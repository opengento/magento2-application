<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Model;

use Magento\Customer\Model\Visitor;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

class CustomerVisitor extends Visitor implements ResetAfterRequestInterface
{
    public function initShouldSkipRequestLogging(): void
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();
        $this->skipRequestLogging = $this->ignoredUserAgents && in_array($userAgent, $this->ignoredUserAgents, true);
    }

    public function _resetState(): void
    {
        $this->_data = [];
        $this->_origData = [];
        $this->skipRequestLogging = false;
    }
}
