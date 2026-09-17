<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Plugin\Checkout\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DB\Adapter\LockWaitException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Covers all callers of CheckoutSession::getQuote() for LockWaitException in FrankenPHP worker mode.
 *
 * Multiple workers may concurrently call getQuote() for the same customer. When a currency-code mismatch
 * triggers quoteRepository->save() → quote_address INSERT, the losing worker gets MySQL error 1205 (lock
 * wait timeout). At the point of the exception isLoading=true and _quote=null — a plain retry would throw
 * LogicException("Infinite loop"). _resetState() resets those flags without touching session data
 * (quote_id preserved) so the retry succeeds.
 *
 * NOTE: Checkout-coupled worker-mode guard (ported from MageOS_WorkerMode "Group B") — long-term home is
 * a downstream Magento-scoped module rather than this generic bootstrap package.
 */
class CheckoutSessionPlugin
{
    /**
     * @param CheckoutSession $subject
     * @param callable $proceed
     * @return CartInterface
     * @throws LockWaitException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetQuote(CheckoutSession $subject, callable $proceed): CartInterface
    {
        // StartSessionOnAccess starts the checkout session on first access (getQuote() reads getQuoteId(),
        // a magic __call), so no explicit start() is needed here — this plugin only handles LockWait.
        try {
            return $proceed();
        } catch (LockWaitException $e) {
            $subject->_resetState();
            try {
                return $proceed();
            } catch (LockWaitException) {
                throw $e;
            }
        }
    }
}
