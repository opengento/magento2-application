<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Plugin\Checkout\Model;

use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DB\Adapter\LockWaitException;

/**
 * Retries DefaultConfigProvider::getConfig() once after a LockWaitException in multi-worker setups.
 *
 * Concurrent workers race to INSERT into quote_address for the same quote_id; the loser gets MySQL 1205.
 * After the timeout the competing transaction has committed; at that point CheckoutSession::$isLoading is
 * true and $_quote is null, so a direct retry would throw LogicException. _resetState() resets both flags
 * without touching session data (quote_id is preserved), so the retry re-runs getQuote() cleanly.
 *
 * Broader net than CheckoutSessionPlugin (which wraps only getQuote()): this also catches LockWaits from
 * other DB writes inside getConfig(), e.g. totals collection.
 *
 * The former empty-customer-id repair (NoSuchEntityException catch) was removed: it is unreachable now
 * that StartSessionOnAccess::afterGetCustomerId() repairs the session at the SessionManager level,
 * upstream of getConfig() — validated live (cart test passed with only the SessionManager repair).
 *
 * NOTE: Checkout-coupled worker-mode guard (ported from MageOS_WorkerMode "Group B") — long-term home is
 * a downstream Magento-scoped module rather than this generic bootstrap package.
 */
class DefaultConfigProviderPlugin
{
    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {}

    /**
     * @param DefaultConfigProvider $subject
     * @param callable $proceed
     * @return array
     * @throws LockWaitException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetConfig(DefaultConfigProvider $subject, callable $proceed): array
    {
        try {
            return $proceed();
        } catch (LockWaitException $lockException) {
            $this->checkoutSession->_resetState();
            try {
                return $proceed();
            } catch (LockWaitException) {
                throw $lockException;
            }
        }
    }
}
