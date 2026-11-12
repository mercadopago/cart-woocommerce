<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use InvalidArgumentException;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\SmartPixExistingPixException;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\SmartPixOrderDetails;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    // @codeCoverageIgnoreStart
    exit;
    // @codeCoverageIgnoreEnd
}

/**
 * Validates server-owned checkout invariants without interpreting opaque provider proof.
 */
final class SmartPixValidator
{
    private Seller $seller;

    private SmartPixTelemetry $telemetry;

    private SmartPixOrderDetails $orderDetails;

    private ?OrderMetadata $metadata;

    /**
     * @param OrderMetadata|null $metadata When given, an order with a conventional Pix QR issued is
     *     refused, since that QR may still be paid after the Smart Pix attempt.
     */
    public function __construct(
        Seller $seller,
        ?SmartPixTelemetry $telemetry = null,
        ?SmartPixOrderDetails $orderDetails = null,
        ?OrderMetadata $metadata = null
    ) {
        $this->seller = $seller;
        $this->telemetry = $telemetry ?? new SmartPixTelemetry();
        $this->orderDetails = $orderDetails ?? new SmartPixOrderDetails();
        $this->metadata = $metadata;
    }

    /**
     * The caller must resolve the order and expected ID from the authenticated WooCommerce
     * operation, never from an unchecked browser ID. Only the Smart Pix object belongs here;
     * the complete extensible WooCommerce form must not be passed to this method.
     * Nonce/session authorization remains the responsibility of the checkout entrypoint.
     * The Smart Pix object carries only the authorization proof. Optional buyer identification
     * comes from the order's existing billing data, never a second payment-box field.
     *
     * @param array<string, mixed> $smartPix
     * @return array<string, mixed> Normalized request without authorization material. Its
     *                              order_details hold buyer personal data: never log or persist them.
     */
    public function validate(
        ?WC_Order $order,
        int $expectedOrderId,
        string $surface,
        array $smartPix,
        ?string $orderKey = null
    ): array {
        try {
            return $this->validateContext($order, $expectedOrderId, $surface, $smartPix, $orderKey);
        } catch (InvalidArgumentException $failure) {
            $metricSurface = in_array($surface, ['classic', 'blocks', 'order_pay', 'server'], true) ? $surface : 'server';
            $this->telemetry->error('validation', 'validation', $metricSurface, 'sync_response', 'checkout.validation: ' . SmartPixDiagnostic::exception($failure));
            throw $failure;
        }
    }

    /**
     * @param array<string, mixed> $smartPix
     * @return array<string, mixed>
     */
    private function validateContext(?WC_Order $order, int $expectedOrderId, string $surface, array $smartPix, ?string $orderKey): array
    {
        if ($order === null) {
            throw new InvalidArgumentException('Invalid Smart Pix order context.');
        }
        $attemptReference = $this->resolveAttemptReference($order, $expectedOrderId, $surface, $orderKey);

        if ($order->get_payment_method() !== PixGateway::ID || $order->is_paid() || !$order->needs_payment()) {
            throw new InvalidArgumentException('The Smart Pix order is not payable.');
        }

        // Its legacy notifications would be ignored once the Smart Pix contract is persisted.
        if ($this->metadata !== null && $this->metadata->hasConventionalPixPayment($order)) {
            throw new SmartPixExistingPixException();
        }

        if (
            $order->get_currency() !== 'BRL'
            || $this->seller->getSiteId() !== 'MLB'
            || $this->seller->getCredentialsAccessToken() === ''
        ) {
            throw new InvalidArgumentException('Invalid Smart Pix financial configuration.');
        }

        $amount = $this->normalizeAmount($order->get_total());
        $this->validateProof($smartPix);
        $orderDetails = $this->orderDetails->build($order, $amount);

        return [
            'surface'           => $surface,
            'country'           => 'MLB',
            'payment_contract'  => 'orders_smart_pix_v1',
            'attempt_reference' => $attemptReference,
            'order_reference'   => null,
            'amount'            => ['value' => $amount, 'currency' => 'BRL'],
            'refund_amount'     => null,
            'order_details'     => $orderDetails,
        ];
    }

    /** Resolve the technical reference only from a matching, trusted native order. */
    private function resolveAttemptReference(WC_Order $order, int $expectedOrderId, string $surface, ?string $orderKey): string
    {
        $nativeOrderId = $order->get_id();
        if (
            $expectedOrderId <= 0
            || $nativeOrderId !== $expectedOrderId
            || !in_array($surface, ['classic', 'blocks', 'order_pay'], true)
        ) {
            throw new InvalidArgumentException('Invalid Smart Pix order context.');
        }

        if ($surface === 'order_pay') {
            $storedKey = $order->get_order_key();
            if ($orderKey === null || $orderKey === '' || $storedKey === '' || !hash_equals($storedKey, $orderKey)) {
                throw new InvalidArgumentException('Invalid Smart Pix order context.');
            }
        }

        // The store identificator prefix matches the external reference used by the other
        // gateways and by notification linkage. External references remain validated at
        // the client boundary, so a prefix outside the opaque reference format fails closed.
        return get_option('_mp_store_identificator', 'WC-') . $nativeOrderId;
    }

    /**
     * Preserve WooCommerce's authoritative decimal total; do not introduce float rounding.
     * @param mixed $total
     */
    private function normalizeAmount($total): string
    {
        if (
            !is_string($total)
            || strlen($total) > 64
            || preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2})0*)?$/D', $total, $parts) !== 1
        ) {
            throw new InvalidArgumentException('Invalid Smart Pix order total.');
        }

        $amount = $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
        if ($amount === '0.00') {
            throw new InvalidArgumentException('Invalid Smart Pix order total.');
        }

        return $amount;
    }

    /**
     * @param array<string, mixed> $smartPix
     */
    private function validateProof(array $smartPix): void
    {
        $fields = array_keys($smartPix);
        sort($fields);
        if ($fields !== ['openFinanceAccountId', 'token']) {
            throw new InvalidArgumentException('Invalid Smart Pix fields.');
        }

        $token     = $smartPix['token'];
        $accountId = $smartPix['openFinanceAccountId'];
        if (
            !is_string($token)
            || trim($token) === ''
            || strlen($token) > 4096
            || !is_string($accountId)
            || strlen($accountId) !== 36
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $accountId) !== 1
        ) {
            throw new InvalidArgumentException('Invalid Smart Pix authorization shape.');
        }
    }
}
