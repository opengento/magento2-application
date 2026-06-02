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
use Magento\Framework\ObjectManager\Resetter\ResetterFactory;
use Magento\Framework\ObjectManager\Resetter\ResetterInterface;
use Magento\Framework\ObjectManagerInterface;
use Opengento\Application\ObjectManager\AppObjectManager;

class Compiled extends FactoryCompiled implements ResetAfterRequestInterface
{
    private ResetterInterface $resetter;
    private array $sharedInstances;

    public function __construct(
        ConfigInterface $config,
        array &$sharedInstances = [],
        array $globalArguments = []
    ) {
        $this->resetter = ResetterFactory::create();
        $this->sharedInstances = &$sharedInstances;
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
        foreach (AppObjectManager::INSTANCES_TO_UNSET as $type) {
            unset($this->sharedInstances[$type]);
        }
    }

    public function setObjectManager(ObjectManagerInterface $objectManager): void
    {
        parent::setObjectManager($objectManager);
        $this->resetter->setObjectManager($objectManager);
    }
}
