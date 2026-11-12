<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce;

use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore;

if (!defined('ABSPATH')) {
    exit;
}

/** Recover only a confirmed Order in its server-bound checkout session. */
final class SmartPixCheckoutRecovery
{
    public const SESSION_KEY = 'mp_smart_pix_creation_order';

    private OrderMetadata $metadata;
    public function __construct(OrderMetadata $metadata)
    {
        $this->metadata = $metadata;
    }

    /** Called only after durable creation admission and before the outbound POST. */
    public static function bindSession(WC_Order $order): void
    {
        /** @var \WC_Session_Handler|null $session */
        $session = WC()->session;
        if ($session === null) {
            throw new \RuntimeException('creation_session_unavailable');
        }
        $session->set(self::SESSION_KEY, $order->get_id());
        $session->set('order_awaiting_payment', $order->get_id());
        // The reload runs concurrently; do not wait until shutdown to publish the binding.
        $session->save_data();
    }

    /** Only call after native order-page/status authorization and a confirmed PPORD link. */
    public static function acknowledge(WC_Order $order): void
    {
        if (!function_exists('WC')) {
            return;
        }
        /** @var object|null $woocommerce */
        $woocommerce = WC();
        /** @var \WC_Session|null $session */
        $session = $woocommerce->session ?? null;
        if ($session !== null && (int) $session->get(self::SESSION_KEY) === $order->get_id()) {
            $session->__unset(self::SESSION_KEY);
        }
    }

    public function redirectCheckout(): void
    {
        if (wp_doing_ajax() || !is_checkout() || is_wc_endpoint_url()) {
            return;
        }
        /** @var \WC_Session_Handler|null $session */
        $session = WC()->session;
        $order = $session === null ? null : $this->recover($session, (int) get_current_user_id());
        if ($order === null) {
            return;
        }
        wp_safe_redirect($order->get_checkout_order_received_url());
        exit;
    }

    /** @param object $session WooCommerce's authenticated, server-owned session. */
    public function recover($session, int $userId): ?WC_Order
    {
        $orderId = $session->get(self::SESSION_KEY);
        if ((!is_int($orderId) && !is_string($orderId)) || !ctype_digit((string) $orderId) || (int) $orderId <= 0) {
            return null;
        }
        $order = wc_get_order((int) $orderId);
        if (!$order instanceof WC_Order || $order->get_payment_method() !== PixGateway::ID
            || $this->metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            || ((int) $order->get_customer_id() !== 0 && (int) $order->get_customer_id() !== $userId)) {
            return null;
        }
        if (!in_array($order->get_status(), ['pending', 'on-hold'], true)) {
            $session->__unset(self::SESSION_KEY);
            return null;
        }
        if (!SmartPixCreationStore::hasStarted($order)) {
            return null;
        }
        $reference = $this->metadata->getPpOrderId($order);
        if (!is_string($reference) || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $reference) !== 1) {
            $session->__unset(self::SESSION_KEY);
            return null;
        }
        return $order;
    }
}
