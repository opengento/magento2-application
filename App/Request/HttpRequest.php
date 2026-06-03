<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\Request;

use Laminas\Stdlib\Parameters;
use Magento\Framework\App\Request\Http;

class HttpRequest extends Http
{
    public function _resetState(): void
    {
        parent::_resetState();
        $this->originalPathInfo = null;
        $this->beforeForwardInfo = [];
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
    }
}
