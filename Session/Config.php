<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Session;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Session\Config as BaseConfig;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\ValidatorFactory;

/**
 * Session config that always stores `session.name` in $options so `initIniOptions()` re-asserts it on
 * every `start()` — undoing cross-area `session.name` contamination on a persistent worker.
 *
 * The base `Config` never calls `setName()`, so `session.name` is absent from $options and
 * `initIniOptions()` never `ini_set()`s it. After an admin request set `ini session.name='admin'`
 * process-globally (via `AdminConfig`), a later storefront/REST `start()` would read `$_COOKIE['admin']`
 * instead of the storefront cookie, loading the wrong session (or none). Storing the name here forces
 * `initIniOptions()` to reset it before `session_start()`.
 *
 * Registered as the preference for `Magento\Framework\Session\Config`, so it covers every session using
 * the base config (storefront + REST). The admin area uses its own `AdminConfig` and is unaffected.
 * `scopeType` defaults to the default scope so cookie settings resolve like the globally-configured base
 * config; `sessionName` is a DI argument (default 'PHPSESSID') for stores that use a custom cookie name.
 */
#[\Magento\Framework\ObjectManager\Attribute\NonLazy]
class Config extends BaseConfig
{
    /**
     * @param ValidatorFactory $validatorFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param StringUtils $stringHelper
     * @param RequestInterface $request
     * @param Filesystem $filesystem
     * @param DeploymentConfig $deploymentConfig
     * @param string $scopeType
     * @param string $lifetimePath
     * @param string $sessionName
     */
    public function __construct(
        ValidatorFactory $validatorFactory,
        ScopeConfigInterface $scopeConfig,
        StringUtils $stringHelper,
        RequestInterface $request,
        Filesystem $filesystem,
        DeploymentConfig $deploymentConfig,
        string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        string $lifetimePath = self::XML_PATH_COOKIE_LIFETIME,
        string $sessionName = 'PHPSESSID'
    ) {
        parent::__construct(
            $validatorFactory,
            $scopeConfig,
            $stringHelper,
            $request,
            $filesystem,
            $deploymentConfig,
            $scopeType,
            $lifetimePath
        );

        // Store the name in $options so initIniOptions() always ini_set('session.name', ...),
        // resetting any stale 'admin' contamination before session_start() reads the cookie.
        $this->setName($sessionName);
    }
}
