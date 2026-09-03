<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager;

use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use Opengento\Application\App\Request\RequestRegistry;

use function array_intersect_key;
use function array_replace;
use function parse_url;
use function preg_match;
use function str_starts_with;
use function strtok;
use function trim;

use const BP;
use const PHP_URL_PATH;

class BootstrapPool
{
    private const array ALLOWED_RUNTIME_INIT_PARAMETERS = [
        'MAGE_RUN_CODE' => null,
        'MAGE_RUN_TYPE' => null,
        'MAGE_PROFILER' => null,
        //'MAGE_REQUIRE_MAINTENANCE' => null,//ToDo
        //'MAGE_REQUIRE_IS_INSTALLED' => null,//ToDo
        //'MAGE_DIRS' => null,//ToDo
        //'MAGE_FILESYSTEM_DRIVERS' => null,//ToDo
        //'MAGE_MODE' => null,
        //'MAGE_PROFILER' => null,
        //'MAGE_CONFIG' => null,//ToDo
        //'MAGE_CONFIG_FILE' => null,//ToDo
    ];

    private ObjectManagerInterface $objectManager;
    private AppObjectManagerFactory $factory;
    private array $bootstraps = [];

    public function __construct(
        private array $globalParameters = [],
        private array $allowedRuntimeInitParameters = self::ALLOWED_RUNTIME_INIT_PARAMETERS,
    ) {
        $this->factory = AppBootstrap::createObjectManagerFactory(BP, $this->globalParameters);
        $this->objectManager = $this->factory->create($this->globalParameters);
    }

    /**
     * @throws LocalizedException
     */
    public function get(array $server, array $get): AppBootstrap
    {
        $areaCode = $this->resolveAreaCode($server, $get);
        $bootstrap = $this->bootstraps[$areaCode] ??= $this->createBootstrap($areaCode);
        // Ensure the server arguments are set with the current context
        $bootstrap->getObjectManager()->configure(
            [
                'arguments' => array_replace(
                    $this->globalParameters,
                    $this->allowedRuntimeInitParameters,
                    array_intersect_key($server, $this->allowedRuntimeInitParameters),
                )
            ]
        );

        return $bootstrap;
    }

    /**
     * @throws LocalizedException
     */
    private function createBootstrap(string $areaCode): AppBootstrap
    {
        $bootstrap = AppBootstrap::create(BP, $this->globalParameters, $this->factory);
        $objectManager = $bootstrap->getObjectManager();
        $objectManager->get(State::class)->setAreaCode($areaCode);
        $objectManager->configure($objectManager->get(ConfigLoaderInterface::class)->load($areaCode));

        return $bootstrap;
    }

    private function resolveAreaCode(array $server, array $get): string
    {
        // REQUEST_URI includes the query string: "/graphql?query=..." must resolve as "/graphql".
        $pathInfo = (string)parse_url($server['REQUEST_URI'], PHP_URL_PATH);
        if (str_starts_with($pathInfo, '/get.php/') || str_starts_with($pathInfo, '/media/')) {
            return Area::AREA_GLOBAL;
        }
        if (str_starts_with($pathInfo, '/static.php?')) {
            return strtok(trim($get['resource'] ?? $pathInfo, '/'), '/');
        }
        if (preg_match('/^\/static\/(version\d*\/)?(.*)$/', $pathInfo, $matches)) {
            return strtok(trim($matches[2] ?? $matches[1] ?? $matches[0] ?? $pathInfo, '/'), '/');
        }

        // AreaList::getCodeByFrontName() stores the resolved admin front name on its first call,
        // and FrontNameResolver::getFrontName(true) returns false when the host is not the backend
        // host. A fresh AreaList per request keeps that host check per request. The resolver reads
        // the host from the Request objects, which run() fills only after this lookup, so they are
        // filled here first. strtok() returns false for "/" and would match that false, so the
        // front name is cast to string.
        $this->objectManager->get(RequestRegistry::class)->initFromSuperGlobals();
        foreach ($this->bootstraps as $bootstrap) {
            $bootstrap->getObjectManager()->get(RequestRegistry::class)->initFromSuperGlobals();
        }

        return $this->objectManager->create(AreaList::class)
            ->getCodeByFrontName((string)strtok(trim($pathInfo, '/'), '/'));
    }
}
