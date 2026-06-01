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

use function array_intersect_key;
use function array_replace;
use function preg_match;
use function str_starts_with;
use function strtok;
use function trim;

use const BP;

class BootstrapPool
{
    private const array ALLOWED_RUNTIME_INIT_PARAMETERS = [
        'MAGE_RUN_CODE' => null,
        'MAGE_RUN_TYPE' => null,
        'MAGE_PROFILER' => null,
        //'MAGE_REQUIRE_MAINTENANCE' => null,//ToDo
        //'MAGE_REQUIRE_IS_INSTALLED' => null,//ToDo
    ];
    private const array ALLOWED_SETUP_INIT_PARAMETERS = [
        //'MAGE_DIRS' => null,//ToDo
        //'MAGE_FILESYSTEM_DRIVERS' => null,//ToDo
        //'MAGE_MODE' => null,
        //'MAGE_PROFILER' => null,
        //'MAGE_CONFIG' => null,//ToDo
        //'MAGE_CONFIG_FILE' => null,//ToDo
    ];

    private AppObjectManagerFactory $factory;
    private AreaList $areaList;

    private array $bootstraps = [];
    private array $globalParameters;

    public function __construct(
        array $initParameters =  [],
        array $allowedSetupInitParameters = self::ALLOWED_SETUP_INIT_PARAMETERS,
        private array $allowedRuntimeInitParameters = self::ALLOWED_RUNTIME_INIT_PARAMETERS,
    ) {
        $this->globalParameters = array_intersect_key($initParameters, $allowedSetupInitParameters);
        $this->factory = AppBootstrap::createObjectManagerFactory(BP, $this->globalParameters);
        $this->areaList = $this->factory->create($this->globalParameters)->get(AreaList::class);
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
        $globalObjectManager = $bootstrap->getObjectManager();
        $globalObjectManager->get(State::class)->setAreaCode($areaCode);
        $globalObjectManager->configure($globalObjectManager->get(ConfigLoaderInterface::class)->load($areaCode));

        return $bootstrap;
    }

    private function resolveAreaCode(array $server, array $get): string
    {
        $pathInfo = $server['REQUEST_URI'];
        if (str_starts_with($pathInfo, '/get.php/') || str_starts_with($pathInfo, '/media/')) {
            return Area::AREA_GLOBAL;
        }
        if (str_starts_with($pathInfo, '/static.php?')) {
            $pathInfo = $get['resource'] ?? $pathInfo;
        } elseif (preg_match('/^\/static\/(version\d*\/)?(.*)$/', $pathInfo, $matches)) {
            $pathInfo = $matches[2] ?? $matches[1] ?? $matches[0] ?? $pathInfo;
        }

        return $this->areaList->getCodeByFrontName(strtok(trim($pathInfo, '/'), '/'));
    }
}
