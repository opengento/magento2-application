<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager\Environment\Factory\Dynamic;

use Magento\Framework\ObjectManager\Factory\Dynamic\Developer as DynamicDeveloper;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\ObjectManager\Resetter\ResetterFactory;
use Magento\Framework\ObjectManager\Resetter\ResetterInterface;
use Magento\Framework\ObjectManagerInterface;

class Developer extends DynamicDeveloper implements ResetAfterRequestInterface
{
    private ResetterInterface $resetter;

    public function __construct(...$args)
    {
        $this->resetter = ResetterFactory::create();
        parent::__construct(...$args);
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
