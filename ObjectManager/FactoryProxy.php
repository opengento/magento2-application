<?php

declare(strict_types=1);

namespace Opengento\Application\ObjectManager;

use Magento\Framework\ObjectManager\FactoryInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\ObjectManager\Resetter\ResetterInterface;
use Magento\Framework\ObjectManagerInterface;

class FactoryProxy implements FactoryInterface
{
    public function __construct(
        private FactoryInterface $factory,
        private ResetterInterface $resetter,
    ) {}

    public function create($requestedType, array $arguments = []): object
    {
        $object = $this->factory->create($requestedType, $arguments);
        $this->resetter->addInstance($object);

        return $object;
    }

    public function setObjectManager(ObjectManagerInterface $objectManager): void
    {
        $this->factory->setObjectManager($objectManager);
    }
}
