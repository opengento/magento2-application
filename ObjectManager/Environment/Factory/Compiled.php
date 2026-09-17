<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager\Environment\Factory;

use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\Factory\Compiled as FactoryCompiled;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\ObjectManager\Resetter\ResetterInterface;
use Magento\Framework\ObjectManagerInterface;
use Opengento\Application\ObjectManager\Resetter\Resetter;

class Compiled extends FactoryCompiled implements ResetAfterRequestInterface
{
    private ResetterInterface $resetter;

    public function __construct(
        ConfigInterface $config,
        array &$sharedInstances = [],
        array $globalArguments = []
    ) {
        // Use the module's Resetter so reset.json entries applied by reflection also work on PHP 8.4
        // lazy-ghost Interceptors (the framework Resetter's reflection write silently fails on them).
        $this->resetter = new Resetter();
        parent::__construct($config, $sharedInstances, $globalArguments);
    }

    public function create($requestedType, array $arguments = []): object
    {
        $instance = parent::create($requestedType, $arguments);
        $this->resetter->addInstance($instance);

        return $instance;
    }

    public function _resetState(): void
    {
        $this->resetter->_resetState();
    }

    public function setObjectManager(ObjectManagerInterface $objectManager): void
    {
        parent::setObjectManager($objectManager);
        $this->resetter->setObjectManager($objectManager);
    }
}
