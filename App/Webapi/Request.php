<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Webapi;

use Laminas\Stdlib\Parameters;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Webapi\Request as MagentoWebapiRequest;

/**
 * Worker-mode-aware variant of {@see MagentoWebapiRequest}.
 *
 * Background
 * ----------
 * `Magento\Framework\Webapi\Request` extends `Magento\Framework\HTTP\PhpEnvironment\Request`
 * (which transitively extends `Laminas\Http\PhpEnvironment\Request`). The parent
 * chain holds per-request state on protected fields — `$module`, `$controller`,
 * `$action`, `$pathInfo`, `$requestString`, `$params`, `$aliases`, `$dispatched`,
 * `$forwarded`, `$baseUrl`, `$basePath`, `$requestUri`, `$method`, `$headers`,
 * `$metadata`, `$content` — but neither the parent nor `Webapi\Request` itself
 * implements {@see ResetAfterRequestInterface}.
 *
 * `Magento\Framework\App\Request\Http` (which is a SIBLING class, not a parent
 * of `Webapi\Request`) DOES implement the interface and DOES reset all those
 * fields. So in worker mode the `/index.php` request path stays clean across
 * requests but `/rest/V1/...` and `/soap/...` requests on the same worker
 * inherit state from previous Webapi requests — historically the source of
 * mysterious "wrong store / wrong area / leaked auth" bugs.
 *
 * Fix
 * ---
 * This subclass implements {@see ResetAfterRequestInterface} and provides
 * `_resetState()` with the same body Magento's own `App\Request\Http` uses.
 * Wired in via `etc/di.xml` preference so all DI consumers of
 * `Magento\Framework\Webapi\Request` get our subclass.
 *
 * Compatibility
 * -------------
 * `Magento\Framework\Webapi\Request` is byte-identical between Magento 2.4.7
 * and 2.4.9-beta1 (Copyright header aside). All the protected fields we
 * touch are declared by the shared `HTTP\PhpEnvironment\Request` parent and
 * are stable across the 2.4.x line.
 *
 * Open questions for the maintainer
 * ---------------------------------
 *   1. Are there other Laminas-extending Magento classes with the same
 *      gap that would deserve the same treatment? `Magento\Framework\HTTP\Client\*`
 *      is the next-likely candidate (outbound HTTP client, holds cookies/auth
 *      across requests in worker mode). I scoped this PR to inbound `Webapi\Request`
 *      to keep the change set reviewable; happy to expand.
 *   2. Should we instead push for `Magento\Framework\Webapi\Request` to
 *      implement `ResetAfterRequestInterface` upstream in magento/magento2?
 *      I can file the PR there as a follow-up if you'd like — this subclass
 *      could then become a thin extension that gets retired when upstream
 *      lands.
 */
class Request extends MagentoWebapiRequest implements ResetAfterRequestInterface
{
    public function _resetState(): void
    {
        // Body mirrors Magento\Framework\App\Request\Http::_resetState() — the
        // sibling class for the /index.php path that already does this right.
        $this->setEnv(new Parameters($_ENV));
        $this->serverParams = new Parameters($_SERVER);
        $this->setQuery(new Parameters([]));
        $this->setPost(new Parameters([]));
        $this->setFiles(new Parameters([]));
        $this->module = null;
        $this->controller = null;
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
        $this->method = 'GET';
        $this->allowCustomMethods = true;
        $this->uri = null;
        $this->headers = null;
        $this->metadata = [];
        $this->content = '';
    }
}
