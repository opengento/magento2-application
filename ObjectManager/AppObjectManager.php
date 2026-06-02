<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

class AppObjectManager extends ObjectManager implements ResetAfterRequestInterface
{
    public function _resetState(): void
    {
        if ($this->_factory instanceof ResetAfterRequestInterface) {
            $this->_factory->_resetState();
        }
    }
}
