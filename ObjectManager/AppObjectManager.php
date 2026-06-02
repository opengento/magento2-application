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
use Magento\Framework\ObjectManager\Resetter\ResetterFactory;
use Magento\Framework\ObjectManager\Resetter\ResetterInterface;

class AppObjectManager extends ObjectManager implements ResetAfterRequestInterface
{
    private ResetterInterface $resetter;

    public function __construct(
        FactoryInterface $factory,
        ConfigInterface $config,
        array &$sharedInstances = []
    ) {
        $this->resetter = ResetterFactory::create();
        $this->resetter->setObjectManager($this);
        parent::__construct(new FactoryProxy($factory, $this->resetter), $config, $sharedInstances);
    }

    /**
     * @ingeritdoc
     */
    public function _resetState(): void
    {
        $this->resetter->_resetState();
    }
}
