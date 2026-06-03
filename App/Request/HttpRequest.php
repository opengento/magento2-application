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
    private bool $initialized = true;

    public function _resetState(): void
    {
        parent::_resetState();
        $this->setEnv(new Parameters());
        $this->serverParams = new Parameters();
        $this->originalPathInfo = null;
        $this->initialized = false;
    }

    public function getEnv($name = null, $default = null)
    {
        if (!$this->initialized) {
            $this->reset();
        }
        return parent::getEnv($name, $default);
    }

    public function getQuery($name = null, $default = null)
    {
        if (!$this->initialized) {
            $this->reset();
        }
        return parent::getQuery($name, $default);
    }

    public function getPost($name = null, $default = null)
    {
        if (!$this->initialized) {
            $this->reset();
        }
        return parent::getPost($name, $default);
    }

    public function getCookie($name = null, $default = null): ?string
    {
        if (!$this->initialized) {
            $this->reset();
        }
        return parent::getCookie($name, $default);
    }

    public function getFiles($name = null, $default = null)
    {
        if (!$this->initialized) {
            $this->reset();
        }
        return parent::getFiles($name, $default);
    }

    public function getServer($name = null, $default = null)
    {
        if (!$this->initialized) {
            $this->reset();
        }
        return parent::getServer($name, $default);
    }

    private function reset(): void
    {
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

        $this->initialized = true;
    }
}
