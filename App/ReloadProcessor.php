<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App;

use Magento\Framework\App\State\ReloadProcessorInterface;

class ReloadProcessor implements ReloadProcessorInterface
{
    public function reloadState(): void
    {
        // ToDo: we need to investigate this test in order to reset any missing state not handled natively by the framework:
        // ToDo: @see vendor/magento/magento2-base/dev/tests/integration/framework/Magento/TestFramework/ApplicationStateComparator
    }
}
