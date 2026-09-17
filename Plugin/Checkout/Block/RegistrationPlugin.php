<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Plugin\Checkout\Block;

use Magento\Checkout\Block\Registration;
use Magento\Framework\Exception\InputException;

/**
 * Guards the Registration block against a missing last_order_id in the checkout session.
 *
 * In FrankenPHP worker mode the session may not yet carry last_order_id when the success page renders.
 * Registration::validateAddresses() calls OrderRepository::get(0), which throws
 * InputException("An ID is needed") rather than returning gracefully. This catches that exception and
 * returns an empty string so the success page renders normally without the optional "create account"
 * prompt.
 *
 * NOTE: Checkout-coupled worker-mode guard (ported from MageOS_WorkerMode "Group B"). Its long-term home
 * is a downstream Magento-scoped module rather than this generic bootstrap package.
 */
class RegistrationPlugin
{
    /**
     * @param Registration $subject
     * @param callable $proceed
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundToHtml(Registration $subject, callable $proceed): string
    {
        try {
            return $proceed();
        } catch (InputException $e) {
            return '';
        }
    }
}
