<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager;

use Magento\Framework\App\ObjectManagerFactory;

class AppObjectManagerFactory extends ObjectManagerFactory
{
    protected $envFactoryClassName = AppEnvironmentFactory::class;
    protected $_locatorClassName = AppObjectManager::class;
}
