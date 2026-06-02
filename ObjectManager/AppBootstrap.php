<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager;

use Exception;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ErrorHandler;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\App\State\ReloadProcessorInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Profiler;
use Psr\Log\LoggerInterface;
use Throwable;

use function gc_collect_cycles;
use function set_error_handler;

class AppBootstrap extends Bootstrap
{
    public static function create($rootDir, array $initParams, ?ObjectManagerFactory $factory = null): static
    {
        self::populateAutoloader($rootDir, $initParams);

        return new self($factory ?? self::createObjectManagerFactory($rootDir, $initParams), $rootDir, $initParams);
    }

    public static function createObjectManagerFactory($rootDir, array $initParams): AppObjectManagerFactory
    {
        return new AppObjectManagerFactory(
            self::createFilesystemDirectoryList($rootDir, $initParams),
            self::createFilesystemDriverPool($initParams),
            self::createConfigFilePool()
        );
    }

    public function run(AppInterface $application): void
    {
        try {
            try {
                Profiler::start('magento');
                set_error_handler([new ErrorHandler(), 'handler']);
                $this->assertMaintenance();
                $this->assertInstalled();
                $application->launch()->sendResponse();
                Profiler::stop('magento');
            } catch (Exception $e) {
                Profiler::stop('magento');
                $this->getObjectManager()->get(LoggerInterface::class)->error($e->getMessage());
                if (!$application->catchException($this, $e)) {
                    throw $e;
                }
            }
        } catch (Throwable $e) {
            $this->terminate($e);
        } finally {
            $this->resetState();
        }
    }

    private function resetState(): void
    {
        $objectManager = $this->getObjectManager();
        $reloadProcessor = $objectManager->get(ReloadProcessorInterface::class);
        $reloadProcessor->reloadState();
        if ($objectManager instanceof ResetAfterRequestInterface) {
            $objectManager->_resetState();
        }
        gc_collect_cycles();
    }
}
