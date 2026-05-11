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
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\State;
use Magento\Framework\AppInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\ObjectManagerInterface;
use Magento\MediaStorage\App\Media as MagentoMedia;
use Psr\Log\LoggerInterface;
use ReflectionObject;

use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function preg_replace;
use function str_replace;
use function stripos;
use function trim;

/**
 * Worker-mode entry point for pub/get.php (Magento media on-demand processing).
 *
 * Sibling to {@see Http} and {@see StaticResource}: the FrankenPHP worker loop
 * dispatches to this through {@see \Opengento\Application\ObjectManager\BootstrapPool},
 * reusing the hot ObjectManager + DI graph instead of paying a full Magento
 * bootstrap on every missing-media fallback request. Per request, the actual
 * image materialization is delegated to Magento's stock
 * {@see \Magento\MediaStorage\App\Media}, instantiated via the shared
 * ObjectManager with request-derived constructor arguments.
 *
 * Area-code save/restore note
 * ---------------------------
 * Magento\MediaStorage\App\Media::launch() unconditionally calls
 * State::setAreaCode(Area::AREA_GLOBAL). In worker mode the State singleton
 * persists across requests, and State::setAreaCode() throws "Area code is
 * already set" on the second call. State does not implement
 * ResetAfterRequestInterface and is not auto-tracked by AppObjectManager's
 * Resetter (it's a singleton resolved via get(), not create()), so the
 * Resetter never clears it between requests.
 *
 * The workaround is contained to this class: at the start of launch() we
 * snapshot the current area code (via reflection — State exposes no public
 * read of the raw field bypassing isAreaCodeEmulated logic), set it to null
 * so MagentoMedia's setAreaCode(GLOBAL) succeeds, run the media handler,
 * then restore the original value in a finally{} block. This way the
 * surrounding {@see Http} request flow on the same worker isn't affected
 * by media requests (no area cross-contamination).
 *
 * If Magento eventually implements ResetAfterRequestInterface on State (or
 * exposes a public reset/clear API), the reflection here can be removed.
 */
class Media implements AppInterface
{
    private const CONFIG_CACHE_FILENAME = 'resource_config.json';

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly HttpRequest $request,
        private readonly HttpResponse $response,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
        private readonly State $state,
    ) {}

    /**
     * @inheritDoc
     * @throws Exception
     */
    #[\Override]
    public function launch(): ResponseInterface
    {
        $savedAreaCode = $this->snapshotAreaCode();
        $this->writeAreaCode(null);

        try {
            $configCacheFile = $this->filesystem
                ->getDirectoryRead(DirectoryList::VAR_DIR)
                ->getAbsolutePath(self::CONFIG_CACHE_FILENAME);
            $mediaDirectory = $this->readCachedMediaDirectory($configCacheFile);
            $relativePath = $this->extractRelativePath((string) $this->request->getRequestUri());

            $media = $this->createMagentoMedia(
                mediaDirectory: $mediaDirectory,
                configCacheFile: $configCacheFile,
                relativeFileName: $relativePath,
            );

            return $media->launch();
        } finally {
            // Always restore the area code so concurrent /index.php or /static.php
            // requests serviced by the same worker pool see the area BootstrapPool
            // set up for them, not the GLOBAL Magento Media transiently selects.
            $this->writeAreaCode($savedAreaCode);
        }
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function catchException(Bootstrap $bootstrap, Exception $exception): bool
    {
        $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
        $this->response->setHttpResponseCode(404);
        $this->response->sendResponse();

        return true;
    }

    /**
     * Construct the stock Magento media app with the request-derived arguments.
     *
     * The {@see $isAllowed} closure's two-argument signature matches the call
     * site inside Magento\MediaStorage\App\Media::launch():
     *   `$isAllowed($fileRelativePath, $allowedResources)`
     */
    private function createMagentoMedia(
        ?string $mediaDirectory,
        string $configCacheFile,
        string $relativeFileName,
    ): MagentoMedia {
        $isAllowed = static function (string $resource, array $allowedResources): bool {
            foreach ($allowedResources as $allowed) {
                if (0 === stripos($resource, (string) $allowed)) {
                    return true;
                }
            }
            return false;
        };

        /** @var MagentoMedia $media */
        $media = $this->objectManager->create(MagentoMedia::class, [
            'mediaDirectory' => $mediaDirectory,
            'configCacheFile' => $configCacheFile,
            'isAllowed' => $isAllowed,
            'relativeFileName' => $relativeFileName,
        ]);

        return $media;
    }

    /**
     * Read the cached media_directory value from var/resource_config.json.
     * Returns null if absent or malformed — Magento\MediaStorage\App\Media
     * tolerates a null mediaDirectory and triggers a config rebuild itself.
     */
    private function readCachedMediaDirectory(string $configCacheFile): ?string
    {
        if (!file_exists($configCacheFile)) {
            return null;
        }
        $cached = json_decode((string) file_get_contents($configCacheFile), true);
        if (!is_array($cached)) {
            return null;
        }
        $mediaDirectory = (string) ($cached['media_directory'] ?? '');

        return $mediaDirectory !== '' ? $mediaDirectory : null;
    }

    /**
     * Mirror stock pub/get.php's relative-path derivation: strip `../`
     * traversal attempts and prune query strings, except for /static/
     * subpaths that legitimately carry version/sourcemap identifiers.
     */
    private function extractRelativePath(string $requestUri): string
    {
        $relativePath = str_replace('../', '', $requestUri);
        if (false === stripos($relativePath, '/static/version')
            && false === stripos($relativePath, '/static/sourcemaps')
            && false === stripos($relativePath, '/static/_cache/merged')
        ) {
            $relativePath = (string) preg_replace('/\?.*/', '', $relativePath);
        }

        return trim($relativePath, '/');
    }

    /**
     * Read the current raw `_areaCode` value via reflection. Returns null if
     * never set. We bypass {@see State::getAreaCode()} because that throws
     * a LocalizedException when the code is unset — undesirable for a snapshot
     * that needs to support the "first request after worker boot" case.
     */
    private function snapshotAreaCode(): ?string
    {
        $ref = new ReflectionObject($this->state);
        if (!$ref->hasProperty('_areaCode')) {
            return null;
        }
        $prop = $ref->getProperty('_areaCode');
        $prop->setAccessible(true);

        /** @var ?string $value */
        $value = $prop->getValue($this->state);

        return $value;
    }

    /**
     * Write the raw `_areaCode` value via reflection. Passing null clears it
     * so a subsequent setAreaCode() call succeeds. Defensive against framework
     * field renames: silently no-op if the property is missing.
     */
    private function writeAreaCode(?string $value): void
    {
        $ref = new ReflectionObject($this->state);
        if ($ref->hasProperty('_areaCode')) {
            $prop = $ref->getProperty('_areaCode');
            $prop->setAccessible(true);
            $prop->setValue($this->state, $value);
        }
        // Also reset the emulation flag — Media doesn't go through
        // emulateAreaCode(), so this flag should always be false in its scope.
        if ($ref->hasProperty('_isAreaCodeEmulated')) {
            $prop = $ref->getProperty('_isAreaCodeEmulated');
            $prop->setAccessible(true);
            $prop->setValue($this->state, false);
        }
    }
}
