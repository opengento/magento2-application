<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager\Environment;

use Magento\Framework\App\ObjectManager\Environment\Developer;

class AppDeveloper extends Developer
{
    /** @var string */
    protected $configPreference = DeveloperFactory::class;
}
