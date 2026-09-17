<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Plugin;

use Magento\Framework\App\Response\HttpInterface;
use Opengento\Application\App\Http;
use Opengento\Application\App\Session\SessionRegistry;

/**
 * Commit (writeClose) the request's sessions BEFORE the response is sent.
 *
 * By default sessions are only closed in the reset phase ({@see \Opengento\Application\App\State\ReloadProcessor}),
 * which runs AFTER the response has been sent. In a multi-worker setup that is too late for the two
 * cases where a response immediately triggers a cross-worker follow-up that reads the same session:
 *  - admin login: the session id is regenerated and a 302 is sent; the browser follows it and another
 *    worker handles the GET before the first worker persisted the new session, so the admin looks logged
 *    out and bounces back to the login screen;
 *  - REST → storefront: a REST call writes the session (e.g. a last order id) and a storefront request
 *    reads it before the REST worker persisted it.
 *
 * Closing here writes to storage immediately, so the data is visible to every worker the moment the
 * response leaves. The reset-phase close then finds the sessions already closed (a no-op).
 *
 * Registered in adminhtml and webapi_rest only: closing frontend sessions before the response is sent
 * interferes with the storefront session lifecycle (private-content / cart handling) and causes login
 * and cart errors, so this must not be global.
 */
class CommitSessionsBeforeResponse
{
    public function __construct(private readonly SessionRegistry $sessionRegistry) {}

    /**
     * @param Http $subject
     * @param HttpInterface $result
     * @return HttpInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterLaunch(Http $subject, HttpInterface $result): HttpInterface
    {
        $this->sessionRegistry->closeSessions();

        return $result;
    }
}
