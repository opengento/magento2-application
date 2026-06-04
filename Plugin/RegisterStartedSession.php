<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Plugin;

use Magento\Framework\Session\SessionManager;
use Opengento\Application\App\Session\SessionRegistry;

class RegisterStartedSession
{
    public function __construct(private SessionRegistry $sessionRegistry) {}

    public function beforeStart(SessionManager $subject): array
    {
        $this->sessionRegistry->add($subject);

        return [];
    }
}
