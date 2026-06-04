<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\Response;

use Magento\Framework\App\State;
use Magento\Framework\Webapi\ErrorProcessor;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Webapi\Rest\Response;

class RestResponse extends Response
{
    public function __construct(
        private Response\RendererFactory $rendererFactory,
        ErrorProcessor $errorProcessor,
        State $appState
    ) {
        parent::__construct($rendererFactory, $errorProcessor, $appState);
    }

    public function _resetState(): void
    {
        parent::_resetState();
        $this->exceptions = [];
        $this->_renderer = null;
    }

    /**
     * @throws Exception
     */
    protected function _renderMessages(): self
    {
        $this->setRenderer();
        return parent::_renderMessages();
    }

    /**
     * @throws Exception
     */
    protected function _render($data): void
    {
        $this->setRenderer();
        parent::_render($data);
    }

    /**
     * @throws Exception
     */
    private function setRenderer(): void
    {
        $this->_renderer ??= $this->rendererFactory->get();
    }
}
