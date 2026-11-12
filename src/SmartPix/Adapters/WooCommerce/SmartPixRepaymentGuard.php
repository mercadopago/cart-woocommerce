<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce;

use Exception;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A rejected Smart Pix order must not be offered again for payment: WooCommerce would otherwise route
 * the buyer from its "Pay" links to Order Pay, which offers conventional Pix after the rejection.
 * A Smart Pix order whose linked Order is still processing is not payable either: paying it with
 * another method ends the attempt, so a later settlement of that Order would go unrecorded. Its "Pay"
 * links lead to the order-received page, which shows the pending Smart Pix status instead.
 * Because such an order does not need payment, a Classic checkout resubmission must not resume it:
 * WooCommerce would complete a resumed order that needs no payment without querying Orders.
 * A creation whose response was lost links no Order and is not blocked here: without a linked Order,
 * no local path could release the order again. Its durable creation admission still refuses another
 * Smart Pix creation, and a notification may adopt the Order later. Paying it with another method
 * ends the attempt first (AbstractGateway::process_payment), so that payment owns the order.
 */
final class SmartPixRepaymentGuard
{
    private const REJECTED_STATUS = 'failed';

    private const PROCESSING_STATUSES = ['pending', 'on-hold'];

    private OrderMetadata $metadata;

    public function __construct(OrderMetadata $metadata)
    {
        $this->metadata = $metadata;
    }

    public static function register(OrderMetadata $metadata): void
    {
        $guard = new self($metadata);

        add_filter('woocommerce_valid_order_statuses_for_payment', [$guard, 'filterValidStatusesForPayment'], 20, 2);
        add_filter('woocommerce_order_needs_payment', [$guard, 'filterNeedsPayment'], 20, 2);
        add_filter('woocommerce_get_checkout_payment_url', [$guard, 'filterCheckoutPaymentUrl'], 20, 2);
        add_action('woocommerce_before_checkout_process', [$guard, 'releaseRejectedOrderFromSession'], 20, 0);
        add_action('woocommerce_resume_order', [$guard, 'stopProcessingOrderResume'], 20, 1);
    }

    /**
     * Classic checkout also resumes a failed session order, and a rejected Smart Pix order needs no
     * payment, so resuming it would complete it unpaid. Releasing it from the session before the order
     * is created makes WooCommerce create a new order, while the rejected one stays failed.
     */
    public function releaseRejectedOrderFromSession(): void
    {
        if (!function_exists('WC')) {
            return;
        }
        /** @var \WC_Session|null $session */
        $session = WC()->session;
        if ($session === null) {
            return;
        }
        $orderId = $session->get('order_awaiting_payment');
        if (!is_numeric($orderId) || (int) $orderId <= 0) {
            return;
        }
        if ($this->isRejectedSmartPixOrder(wc_get_order((int) $orderId))) {
            $session->set('order_awaiting_payment', false);
        }
    }

    /**
     * Classic checkout resumes the session's pending order and calls payment_complete() when it does
     * not need payment. The exception stops the checkout before WooCommerce removes the order items
     * or decides on payment, so the order stays pending until Orders settles it.
     *
     * @param mixed $orderId
     * @throws Exception
     */
    public function stopProcessingOrderResume($orderId): void
    {
        $order = is_numeric($orderId) && (int) $orderId > 0 ? wc_get_order((int) $orderId) : null;
        if (!$this->isProcessingSmartPixOrder($order)) {
            return;
        }

        throw new Exception(__(
            'We are processing your Pix via Open Finance payment. Your order remains pending until the payment is confirmed.',
            'woocommerce-mercadopago'
        ));
    }

    /**
     * @param mixed $statuses
     * @param mixed $order
     * @return mixed
     */
    public function filterValidStatusesForPayment($statuses, $order = null)
    {
        if (!is_array($statuses)) {
            return $statuses;
        }
        if ($this->isRejectedSmartPixOrder($order)) {
            return array_values(array_diff($statuses, [self::REJECTED_STATUS]));
        }
        if ($this->isProcessingSmartPixOrder($order)) {
            return array_values(array_diff($statuses, self::PROCESSING_STATUSES));
        }

        return $statuses;
    }

    /**
     * @param mixed $needsPayment
     * @param mixed $order
     * @return mixed
     */
    public function filterNeedsPayment($needsPayment, $order = null)
    {
        return $this->isBlockedSmartPixOrder($order) ? false : $needsPayment;
    }

    /**
     * The failed thank-you template prints its "Pay" button unconditionally, so the URL itself must stop
     * pointing at Order Pay.
     *
     * @param mixed $url
     * @param mixed $order
     * @return mixed
     */
    public function filterCheckoutPaymentUrl($url, $order = null)
    {
        return $this->isBlockedSmartPixOrder($order) ? $order->get_checkout_order_received_url() : $url;
    }

    /**
     * Unpaid Smart Pix order whose linked Order may still settle; its terminal notification makes it
     * paid, failed or cancelled, which ends this block.
     *
     * @param mixed $order
     */
    public function isProcessingSmartPixOrder($order): bool
    {
        if (
            !$order instanceof WC_Order
            || $order->get_payment_method() !== PixGateway::ID
            || !in_array($order->get_status(), self::PROCESSING_STATUSES, true)
            || $order->is_paid()
            || $this->metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
        ) {
            return false;
        }

        $reference = $this->metadata->getPpOrderId($order);

        return is_string($reference) && $reference !== '';
    }

    /** @param mixed $order */
    private function isBlockedSmartPixOrder($order): bool
    {
        return $this->isRejectedSmartPixOrder($order) || $this->isProcessingSmartPixOrder($order);
    }

    /**
     * @param mixed $order
     */
    public function isRejectedSmartPixOrder($order): bool
    {
        if (
            !$order instanceof WC_Order
            || $order->get_payment_method() !== PixGateway::ID
            || $order->get_status() !== self::REJECTED_STATUS
            || $this->metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
        ) {
            return false;
        }

        $reference = $this->metadata->getPpOrderId($order);

        return is_string($reference) && $reference !== '';
    }
}
