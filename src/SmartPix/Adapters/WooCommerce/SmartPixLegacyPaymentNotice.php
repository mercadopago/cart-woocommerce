<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce;

use Throwable;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixCoreMonitorMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Records an approved Payments payment that arrived for an order holding the Smart Pix contract.
 *
 * Legacy notifications and the conventional Pix polling never change such an order, so a payment
 * made with another method (for example a conventional Pix QR paid after the buyer moved to Smart
 * Pix) would otherwise leave no trace. This adds one private order note per payment id and one
 * metric, never changes the order status and never throws: the caller's answer stays unchanged.
 * Only digit-only payment ids are written; no buyer data, amount or provider payload is recorded.
 */
final class SmartPixLegacyPaymentNotice
{
    private const NOTED_META = '_mp_smart_pix_legacy_payments_noted';

    private SmartPixTelemetry $telemetry;

    public function __construct(?SmartPixTelemetry $telemetry = null)
    {
        $this->telemetry = $telemetry ?? new SmartPixTelemetry();
    }

    /** Builds with the plugin metric sink; without a loaded plugin the notice still records the note. */
    public static function fromPlugin(): self
    {
        global $mercadopago;

        try {
            return new self(new SmartPixTelemetry(SmartPixCoreMonitorMetricSink::fromPlugin($mercadopago)));
        } catch (Throwable $exception) {
            return new self();
        }
    }

    /**
     * @param mixed $data Legacy payment, merchant order or Core notification payload already fetched
     *     from Mercado Pago by the caller.
     * @param string $source 'notification' for legacy notifications, 'pending_query' for the polling.
     */
    public function record(WC_Order $order, $data, string $source): void
    {
        try {
            $approved = $this->approvedPaymentIds($data);
            if ($approved === []) {
                return;
            }
            $noted = $this->notedPaymentIds($order);
            foreach ($approved as $paymentId) {
                if (in_array($paymentId, $noted, true)) {
                    continue;
                }
                $order->add_order_note(sprintf(
                    /* translators: %s: Mercado Pago payment id */
                    __(
                        'Mercado Pago: payment %s was approved on an order with a Smart Pix payment. The order status was not changed; check whether a refund is needed.',
                        'woocommerce-mercadopago'
                    ),
                    $paymentId
                ));
                $noted[] = $paymentId;
                $order->update_meta_data(self::NOTED_META, implode(',', $noted));
                $order->save();
                $this->telemetry->legacyPaymentRecorded($source);
            }
        } catch (Throwable $exception) {
            // Recording is informational; a failure never changes the notification answer.
        }
    }

    /** @return array<int, string> */
    private function notedPaymentIds(WC_Order $order): array
    {
        $stored = $order->get_meta(self::NOTED_META, true);

        return is_string($stored) && $stored !== '' ? explode(',', $stored) : [];
    }

    /**
     * Approved payments of a payment (Webhook, polling), a merchant order (IPN) or a Core
     * notification (`payments_details`).
     *
     * @param mixed $data
     * @return array<int, string>
     */
    private function approvedPaymentIds($data): array
    {
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['payments_details']) && is_array($data['payments_details'])) {
            $payments = $data['payments_details'];
        } elseif (isset($data['payments']) && is_array($data['payments'])) {
            $payments = $data['payments'];
        } else {
            $payments = [$data];
        }

        $ids = [];
        foreach ($payments as $payment) {
            if (!is_array($payment) || ($payment['status'] ?? null) !== 'approved') {
                continue;
            }
            $id = $payment['id'] ?? null;
            $id = is_int($id) ? (string) $id : (is_string($id) ? $id : '');
            if (ctype_digit($id) && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
