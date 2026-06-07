<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\State;

use Opengento\Application\App\Session\SessionRegistry;
use Opengento\Application\Model\CustomerVisitor;

class InitProcessor
{
    public function __construct(
        private SessionRegistry $sessionRegistry,
        private CustomerVisitor $customerVisitor,
    ) {}

    public function init(): void
    {
        $this->sessionRegistry->startSessions();
        $this->customerVisitor->initShouldSkipRequestLogging();
    }
}
