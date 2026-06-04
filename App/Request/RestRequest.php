<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\Request;

use Laminas\Stdlib\Parameters;
use Magento\Framework\App\AreaList;
use Magento\Framework\Config\ScopeInterface;
use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Webapi\Rest\Request;

class RestRequest extends Request
{
    public function __construct(
        CookieReaderInterface $cookieReader,
        StringUtils $converter,
        private AreaList $areaList,
        private ScopeInterface $configScope,
        Request\DeserializerFactory $deserializerFactory,
        RequestRegistry $requestRegistry,
        $uri = null
    ) {
        parent::__construct($cookieReader, $converter, $areaList, $configScope, $deserializerFactory, $uri);
        $requestRegistry->add($this);
    }

    public function _resetState(): void
    {
        $this->setEnv(new Parameters());
        $this->serverParams = new Parameters();
        $this->setQuery(new Parameters([]));
        $this->setPost(new Parameters([]));
        $this->setFiles(new Parameters([]));
        $this->module = null;
        $this->controller= null;
        $this->action = null;
        $this->pathInfo = '';
        $this->requestString = '';
        $this->params = [];
        $this->aliases = [];
        $this->dispatched = false;
        $this->forwarded = null;
        $this->baseUrl = null;
        $this->basePath = null;
        $this->requestUri = null;
        $this->method = self::METHOD_GET;
        $this->allowCustomMethods = true;
        $this->uri = null;
        $this->headers = null;
        $this->metadata = [];
        $this->content = '';
        $this->_deserializer = null;
        $this->_bodyParams = null;
    }

    public function initFromSuperGlobals(): void
    {
        $this->_resetState();

        $this->setEnv(new Parameters($_ENV));

        if ($_GET) {
            $this->setQuery(new Parameters($_GET));
        }
        if ($_POST) {
            $this->setPost(new Parameters($_POST));
        }
        if ($_COOKIE) {
            $this->setCookies(new Parameters($_COOKIE));
        }
        if ($_FILES) {
            // convert PHP $_FILES superglobal
            $files = $this->mapPhpFiles();
            $this->setFiles(new Parameters($files));
        }

        $this->setServer(new Parameters($_SERVER));

        $pathInfo = $this->getRequestUri();
        /** Remove base url and area from path */
        $areaFrontName = $this->areaList->getFrontName($this->configScope->getCurrentScope());
        $pathInfo = preg_replace("#.*?/{$areaFrontName}/?#", '/', $pathInfo);
        /** Remove GET parameters from path */
        $pathInfo = preg_replace('#\?.*#', '', $pathInfo);
        $this->setPathInfo($pathInfo);
    }
}
