<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\Session;

use Magento\Framework\Session\SessionManagerInterface;
use WeakMap;

/**
 * Registry of sessions that have been started, so they can be committed at the end of the request.
 *
 * Sessions add themselves here when they start (see {@see \Opengento\Application\Plugin\RegisterStartedSession}).
 * At the end of the request {@see \Opengento\Application\App\State\ReloadProcessor} calls closeSessions()
 * to writeClose() them, flushing session data before the worker handles the next request.
 *
 * Sessions are (re)started lazily on first access by {@see \Opengento\Application\Plugin\StartSessionOnAccess},
 * with the current request's area config — so this registry only needs to track and close them. It must
 * NOT eagerly re-start the sessions it holds: on a persistent worker those objects are singletons that
 * outlive the request, and re-starting all of them would re-start sessions belonging to a different area
 * (e.g. a frontend session inside an admin request), corrupting their cookies.
 */
class SessionRegistry
{
    /** @var WeakMap<SessionManagerInterface, bool> */
    private WeakMap $sessions;

    public function __construct()
    {
        $this->sessions = new WeakMap();
    }

    public function add(SessionManagerInterface $sessionManager): void
    {
        $this->sessions[$sessionManager] = true;
    }

    public function closeSessions(): void
    {
        foreach ($this->sessions as $session => $state) {
            $session?->writeClose();
        }
    }
}
