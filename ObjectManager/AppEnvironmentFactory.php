<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager;

use Magento\Framework\App\Area;
use Magento\Framework\App\EnvironmentFactory;
use Magento\Framework\App\ObjectManager\ConfigLoader\Compiled;
use Opengento\Application\ObjectManager\Environment\AppCompiled;
use Opengento\Application\ObjectManager\Environment\AppDeveloper;

class AppEnvironmentFactory extends EnvironmentFactory
{
    public function createEnvironment(): AppCompiled|AppDeveloper
    {
        return file_exists(Compiled::getFilePath(Area::AREA_GLOBAL)) ? new AppCompiled($this) : new AppDeveloper($this);
    }
}
