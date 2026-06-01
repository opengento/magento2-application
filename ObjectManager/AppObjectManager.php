<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\FactoryInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\ObjectManager\Resetter\Resetter;
use Magento\Framework\ObjectManager\Resetter\ResetterInterface;
use ReflectionException;

class AppObjectManager extends ObjectManager implements ResetAfterRequestInterface
{
    private ResetterInterface $resetter;

    public function __construct(
        FactoryInterface $factory,
        ConfigInterface $config,
        array &$sharedInstances = []
    ) {
        $this->resetter = new Resetter();
        $this->resetter->setObjectManager($this);
        parent::__construct(new FactoryProxy($factory, $this->resetter), $config, $sharedInstances);
    }

    /**
     * @ingeritdoc
     * @throws ReflectionException
     */
    public function _resetState(): void
    {
        $this->resetter->_resetState();
    }
}
