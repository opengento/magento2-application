<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\Request;

use WeakMap;

class RequestRegistry
{
    /** @var WeakMap<HttpRequest|WebapiRequest|RestRequest, bool> */
    private WeakMap $requests;

    public function __construct()
    {
        $this->requests = new WeakMap();
    }

    public function add(HttpRequest|WebapiRequest|RestRequest $request): void
    {
        $this->requests[$request] = true;
    }

    public function initFromSuperGlobals(): void
    {
        foreach ($this->requests as $request => $state) {
            $request?->initFromSuperGlobals();
        }
    }
}
