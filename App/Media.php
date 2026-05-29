<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App;

use Exception;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\Cookie\PhpCookieReader;
use Magento\Framework\Stdlib\StringUtils;
use Magento\MediaStorage\App\MediaFactory;
use Magento\MediaStorage\Model\File\Storage\Response;
use Magento\MediaStorage\Model\File\Storage\Request as StorageRequest;

use function str_starts_with;

class Media implements AppInterface
{
    public function __construct(
        private Filesystem $filesystem,
        private SerializerInterface $serializer,
        private MediaFactory $mediaFactory,
        private Response $response,
    ) {}

    /**
     * @throws FileSystemException
     */
    public function launch(): ResponseInterface
    {
        $mediaDirectory = null;
        $varDirectoryRead = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $configCacheFile = 'resource_config.json';
        $filePath = (new StorageRequest(new Request(new PhpCookieReader(), new StringUtils())))->getPathInfo();
        if ($varDirectoryRead->isExist($configCacheFile) && $varDirectoryRead->isReadable($configCacheFile)) {
            $config = $this->serializer->unserialize($varDirectoryRead->readFile($configCacheFile));

            // Checking update time
            if (isset($config['update_time'], $config['media_directory'], $config['allowed_resources'])
                && $varDirectoryRead->stat($configCacheFile)['mtime'] + $config['update_time'] > time()
            ) {
                $mediaDirectory = $config['media_directory'];
                $allowedResources = $config['allowed_resources'];

                // Serve file if it's materialized
                if ($mediaDirectory) {
                    $fileAbsolutePath = $this->filesystem->getDirectoryRead(DirectoryList::PUB)->getAbsolutePath($filePath);
                    $mediaDirectoryRead = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
                    $fileRelativePath = $mediaDirectoryRead->getRelativePath($fileAbsolutePath);

                    if (!$this->isAllowed($fileRelativePath, $allowedResources)) {
                        $this->response->setHttpResponseCode(404);
                        $this->response->setFilePath($fileAbsolutePath);

                        return $this->response;
                    }
                    if ($mediaDirectoryRead->isReadable($fileRelativePath)) {
                        $this->response->setFilePath($fileAbsolutePath);
                        if ($mediaDirectoryRead->isDirectory($fileRelativePath)) {
                            $this->response->setHttpResponseCode(404);
                        }

                        return $this->response;
                    }
                }
            }
        }

        // ToDo
        // Materialize file in application
        //$params = [];
        //if (empty($mediaDirectory)) {
        //    $params[ObjectManagerFactory::INIT_PARAM_DEPLOYMENT_CONFIG] = [];
        //    $params[Factory::PARAM_CACHE_FORCED_OPTIONS] = ['frontend_options' => ['disable_save' => true]];
        //}

        return $this->mediaFactory->create(
            [
                'mediaDirectory' => $mediaDirectory,
                'configCacheFile' => $varDirectoryRead->getAbsolutePath($configCacheFile),
                'isAllowed' => $this->isAllowed(...),
                'relativeFileName' => $filePath,
            ]
        )->launch();
    }

    public function catchException(Bootstrap $bootstrap, Exception $exception): bool
    {
        $this->response->setHttpResponseCode(404);
        if ($bootstrap->isDeveloperMode()) {
            $this->response->setHeader('Content-Type', 'text/plain');
            $this->response->setBody($exception->getMessage() . "\n" . $exception->getTraceAsString());
        }
        $this->response->sendResponse();

        return true;
    }

    private function isAllowed(string $resource, array $allowedResources): bool
    {
        foreach ($allowedResources as $allowedResource) {
            if (str_starts_with($resource, $allowedResource)) {
                return true;
            }
        }

        return false;
    }
}
