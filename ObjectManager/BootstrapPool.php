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
        $pathInfo = $_SERVER['REQUEST_URI'];
        if (str_starts_with($pathInfo, '/static.php?')) {
            $pathInfo = $_GET['resource'] ?? $pathInfo;
        }
        $areaCode = $this->areaList->getCodeByFrontName(strtok(trim($pathInfo, '/'), '/'));

        return $this->bootstraps[$areaCode] ??= $this->createBootstrap($areaCode);
    }

    /**
     * @throws LocalizedException
     */
    private function createBootstrap(string $areaCode): AppBootstrap
    {
        // ToDo: we don't want to pass $_SERVER but a sanitized version with limited keys
        //       (they are frequently passed via the server conf, but should lives in config.php or env.php):
        //       MAGE_RUN_CODE, MAGE_RUN_TYPE, MAGE_DIRS, MAGE_FILESYSTEM_DRIVERS, MAGE_MODE, MAGE_PROFILER,
        //       MAGE_REQUIRE_MAINTENANCE, MAGE_REQUIRE_IS_INSTALLED, MAGE_CONFIG, MAGE_CONFIG_FILE
        $bootstrap = AppBootstrap::create(BP, $_SERVER, $this->factory);
        $globalObjectManager = $bootstrap->getObjectManager();
        $globalObjectManager->get(State::class)->setAreaCode($areaCode);
        $globalObjectManager->configure($globalObjectManager->get(ConfigLoaderInterface::class)->load($areaCode));

        return $bootstrap;
    }
}
