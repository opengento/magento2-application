<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Model;

use Magento\Eav\Model\Config;
use Magento\Framework\App\Cache\Type\Dummy;
use Magento\Framework\App\ObjectManager;

class EavConfig extends Config
{
    public function clearRuntimeCache(): void
    {
        $cache = $this->_cache;
        $this->_cache = ObjectManager::getInstance()->get(Dummy::class);
        $this->clear();
        $this->_cache = $cache;
    }
}
