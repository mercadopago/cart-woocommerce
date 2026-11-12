<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Notification;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves a Smart Pix notification to its persisted WooCommerce attempt without effects.
 *
 * Only the attempt reference ("<store prefix><order id>") and the Orders reference are
 * accepted as input; the attempt key and persisted reference are read from OrderMetadata.
 * This class never logs identifiers, keys or payloads and emits no telemetry.
 *
 * Return shape (closed):
 * - linked:   ['linked' => true, 'category' => null, 'order' => WC_Order,
 *              'attempt_key' => string, 'order_reference' => string,
 *              'provider_reference_missing' => bool]
 * - pending:  ['linked' => false, 'category' => 'pending_linkage', 'order' => WC_Order,
 *              'attempt_key' => string, 'order_reference' => null] for an unpaid attempt whose
 *              Orders reference was not persisted yet; the caller may adopt() a confirmed one
 * - superseded: ['linked' => false, 'category' => 'superseded', 'order' => WC_Order,
 *              'attempt_key' => string, 'order_reference' => null] for an admitted create that
 *              never answered on an order the buyer then paid with another method; the caller
 *              may only review() a confirmed paid Order, never apply it
 * - rejected: ['linked' => false, 'category' => 'invalid_linkage', 'order' => null,
 *              'attempt_key' => null, 'order_reference' => null]
 *
 * Storage failures while loading the order propagate to the caller unchanged.
 */
final class SmartPixNotificationLinkage
{
    public const CATEGORY_INVALID_LINKAGE = 'invalid_linkage';

    public const CATEGORY_PENDING_LINKAGE = 'pending_linkage';

    public const CATEGORY_SUPERSEDED = 'superseded';

    /** Lease of the late superseded Order review, distinct from the status and terminal leases. */
    public const REVIEW_LOCK_NAME_PREFIX = 'mp_smart_pix_superseded_review_lock_';

    private OrderMetadata $metadata;

    private Seller $seller;

    private ?SmartPixStatusLock $reviewLock;

    public function __construct(OrderMetadata $metadata, Seller $seller, ?SmartPixStatusLock $reviewLock = null)
    {
        $this->metadata = $metadata;
        $this->seller = $seller;
        $this->reviewLock = $reviewLock;
    }

    /**
     * @return array{linked: bool, category: string|null, order: WC_Order|null,
     *     attempt_key: string|null, order_reference: string|null, provider_reference_missing?: bool}
     */
    public function resolve(string $attemptReference, string $orderReference, ?string $providerOrderReference = null): array
    {
        $orderId = $this->orderId($attemptReference);
        if ($orderId === null || !$this->isReference($orderReference)) {
            return $this->rejected();
        }

        $order = $this->load($orderId);
        if ($order === null) {
            return $this->rejected();
        }
        if (!$this->matchesContext($order)) {
            return $this->superseded($order, $attemptReference) ?? $this->rejected();
        }

        $key = $this->metadata->getIdempotencyKey($order);
        $reference = $this->metadata->getPpOrderId($order);
        if (!is_string($key) || $key === '') {
            return $this->rejected();
        }
        if (($reference === '' || $reference === null) && !$order->is_paid()) {
            // The Order may exist before the checkout persists it; only a confirmed read may adopt it.
            return [
                'linked' => false,
                'category' => self::CATEGORY_PENDING_LINKAGE,
                'order' => $order,
                'attempt_key' => $key,
                'order_reference' => null,
            ];
        }
        $storedProvider = $providerOrderReference === null ? null : $this->metadata->getMpOrderId($order);
        if (
            !$this->isReference($reference)
            || $reference !== $orderReference
            || ($providerOrderReference !== null && (
                !$this->isReference($providerOrderReference)
                || ($storedProvider !== '' && $storedProvider !== null && $storedProvider !== $providerOrderReference)
            ))
        ) {
            return $this->rejected();
        }

        return [
            'linked' => true,
            'category' => null,
            'order' => $order,
            'attempt_key' => $key,
            'order_reference' => $reference,
            // A creation answer without valid references linked only the PPORD; the caller completes
            // them from the authenticated read before applying anything.
            'provider_reference_missing' => $providerOrderReference !== null
                && ($storedProvider === '' || $storedProvider === null),
        ];
    }

    /**
     * Persist the provider references of a linked Order whose creation answer did not carry them,
     * only when the authenticated read names the same provider Order as the notification.
     * @param array<string, mixed> $response
     */
    public function completeReferences(WC_Order $order, string $orderReference, string $providerOrderReference, array $response): bool
    {
        $paymentReference = $response['observed']['payment_id'] ?? null;
        if (
            ($response['order_reference'] ?? null) !== $orderReference
            || ($response['observed']['payment_reference'] ?? null) !== $providerOrderReference
            || !$this->isReference($paymentReference)
            || !$this->isReference($providerOrderReference)
        ) {
            return false;
        }
        try {
            $this->metadata->persistSmartPixReferences($order, [
                'order_reference' => $orderReference,
                'payment_reference' => $paymentReference,
                'provider_order_reference' => $providerOrderReference,
            ]);
        } catch (\InvalidArgumentException $conflict) {
            return false;
        }
        $order->read_meta_data(true);
        return $this->metadata->getMpOrderId($order) === $providerOrderReference;
    }

    /** Accepts only "<store prefix><positive decimal id>" without padding or overflow. */
    private function orderId(string $attemptReference): ?int
    {
        $prefix = get_option('_mp_store_identificator', 'WC-');
        if (!is_string($prefix) || $prefix === '' || strpos($attemptReference, $prefix) !== 0) {
            return null;
        }
        $digits = substr($attemptReference, strlen($prefix));
        if (preg_match('/^[1-9][0-9]{0,18}$/D', $digits) !== 1) {
            return null;
        }
        $orderId = (int) $digits;
        return (string) $orderId === $digits ? $orderId : null;
    }

    /**
     * Persist the references that an authoritative read confirmed for a pending attempt, as the
     * checkout would have: the Orders reference, its payment and the provider Order. A concurrent
     * checkout writes the same values; any other persisted value refuses adoption.
     */
    public function adopt(WC_Order $order, string $orderReference, string $paymentReference, string $providerOrderReference): bool
    {
        if (
            !$this->isReference($orderReference)
            || !$this->isReference($paymentReference)
            || !$this->isReference($providerOrderReference)
        ) {
            return false;
        }
        $order->read_meta_data(true);
        $current = $this->metadata->getPpOrderId($order);
        if ($current === '' || $current === null) {
            $this->metadata->linkSmartPixOrder($order, $orderReference, time(), [
                'order_reference' => $orderReference,
                'payment_reference' => $paymentReference,
                'provider_order_reference' => $providerOrderReference,
            ]);
            $order->read_meta_data(true);
            $current = $this->metadata->getPpOrderId($order);
        }
        return $current === $orderReference && $this->metadata->getMpOrderId($order) === $providerOrderReference;
    }

    /**
     * A late Order paid for a superseded attempt: records it once for the merchant, without any
     * native payment effect, because the method that replaced it owns the order.
     */
    public function review(WC_Order $order, string $orderReference): bool
    {
        if (!$this->isReference($orderReference)) {
            return false;
        }
        if ($this->reviewLock === null) {
            return $this->recordReview($order, $orderReference);
        }
        // Concurrent redeliveries would both read the unset marker: only the lease holder records
        // the review, on freshly read metadata. A busy lease means another request is recording it.
        $lease = $this->reviewLock->acquire($order->get_id());
        if ($lease === null) {
            return false;
        }
        try {
            $order->read_meta_data(true);
            return $this->recordReview($order, $orderReference);
        } finally {
            $this->reviewLock->release($lease);
        }
    }

    private function recordReview(WC_Order $order, string $orderReference): bool
    {
        if ($this->metadata->isSupersededSmartPixSettled($order, $orderReference)) {
            return false;
        }
        // The replacing method may not have been paid (a declined card, an unpaid Pix QR code), so
        // the duplicate wording is only used when the order is actually paid.
        $note = $order->is_paid()
            ? sprintf(
                /* translators: %s: Mercado Pago Order ID */
                __(
                    'Mercado Pago: a Smart Pix payment (Order %s) was approved after this order was paid with another method. Review it in the Mercado Pago panel and refund it if it is a duplicate.',
                    'woocommerce-mercadopago'
                ),
                $orderReference
            )
            : sprintf(
                /* translators: %s: Mercado Pago Order ID */
                __(
                    'Mercado Pago: a Smart Pix payment (Order %s) was approved after another payment method was selected for this order. Review it in the Mercado Pago panel before changing the order.',
                    'woocommerce-mercadopago'
                ),
                $orderReference
            );
        // The note comes before the marker, and the marker and the manual review share one save:
        // an interruption in between replays the note on the next delivery instead of losing the alert.
        $order->add_order_note($note);
        $this->metadata->settleSupersededSmartPixReview($order, $orderReference);
        return true;
    }

    /**
     * @return array{linked: bool, category: string|null, order: WC_Order|null,
     *     attempt_key: string|null, order_reference: string|null}|null
     */
    private function superseded(WC_Order $order, string $attemptReference): ?array
    {
        if ($order->get_currency('edit') !== 'BRL' || $this->seller->getSiteId() !== 'MLB') {
            return null;
        }
        $attempt = $this->metadata->getSupersededSmartPixAttempt($order);
        if ($attempt === null || !hash_equals($attempt['attempt_reference'], $attemptReference)) {
            return null;
        }
        return [
            'linked' => false,
            'category' => self::CATEGORY_SUPERSEDED,
            'order' => $order,
            'attempt_key' => $attempt['attempt_key'],
            'order_reference' => null,
        ];
    }

    /** An order the reconciliation already escalated to the merchant. */
    public function inManualReview(WC_Order $order): bool
    {
        return $this->metadata->getPpOrderReconcileState($order) === OrderMetadata::PP_ORDER_RECONCILE_STATE_MANUAL_REVIEW;
    }

    private function load(int $orderId): ?WC_Order
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order || $order->get_id() !== $orderId) {
            return null;
        }
        // A metadata-only refresh would leave status, total and paid facts stale.
        $order->get_data_store()->read($order);
        $order->read_meta_data(true);
        return $order;
    }

    private function matchesContext(WC_Order $order): bool
    {
        return $order->get_payment_method('edit') === PixGateway::ID
            && $order->get_currency('edit') === 'BRL'
            && $this->seller->getSiteId() === 'MLB'
            && $this->metadata->getPaymentContract($order) === OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1;
    }

    /** @param mixed $reference */
    private function isReference($reference): bool
    {
        return is_string($reference) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $reference) === 1;
    }

    /**
     * @return array{linked: bool, category: string|null, order: WC_Order|null,
     *     attempt_key: string|null, order_reference: string|null}
     */
    private function rejected(): array
    {
        return [
            'linked' => false,
            'category' => self::CATEGORY_INVALID_LINKAGE,
            'order' => null,
            'attempt_key' => null,
            'order_reference' => null,
        ];
    }
}
