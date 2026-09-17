<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\ObjectManager\Resetter;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\ObjectManager\Resetter\Resetter as FrameworkResetter;
use ReflectionClass;
use WeakMap;

/**
 * Resetter that fixes silent reset failures for PHP 8.4 lazy-ghost Interceptors.
 *
 * The framework Resetter resets classes listed in reset.json that do NOT implement
 * ResetAfterRequestInterface with ReflectionProperty::setValue(). On PHP 8.4 the DI container creates
 * Interceptors as lazy ghosts; writing to an UNINITIALIZED ghost lands in the parent scope, and the
 * ghost's later lazy initializer overwrites the value — so the reset silently fails and stale state
 * (e.g. Design::_area, Page\Config::elements) leaks into the next request on a persistent worker.
 *
 * The framework's reflection-reset method is private, so this subclass fixes the behaviour at the two
 * public seams: it remembers the reflection-reset targets as they are created ({@see addInstance()})
 * and, at reset time ({@see _resetState()}), force-initializes any that are still uninitialized lazy
 * ghosts BEFORE delegating to the parent reset — so the parent's setValue() writes into the live
 * property slot. Instances reset via a `_resetState()` method are untouched: that path already
 * initializes the ghost by calling the method.
 *
 * This mirrors the correct fix (initialize the ghost before reflecting). It lives in this module because
 * the Resetter is built by a static factory before DI exists, so it cannot be replaced by a preference.
 */
class Resetter extends FrameworkResetter
{
    private const RESET_STATE_METHOD = '_resetState';

    /** @var WeakMap<object, bool> Instances that will be reset by reflection (candidate lazy ghosts). */
    private WeakMap $reflectionTargets;

    public function __construct(
        ?ComponentRegistrarInterface $componentRegistrar = null,
        array $classList = [],
    ) {
        parent::__construct($componentRegistrar, $classList);
        $this->reflectionTargets = new WeakMap();
    }

    /**
     * @inheritDoc
     */
    public function addInstance(object $instance): void
    {
        parent::addInstance($instance);
        // Only instances reset by reflection can hit the lazy-ghost failure. Those reset via a
        // _resetState() method (ResetAfterRequestInterface or an ad-hoc method) already initialize the
        // ghost by virtue of the method call, so they never need this treatment.
        if (!$instance instanceof ResetAfterRequestInterface
            && !method_exists($instance, self::RESET_STATE_METHOD)
            && $this->isObjectInClassList($instance)
        ) {
            $this->reflectionTargets[$instance] = true;
        }
    }

    /**
     * @inheritDoc
     * @throws \ReflectionException
     */
    public function _resetState(): void
    {
        foreach ($this->reflectionTargets as $instance => $tracked) {
            $reflection = new ReflectionClass($instance);
            if (method_exists($reflection, 'isUninitializedLazyObject')
                && $reflection->isUninitializedLazyObject($instance)
            ) {
                $reflection->initializeLazyObject($instance);
            }
        }

        parent::_resetState();
    }
}
