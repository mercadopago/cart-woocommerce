<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce;

use MercadoPago\Woocommerce\Order\OrderMetadata;
use Throwable;
use WC_Order;
use WC_Order_Refund;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;

if (!defined('ABSPATH')) {
    exit;
}

/** Request-local correlation captured before WooCommerce saves the native refund. */
final class SmartPixRefundContext
{
    /**
     * Saved with a refund_payment refund before its gateway call. WooCommerce deletes that refund
     * when the call fails and sets refunded_payment only after it succeeds, so a stamped refund
     * without refunded_payment may still disappear and must not count as settled.
     */
    public const IN_FLIGHT_META_KEY = '_mp_smart_pix_refund_in_flight';

    private ?WC_Order_Refund $refund = null;

    private ?OrderMetadata $metadata;

    public function __construct(?OrderMetadata $metadata = null)
    {
        $this->metadata = $metadata;
    }

    /**
     * Runs on woocommerce_create_refund, before wc_create_refund() saves the refund.
     * @param array<string, mixed> $args
     */
    public function capture(WC_Order_Refund $refund, array $args): void
    {
        $this->refund = !empty($args['refund_payment']) ? $refund : null;
        if ($this->refund !== null && $this->isSmartPixParent($refund)) {
            $refund->update_meta_data(self::IN_FLIGHT_META_KEY, '1');
        }
    }

    /** True while a stamped gateway refund has not been confirmed by WooCommerce. */
    public static function isInFlight(WC_Order_Refund $refund): bool
    {
        return $refund->get_meta(self::IN_FLIGHT_META_KEY, true, 'edit') === '1'
            && $refund->get_refunded_payment('edit') !== true;
    }

    private function isSmartPixParent(WC_Order_Refund $refund): bool
    {
        if ($this->metadata === null) {
            return false;
        }
        try {
            $order = wc_get_order($refund->get_parent_id('edit'));
            return $order instanceof WC_Order
                && $this->metadata->getPaymentContract($order) === OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1;
        } catch (Throwable $failure) {
            // An unreadable parent keeps the conservative stamp; it never blocks the native refund.
            return true;
        }
    }

    /** @return array{identity: string, amount: string}|null */
    public function resolve(WC_Order $order, float $amount): ?array
    {
        if (
            $this->refund === null || $this->refund->get_id() <= 0
            || $this->refund->get_parent_id('edit') !== $order->get_id()
            || $this->refund->get_currency('edit') !== $order->get_currency('edit')
        ) {
            return null;
        }
        // get_amount() casts to float; the native negative total retains the CRUD decimal string.
        $canonical = $this->refundAmount($this->refund->get_total('edit'));
        if ($canonical === null || $canonical === '0.00' || !is_finite($amount) || (float) $canonical !== $amount) {
            return null;
        }
        return ['identity' => 'refund_' . $this->refund->get_id(), 'amount' => $canonical];
    }

    /** @param mixed $total Stubs declare float, while native CRUD retains the decimal string. */
    private function refundAmount($total): ?string
    {
        return is_string($total) && substr($total, 0, 1) === '-'
            ? SmartPixMoney::normalize(substr($total, 1)) : null;
    }
}
