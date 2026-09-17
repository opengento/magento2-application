<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Model\Reset;

use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Context as WidgetContext;
use Magento\Framework\App\Area;
use Magento\Framework\App\State\ReloadProcessorInterface;
use Magento\Framework\ObjectManagerInterface;

/**
 * Surgical between-request resets that etc/reset.json cannot express, because they target a single
 * sub-key of an array property or a property on a NESTED object rather than a top-level property.
 *
 *  - Magento\Framework\App\Area::_loadedParts['design'] — cleared so load(PART_DESIGN) re-runs
 *    _initDesign() and restores the area/theme. Design::_area/_theme are reset via reset.json, but they
 *    are never repopulated unless _initDesign() runs again; without this the storefront renders with the
 *    previous request's area CSS (or '_view' asset URLs / a blank body) on a warm worker.
 *  - Magento\Backend\Block\Widget\Context -> ButtonList::_buttons — cleared so admin button lists do not
 *    accumulate/duplicate across warm requests.
 *
 * Each target is force-initialised first (a PHP 8.4 lazy ghost cannot be reflection-written while
 * uninitialised — see Opengento\Application\ObjectManager\Resetter\Resetter). Registered as a
 * ReloadProcessorComposite item, so AppBootstrap's reloadState() runs it between requests.
 */
class StateReset implements ReloadProcessorInterface
{
    public function __construct(private readonly ObjectManagerInterface $objectManager)
    {
    }

    /**
     * @return void
     */
    public function reloadState(): void
    {
        // Area: clear only the 'design' loaded part so _initDesign() re-runs next request.
        try {
            $area = $this->objectManager->get(Area::class);
            $this->initializeGhost($area);
            $property = new \ReflectionProperty(Area::class, '_loadedParts');
            $parts = $property->getValue($area);
            if (is_array($parts)) {
                unset($parts['design']);
                $property->setValue($area, $parts);
            }
        } catch (\Throwable) {
            // Best effort — one target's failure must not abort the reset cycle.
        }

        // Admin Widget\Context: clear the ButtonList so admin buttons do not accumulate across requests.
        // Admin-only; on the storefront the get()/reset is harmless (the catch swallows any Throwable).
        try {
            $context = $this->objectManager->get(WidgetContext::class);
            $this->initializeGhost($context);
            $buttonList = (new \ReflectionProperty(WidgetContext::class, 'buttonList'))->getValue($context);
            if ($buttonList !== null) {
                (new \ReflectionProperty(ButtonList::class, '_buttons'))
                    ->setValue($buttonList, [-1 => [], 0 => [], 1 => []]);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Initialize a PHP 8.4 lazy-ghost Interceptor so a subsequent reflection write lands in the live slot.
     *
     * @param object $instance
     * @return void
     */
    private function initializeGhost(object $instance): void
    {
        $reflection = new \ReflectionClass($instance);
        if (method_exists($reflection, 'isUninitializedLazyObject')
            && $reflection->isUninitializedLazyObject($instance)
        ) {
            $reflection->initializeLazyObject($instance);
        }
    }
}
