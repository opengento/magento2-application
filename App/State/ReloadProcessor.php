<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\State;

use Magento\Framework\App\State\ReloadProcessorInterface;
use Magento\Framework\Search\Request\Config as SearchRequestConfig;
use Opengento\Application\App\Session\SessionRegistry;
use Opengento\Application\Model\EavConfig;

class ReloadProcessor implements ReloadProcessorInterface
{
    public function __construct(
        private EavConfig $eavConfig,
        private SearchRequestConfig $searchRequestConfig,
        private SessionRegistry $sessionRegistry,
    ) {}

    public function reloadState(): void
    {
        $this->eavConfig->clearRuntimeCache();
        $this->searchRequestConfig->reset();
        $this->sessionRegistry->closeSessions();
        // ToDo: we need to investigate this test in order to reset any missing state not handled natively by the framework:
        // ToDo: @see vendor/magento/magento2-base/dev/tests/integration/framework/Magento/TestFramework/ApplicationStateComparator
    }
}
