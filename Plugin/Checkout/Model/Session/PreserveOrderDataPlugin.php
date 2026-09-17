<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Plugin\Checkout\Model\Session;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Preserves order-completion session data across the depersonalize-triggered clearStorage() call.
 *
 * In FrankenPHP worker mode, stale Layout _xml can cause DepersonalizeChecker to treat the checkout
 * success page as cacheable, which triggers Checkout\DepersonalizePlugin::clearStorage() and wipes _data
 * (including last_real_order_id) before prepareBlockData() can read the order. The Layout reset now clears
 * _xml at the root, but this plugin is the safety net: on the success page it saves the order keys before
 * the clear and restores them immediately after so the print-order button always has a valid order_id.
 *
 * Scoped to checkout_onepage_success only so no other clearStorage() call site is affected.
 *
 * NOTE: Checkout-coupled worker-mode guard (ported from MageOS_WorkerMode "Group B") — long-term home is
 * a downstream Magento-scoped module rather than this generic bootstrap package.
 */
class PreserveOrderDataPlugin
{
    /** Keys that must survive a depersonalize-triggered clearStorage() on the success page. */
    private const PRESERVE_KEYS = ['last_real_order_id', 'last_order_id', 'last_order_status'];

    public function __construct(private readonly HttpRequest $request) {}

    /**
     * @param CheckoutSession $subject
     * @param callable $proceed
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundClearStorage(CheckoutSession $subject, callable $proceed): mixed
    {
        $savedData = [];
        if ($this->request->getFullActionName() === 'checkout_onepage_success') {
            foreach (self::PRESERVE_KEYS as $key) {
                $value = $subject->getData($key);
                if ($value !== null && $value !== '') {
                    $savedData[$key] = $value;
                }
            }
        }

        $result = $proceed();

        foreach ($savedData as $key => $value) {
            $subject->setData($key, $value);
        }

        return $result;
    }
}
