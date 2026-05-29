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

use function array_fill_keys;
use function array_intersect_key;
use function array_keys;
use function array_replace;
use function strtok;
use function trim;

use const BP;

class BootstrapPool
{
    private const array ALLOWED_RUNTIME_INIT_PARAMETERS = [
        'MAGE_RUN_CODE' => 1,
        'MAGE_RUN_TYPE' => 1,
        'MAGE_PROFILER' => 1,
        'MAGE_REQUIRE_MAINTENANCE' => 1,
        'MAGE_REQUIRE_IS_INSTALLED' => 1,
    ];
    private const array ALLOWED_SETUP_INIT_PARAMETERS = [
        'MAGE_DIRS' => 1,
        'MAGE_FILESYSTEM_DRIVERS' => 1,
        'MAGE_MODE' => 1,
        'MAGE_PROFILER' => 1,
        'MAGE_CONFIG' => 1,
        'MAGE_CONFIG_FILE' => 1,
    ];

    private AppObjectManagerFactory $factory;
    private AreaList $areaList;

    private array $bootstraps = [];
    private array $globalParameters;

    public function __construct(
        array $allowedSetupInitParameters = self::ALLOWED_SETUP_INIT_PARAMETERS,
        private array $allowedRuntimeInitParameters = self::ALLOWED_RUNTIME_INIT_PARAMETERS,
    ) {
        $this->globalParameters = array_intersect_key($_SERVER, $allowedSetupInitParameters);
        $this->factory = AppBootstrap::createObjectManagerFactory(BP, $this->globalParameters);
        $this->areaList = $this->factory->create($this->globalParameters)->get(AreaList::class);
    }

    /**
     * @throws LocalizedException
     */
    public function get(): AppBootstrap
    {
        $pathInfo = $_SERVER['REQUEST_URI'];
        if (str_starts_with($pathInfo, '/static.php?')) {
            $pathInfo = $_GET['resource'] ?? $pathInfo;
        }
        $areaCode = $this->areaList->getCodeByFrontName(strtok(trim($pathInfo, '/'), '/'));

        $bootstrap = $this->bootstraps[$areaCode] ??= $this->createBootstrap($areaCode);
        // Ensure the $_SERVER based init parameters are set with the current context
        $bootstrap->getObjectManager()->configure(
            [
                'arguments' => array_replace(
                    $this->globalParameters,
                    array_fill_keys(array_keys($this->allowedRuntimeInitParameters), null),
                    array_intersect_key($_SERVER, $this->allowedRuntimeInitParameters),
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
}
