<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\App\Store;

use Magento\Framework\App\ScopeInterface;
use Magento\Store\Model\StoreResolver as MagentoStoreResolver;

/**
 * Worker-mode-aware variant of {@see MagentoStoreResolver}.
 *
 * Background
 * ----------
 * Magento's `\Magento\Store\Model\StoreResolver` receives its `$runMode` and
 * `$scopeCode` constructor arguments via DI `init_parameter` resolution:
 *
 *     <type name="Magento\Store\Model\StoreResolver">
 *         <arguments>
 *             <argument name="runMode" xsi:type="init_parameter">
 *                 Magento\Store\Model\StoreManager::PARAM_RUN_TYPE
 *             </argument>
 *             <argument name="scopeCode" xsi:type="init_parameter">
 *                 Magento\Store\Model\StoreManager::PARAM_RUN_CODE
 *             </argument>
 *         </arguments>
 *     </type>
 *
 * `init_parameter` reads `$_SERVER['MAGE_RUN_TYPE']` and `$_SERVER['MAGE_RUN_CODE']`
 * from the {@see \Magento\Framework\ObjectManagerFactory} `$globalArguments`
 * array — which is a SNAPSHOT of `$_SERVER` taken once when the ObjectManager
 * is constructed.
 *
 * Under classic PHP-FPM dispatch this is fine: `$_SERVER` reflects the current
 * request and the ObjectManager is built once per request. Under FrankenPHP
 * worker mode the ObjectManager is built ONCE per worker (per area) and
 * survives across many requests. The `StoreResolver` instance ends up with
 * `$scopeCode` and `$runMode` frozen at the worker's first request — every
 * subsequent request on that worker that sets `$_SERVER['MAGE_RUN_CODE']`
 * (e.g., via a `magento-vars.php` override driven by a `Store` HTTP header)
 * gets the wrong store group from `getStoresData()`, and dispatch falls back
 * to the default store of the boot-time scope.
 *
 * Concrete symptom on a multi-store setup: a customer hitting `/de/` (or sending
 * `Store: store_de`) on a worker that booted under `MAGE_RUN_CODE=base` gets
 * the `base` store's currency, locale, catalog rules, and prices — regardless
 * of what the request header says.
 *
 * Fix
 * ---
 * Override `getCurrentStoreId()` to refresh `$scopeCode` / `$runMode` from
 * the current request's `$_SERVER` before delegating to the parent. The parent
 * then resolves the right store list via `StoresData->getStoresData(...)` and
 * the right cookie / URL path / query parameter against that list. No state
 * is cached at the subclass level — the parent's per-request resolution path
 * is preserved unchanged.
 *
 * Why override `getCurrentStoreId()` and not implement `ResetAfterRequestInterface`?
 * ---------------------------------------------------------------------------
 * `ResetAfterRequestInterface::_resetState()` is called by the framework
 * BETWEEN requests, after request N has completed and before request N+1
 * has been read off the wire. At that moment `$_SERVER` still holds request
 * N's values — re-reading them resets to the previous request's scope,
 * not the next one. Overriding the dispatch entry point means the read
 * happens AFTER FrankenPHP has populated `$_SERVER` for the current request
 * and AFTER any `magento-vars.php`-style per-request override has run.
 *
 * Compatibility
 * -------------
 * `Magento\Store\Model\StoreResolver::$runMode` and `::$scopeCode` are
 * `protected` (declared at the parent level since Magento 2.2.x and stable
 * across the 2.4.x line — verified against 2.4.6 through 2.4.9-beta1). The
 * constructor signature and `getStoresData()` contract are unchanged in
 * 2.4.7…2.4.9.
 *
 * Wired in via `etc/di.xml` preference so DI consumers of
 * `Magento\Store\Model\StoreResolver` receive our subclass.
 */
class StoreResolver extends MagentoStoreResolver
{
    /**
     * @inheritDoc
     */
    public function getCurrentStoreId()
    {
        $this->syncRunCodeFromServer();

        return parent::getCurrentStoreId();
    }

    /**
     * Mirror the parent constructor's `$runMode = $scopeCode ? $runMode : SCOPE_WEBSITE`
     * logic, sourcing values from the current request's `$_SERVER` rather than
     * the worker-boot snapshot consumed by `init_parameter` DI resolution.
     *
     * Idempotent: if neither `MAGE_RUN_CODE` nor `MAGE_RUN_TYPE` has changed
     * since the last call, both assignments are no-ops. Cheap enough to run on
     * every call (one isset() + one ternary + two property writes).
     */
    private function syncRunCodeFromServer(): void
    {
        $scopeCode = $_SERVER['MAGE_RUN_CODE'] ?? null;
        $runMode = $scopeCode
            ? ($_SERVER['MAGE_RUN_TYPE'] ?? ScopeInterface::SCOPE_STORE)
            : ScopeInterface::SCOPE_WEBSITE;

        $this->scopeCode = $scopeCode;
        $this->runMode = $runMode;
    }
}
