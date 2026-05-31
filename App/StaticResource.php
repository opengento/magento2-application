<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App;

use Exception;
use InvalidArgumentException;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\FileInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\View\Asset\Publisher;
use Magento\Framework\AppInterface;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\Debug;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\Proxy as FilesystemProxy;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Profiler;
use Magento\Framework\Validator\Locale;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Design\Theme\ThemePackageList;
use Psr\Log\LoggerInterface;

class StaticResource implements AppInterface
{
    public function __construct(
        private State $state,
        private FileInterface $response,
        private Http $request,
        private Publisher $publisher,
        private Repository $assetRepo,
        private ModuleList $moduleList,
        private DeploymentConfig $deploymentConfig,
        private File $driver,
        private ThemePackageList $themePackageList,
        private Locale $localeValidator,
        private LoggerInterface $logger,
        private FilesystemProxy $filesystem
    ) {}

    /**
     * Finds requested resource and provides it to the client
     *
     * @throws Exception
     */
    public function launch(): ResponseInterface
    {
        // disabling profiling when retrieving static resource
        Profiler::reset();
        $appMode = $this->state->getMode();
        if ($appMode === State::MODE_PRODUCTION
            && !$this->deploymentConfig->getConfigData(
                ConfigOptionsListConstants::CONFIG_PATH_SCD_ON_DEMAND_IN_PRODUCTION
            )
        ) {
            $this->response->setHttpResponseCode(404);
            return $this->response;
        }

        $path = $this->request->get('resource');
        try {
            $params = $this->parsePath($path ?? '');
        } catch (InvalidArgumentException $e) {
            if ($appMode === State::MODE_PRODUCTION) {
                $this->response->setHttpResponseCode(404);
                return $this->response;
            }
            throw $e;
        }

        if (!($this->isThemeAllowed($params['area'] . DIRECTORY_SEPARATOR . $params['theme'])
            && $this->localeValidator->isValid($params['locale']))
        ) {
            if ($appMode === State::MODE_PRODUCTION) {
                $this->response->setHttpResponseCode(404);
                return $this->response;
            }
            throw new InvalidArgumentException('Requested path ' . $path . ' is wrong.');
        }

        $file = $params['file'];
        unset($params['file']);
        $asset = $this->assetRepo->createAsset($file, $params);
        $this->response->setFilePath($asset->getSourceFile());
        $this->publisher->publish($asset);

        return $this->response;
    }

    /**
     * @inheritdoc
     */
    public function catchException(Bootstrap $bootstrap, Exception $exception): bool
    {
        $this->logger->critical($exception->getMessage());
        if ($bootstrap->isDeveloperMode()) {
            $this->response->setHttpResponseCode(404);
            $this->response->setHeader('Content-Type', 'text/plain');
            $this->response->setBody(
                $exception->getMessage() . "\n" .
                Debug::trace(
                    $exception->getTrace(),
                    true,
                    true,
                    (bool)getenv('MAGE_DEBUG_SHOW_ARGS')
                )
            );
            $this->response->sendResponse();
        } else {
            require $this->filesystem->getDirectoryRead(DirectoryList::PUB)->getAbsolutePath('errors/404.php');
        }

        return true;
    }

    /**
     * Parse path to identify parts needed for searching original file
     *
     * @throws InvalidArgumentException
     */
    private function parsePath(string $path): array
    {
        $path = ltrim($path, '/');
        $safePath = $this->driver->getRealPathSafety($path);
        $parts = explode('/', $safePath, 6);
        if (count($parts) < 5) {
            // Checking that path contains all required parts and is not above static folder.
            throw new InvalidArgumentException("Requested path '$path' is wrong.");
        }

        $result = [];
        $result['area'] = $parts[0];
        $result['theme'] = $parts[1] . '/' . $parts[2];
        $result['locale'] = $parts[3];
        if (count($parts) >= 6 && $this->moduleList->has($parts[4])) {
            $result['module'] = $parts[4];
        } else {
            $result['module'] = '';
            if (isset($parts[5])) {
                $parts[5] = $parts[4] . '/' . $parts[5];
            } else {
                $parts[5] = $parts[4];
            }
        }
        $result['file'] = $parts[5];

        return $result;
    }

    private function isThemeAllowed(string $theme): bool
    {
        return array_key_exists($theme, $this->themePackageList->getThemes());
    }
}
