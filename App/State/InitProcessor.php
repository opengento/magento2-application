<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\State;

use Opengento\Application\Model\CustomerVisitor;

class InitProcessor
{
    public function __construct(
        private CustomerVisitor $customerVisitor,
    ) {}

    public function init(): void
    {
        // Sessions are (re)started lazily on first access this request by Plugin\StartSessionOnAccess,
        // so nothing is eagerly started here. Eagerly re-starting every registered session on a warm
        // worker would re-start sessions from another area (verified unsafe).
        $this->customerVisitor->initShouldSkipRequestLogging();
    }
}
