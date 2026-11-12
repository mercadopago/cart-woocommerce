<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

if (!defined('ABSPATH')) {
    // @codeCoverageIgnoreStart
    exit;
    // @codeCoverageIgnoreEnd
}

/**
 * Stores may choose their own paid status (`woocommerce_payment_complete_order_status` plus
 * `woocommerce_order_is_paid_statuses`). WooCommerce `is_paid()` is the paid fact; a native
 * unpaid lifecycle status still contradicts it, so both facts must agree.
 */
final class SmartPixPaidStatus
{
    private const NATIVE_UNPAID_STATUSES = ['pending', 'on-hold', 'failed', 'cancelled', 'refunded'];

    public static function isPaid(string $status, bool $isPaid): bool
    {
        return $isPaid && !in_array($status, self::NATIVE_UNPAID_STATUSES, true);
    }
}
