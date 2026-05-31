<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App;

use Exception;
use LogicException;
use Magento\Catalog\Model\Config\CatalogMediaConfig;
use Magento\Catalog\Model\View\Asset\PlaceholderFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\Cookie\PhpCookieReader;
use Magento\Framework\Stdlib\StringUtils;
use Magento\MediaStorage\Model\File\Storage\Config;
use Magento\MediaStorage\Model\File\Storage\ConfigFactory;
use Magento\MediaStorage\Model\File\Storage\Request as StorageRequest;
use Magento\MediaStorage\Model\File\Storage\Response;
use Magento\MediaStorage\Model\File\Storage\SynchronizationFactory;
use Magento\MediaStorage\Service\ImageResize;

use function rtrim;
use function str_starts_with;

use const PHP_EOL;

class Media implements AppInterface
{
    private WriteInterface $pubDirectory;
    private WriteInterface $mediaDirectory;

    public function __construct(
        private Filesystem $filesystem,
        private SerializerInterface $serializer,
        private Response $response,
        private ConfigFactory $configFactory,
        private SynchronizationFactory $syncFactory,
        private PlaceholderFactory $placeholderFactory,
        private CatalogMediaConfig $catalogMediaConfig,
        private ImageResize $imageResize,
    ) {
        $this->pubDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::PUB);
        $this->mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * @throws FileSystemException
     */
    public function launch(): ResponseInterface
    {
        ['media_directory' => $mediaDirectory, 'allowed_resources' => $allowedResources] = $this->loadResourceConfig();
        $filePath = (new StorageRequest(new Request(new PhpCookieReader(), new StringUtils())))->getPathInfo();

        // ToDo
        // Materialize file in application
        //$params = [];
        //if (empty($mediaDirectory)) {
        //    $params[ObjectManagerFactory::INIT_PARAM_DEPLOYMENT_CONFIG] = [];
        //    $params[Factory::PARAM_CACHE_FORCED_OPTIONS] = ['frontend_options' => ['disable_save' => true]];
        //}

        $fileAbsolutePath = $this->pubDirectory->getAbsolutePath($filePath);
        $fileRelativePath = str_replace(rtrim($mediaDirectory, '/') . '/', '', $fileAbsolutePath);
        if (!$this->isAllowed($fileRelativePath, $allowedResources)) {
            throw new LogicException('The path is not allowed: ' . $filePath);
        }
        if ($this->pubDirectory->isReadable($filePath)) {
            if ($this->pubDirectory->isDirectory($filePath)) {
                throw new LogicException('The path is not a valid file: ' . $filePath);
            }
            $this->response->setFilePath($fileAbsolutePath);

            return $this->response;
        }

        try {
            $this->createLocalCopy($filePath);

            if ($this->pubDirectory->isReadable($filePath)) {
                $this->response->setFilePath($fileAbsolutePath);
            } else {
                $this->setPlaceholderImage();
            }
        } catch (Exception) {
            $this->setPlaceholderImage();
        }

        return $this->response;
    }

    public function catchException(Bootstrap $bootstrap, Exception $exception): bool
    {
        $this->response->setHttpResponseCode(404);
        if ($bootstrap->isDeveloperMode()) {
            $this->response->setHeader('Content-Type', 'text/plain');
            $this->response->setBody($exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
        }
        $this->response->sendResponse();

        return true;
    }

    /**
     * @return array{media_directory: string, allowed_resources: array}
     * @throws FileSystemException
     */
    private function loadResourceConfig(): array
    {
        $mediaDirectory = null;
        $allowedResources = [];
        $resConfigFile = 'resource_config.json';

        $varDirectoryRead = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        if ($varDirectoryRead->isExist($resConfigFile) && $varDirectoryRead->isReadable($resConfigFile)) {
            $config = $this->serializer->unserialize($varDirectoryRead->readFile($resConfigFile));
            if (isset($config['update_time'], $config['media_directory'], $config['allowed_resources'])
                && $varDirectoryRead->stat($resConfigFile)['mtime'] + $config['update_time'] > time()
            ) {
                $mediaDirectory = $config['media_directory'];
                $allowedResources = $config['allowed_resources'];
            }
        }
        if (!$mediaDirectory || (rtrim($mediaDirectory, '/') !== rtrim($this->mediaDirectory->getAbsolutePath(), '/'))) {
            /** @var Config $config */
            $config = $this->configFactory->create(['cacheFile' => $varDirectoryRead->getAbsolutePath($resConfigFile)]);
            $config->save();
            $mediaDirectory = $config->getMediaDirectory();
            $allowedResources = $config->getAllowedResources();
        }

        return ['media_directory' => $mediaDirectory, 'allowed_resources' => $allowedResources];
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

    /**
     * Create local copy of file and perform resizing if necessary.
     *
     * @throws NotFoundException
     */
    private function createLocalCopy(string $fileName): void
    {
        $synchronizer = $this->syncFactory->create(['directory' => $this->pubDirectory]);
        $synchronizer->synchronize($fileName);

        if (!$this->pubDirectory->isReadable($fileName)
            && $this->catalogMediaConfig->getMediaUrlFormat() === CatalogMediaConfig::HASH
        ) {
            $this->imageResize->resizeFromImageName($this->getOriginalImage($fileName));
            if (!$this->pubDirectory->isReadable($fileName)) {
                $synchronizer->synchronize($fileName);
            }
        }
    }

    private function createPlaceholderLocalCopy(?string $relativeFileName): void
    {
        $synchronizer = $this->syncFactory->create(['directory' => $this->mediaDirectory]);
        $synchronizer->synchronize($relativeFileName);
    }

    private function setPlaceholderImage(): void
    {
        $placeholder = $this->placeholderFactory->create(['type' => 'image']);
        $this->createPlaceholderLocalCopy($placeholder->getRelativePath());
        $this->response->setFilePath($placeholder->getPath());
    }

    /**
     * Find the path to the original image of the cache path
     */
    private function getOriginalImage(string $resizedImagePath): string
    {
        return preg_replace('|^.*((?:/[^/]+){3})$|', '$1', $resizedImagePath);
    }
}
