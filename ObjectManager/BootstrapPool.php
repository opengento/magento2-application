<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager;

use Magento\Framework\App\AreaList;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;

use function strtok;
use function trim;

use const BP;

class BootstrapPool
{
    private AppObjectManagerFactory $factory;
    private AreaList $areaList;

    private array $bootstraps = [];

    public function __construct()
    {
        $this->factory = AppBootstrap::createObjectManagerFactory(BP, $_SERVER);
        $this->areaList = $this->factory->create($_SERVER)->get(AreaList::class);
    }

    /**
     * @throws LocalizedException
     */
    public function get(): AppBootstrap
    {
        // Prefer SCRIPT_FILENAME for area routing — FrankenPHP sets it from
        // each worker's `file` directive at worker boot, so it's stable across
        // try_files rewrites. REQUEST_URI is not: a Caddyfile that rewrites
        // /static/version<N>/foo.css → /static.php?resource=foo.css before
        // dispatching to the static.php worker leaves REQUEST_URI as
        // "/static.php?resource=foo.css", which fails the "starts with
        // static/" check below and falls through to the default `frontend`
        // area — wrong DI graph for static.php's StaticResource app.
        $scriptName = isset($_SERVER['SCRIPT_FILENAME']) ? \basename($_SERVER['SCRIPT_FILENAME']) : '';
        if ($scriptName === 'static.php') {
            $pathInfo = $_GET['resource'] ?? '';
        } else {
            $pathInfo = $_SERVER['REQUEST_URI'] ?? '';
            if (str_starts_with(trim($pathInfo, '/') . '/', 'static/')) {
                $pathInfo = $_GET['resource'] ?? $pathInfo;
            }
        }
        $areaCode = $this->areaList->getCodeByFrontName(strtok(trim($pathInfo, '/'), '/'));

        return $this->bootstraps[$areaCode] ??= $this->createBootstrap($areaCode);
    }

    /**
     * @throws LocalizedException
     */
    private function createBootstrap(string $areaCode): AppBootstrap
    {
        $bootstrap = AppBootstrap::create(BP, $_SERVER, $this->factory);
        $globalObjectManager = $bootstrap->getObjectManager();
        $globalObjectManager->get(State::class)->setAreaCode($areaCode);
        $globalObjectManager->configure($globalObjectManager->get(ConfigLoaderInterface::class)->load($areaCode));

        return $bootstrap;
    }
}
