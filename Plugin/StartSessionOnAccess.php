<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Plugin;

use Magento\Framework\Exception\SessionException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Session\SessionManager;
use WeakMap;

/**
 * (Re)start a session lazily on first access during the current request.
 *
 * A SessionManager singleton is constructed once per worker; its constructor calls start() a single
 * time for the whole worker lifetime. On every subsequent request the object is reused without
 * re-construction, so start() is never called again and its storage stays bound to a previous request's
 * $_SESSION (a reference break). start()'s storage->init($_SESSION) is the only thing that re-binds it.
 *
 * This plugin runs start() before the first access this request, through the entry points that actually
 * read session state:
 *  - before__call / beforeGetData — magic getters/setters and explicit getData() on any session;
 *  - beforeIsLoggedIn / beforeGetCustomerId — Customer/Auth methods whose inner reads hit
 *    $this->storage->getData() directly (bypassing __call/getData), so start must run before them.
 *
 * Only the sessions a request actually touches are (re)started, with the current area's config — so it
 * never re-starts an unrelated or cross-area session the way eagerly iterating a persistent registry
 * would. This makes "sessions are lazily started on first access" literally true on a persistent worker.
 * Wired on Magento\Framework\Session\SessionManager so every session subclass is covered; downstream
 * integrations may add before-hooks for other direct-storage entry points on their own session classes.
 */
class StartSessionOnAccess implements ResetAfterRequestInterface
{
    /** @var WeakMap<SessionManager, true> Sessions already started this request. */
    private WeakMap $started;

    /** @var bool Re-entry guard for the getCustomerId repair. */
    private bool $repairing = false;

    public function __construct()
    {
        $this->started = new WeakMap();
    }

    /**
     * Magic get/set/uns/has (getQuoteId, setLastOrderId, …) route through __call.
     *
     * @param SessionManager $subject
     * @param string $method
     * @param array $args
     * @return void
     */
    public function before__call(SessionManager $subject, $method, $args = []): void
    {
        $this->ensureStarted($subject);
    }

    /**
     * getData() is an explicit method, so it does not route through __call.
     *
     * @param SessionManager $subject
     * @param string $key
     * @param bool $clear
     * @return void
     */
    public function beforeGetData(SessionManager $subject, $key = '', $clear = false): void
    {
        $this->ensureStarted($subject);
    }

    /**
     * Customer/Auth isLoggedIn() reads storage directly and must be started first.
     *
     * @param SessionManager $subject
     * @return void
     */
    public function beforeIsLoggedIn(SessionManager $subject): void
    {
        $this->ensureStarted($subject);
    }

    /**
     * Customer\Session::getCustomerId() reads $this->storage->getData() directly (bypasses __call).
     *
     * @param SessionManager $subject
     * @return void
     */
    public function beforeGetCustomerId(SessionManager $subject): void
    {
        $this->ensureStarted($subject);
    }

    /**
     * Repair a null customer id caused by a mid-request reference break.
     *
     * Customer\Session::isLoggedIn() can read a truthy id while a later getCustomerId() on the SAME
     * request returns null — the customer session's storage _data reference was severed in between (e.g.
     * by another session's writeClose). A plain re-read still fails; start() re-runs
     * storage->init($_SESSION), re-binding _data, so the retry sees the id. Without this,
     * Checkout\DefaultConfigProvider::getCustomer() calls customerRepository->getById('') and throws
     * NoSuchEntityException on the cart/checkout page. Runs at the SessionManager level, so it reaches
     * Customer\Session regardless of which Storage subclass that session uses.
     *
     * Skipped when the session was deliberately write-closed mid-request (PageCache depersonalize): there
     * the empty read is intentional and repairing it would re-personalize a cacheable render — see
     * isClosedMidRequest().
     *
     * @param SessionManager $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetCustomerId(SessionManager $subject, $result)
    {
        if (!empty($result) || $this->repairing || $this->isClosedMidRequest()) {
            return $result;
        }
        $this->repairing = true;
        try {
            $subject->start();
            return $subject->getCustomerId();
        } catch (\Throwable) {
            return $result;
        } finally {
            $this->repairing = false;
        }
    }

    /**
     * Start the session once per request, (re)binding its storage to the current $_SESSION.
     *
     * @param SessionManager $subject
     * @return void
     */
    private function ensureStarted(SessionManager $subject): void
    {
        if (isset($this->started[$subject])) {
            return;
        }
        if ($this->isClosedMidRequest()) {
            return;
        }
        // Set the flag BEFORE start(): start() runs validator->validate($this), which reads session
        // state and re-enters this plugin. The pre-set flag breaks that recursion.
        $this->started[$subject] = true;
        try {
            $subject->start();
        } catch (SessionException) {
            // Area code momentarily unavailable, or the validator rejected a stale cookie. Leave the
            // flag set so a single failed attempt does not become a per-access retry storm.
        }
    }

    /**
     * Whether a session started this request has since been deliberately write-closed.
     *
     * That combination only occurs after a mid-request session_write_close(): PageCache's depersonalize
     * plugin persists the session and the per-module depersonalize plugins then clear the in-memory data,
     * so the rest of the render is cache-safe. From that point on, neither ensureStarted() nor the
     * getCustomerId repair may start() the session again — a restart would re-read the persisted data and
     * re-personalize a render that may be captured by the full-page cache. Reads see the intentionally
     * cleared storage instead, matching PHP-FPM behaviour, where every session was constructed (and
     * therefore started) before the depersonalize wipe.
     *
     * @return bool
     */
    private function isClosedMidRequest(): bool
    {
        return count($this->started) > 0 && session_status() === PHP_SESSION_NONE;
    }

    /**
     * @return void
     */
    public function _resetState(): void
    {
        $this->started = new WeakMap();
        $this->repairing = false;
    }
}
