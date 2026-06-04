<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\Response;

use LogicException;
use Magento\Framework\Webapi\Rest\Response\RendererFactory;
use Magento\Framework\Webapi\Rest\Response\RendererInterface;

class RestRendererFactory extends RendererFactory
{
    public function get(): RendererInterface
    {
        $renderer = $this->_objectManager->create($this->_getRendererClass());
        if (!$renderer instanceof RendererInterface) {
            throw new LogicException(
                'The renderer must implement "' . RendererInterface::class . '".'
            );
        }

        return $renderer;
    }
}
