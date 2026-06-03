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
    public const array INSTANCES_TO_UNSET = [
        /** Stateful HTTP Response */
        \Laminas\Http\PhpEnvironment\Response::class,
        \Magento\Framework\HTTP\PhpEnvironment\Response::class,
        \Magento\Framework\App\Response\Http::class,
        \Magento\MediaStorage\Model\File\Storage\Response::class,
        \Magento\Framework\App\Response\File::class,
        \Magento\Framework\App\Response\RedirectInterface::class,
        \Magento\Framework\App\Action\Forward::class,
        \Magento\Framework\App\Action\Redirect::class,

        /** OTHER */
        \Magento\Framework\App\Action\Context::class,
        \Magento\Framework\View\Element\Context::class,
        \Magento\Framework\Webapi\ErrorProcessor::class,
        \Magento\Framework\Search\Request\Config::class,
    ];

    public function _resetState(): void
    {
        if ($this->_factory instanceof ResetAfterRequestInterface) {
            $this->_factory->_resetState();
        }
        foreach (self::INSTANCES_TO_UNSET as $type) {
            unset($this->_sharedInstances[$type]);
        }
    }
}
