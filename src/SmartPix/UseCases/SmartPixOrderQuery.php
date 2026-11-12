<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use Throwable;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixRequestObservation;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

/** Read an existing server-linked Order without authorization or payment effects. */
final class SmartPixOrderQuery
{
    private SmartPixOrderClient $client;
    private OrderMetadata $metadata;
    private Seller $seller;

    private SmartPixTelemetry $telemetry;
    private string $source;

    public function __construct(
        SmartPixOrderClient $client,
        OrderMetadata $metadata,
        Seller $seller,
        ?SmartPixTelemetry $telemetry = null,
        string $source = 'pending_query'
    ) {
        $this->client = $client;
        $this->metadata = $metadata;
        $this->seller = $seller;
        $this->telemetry = $telemetry ?? new SmartPixTelemetry();
        $this->source = $source === 'notification' ? 'notification' : 'pending_query';
    }

    /**
     * The entrypoint must authorize access to this WooCommerce order before calling.
     * A candidate reference is read only while no Orders reference is persisted for the attempt;
     * it comes from an authenticated notification and is confirmed by this read.
     * @return array<string, mixed>
     */
    public function query(WC_Order $order, string $surface, ?string $candidateReference = null): array
    {
        try {
            return $this->queryLinkedOrder($order, $surface, $candidateReference);
        } catch (Throwable $failure) {
            $this->reportError('internal_error', $surface, SmartPixDiagnostic::exception($failure));
            throw $failure;
        }
    }

    /**
     * Read a late Order of a superseded attempt (see OrderMetadata::getSupersededSmartPixAttempt).
     * The notification candidate must name an Order created for that attempt reference; nothing is
     * recorded on the order because the attempt no longer owns its Smart Pix metadata.
     * @return array<string, mixed>
     */
    public function querySuperseded(WC_Order $order, string $candidateReference, string $attemptReference): array
    {
        try {
            if (
                $order->get_id() <= 0
                || $order->get_currency() !== 'BRL'
                || $this->seller->getSiteId() !== 'MLB'
                || !$this->isReference($candidateReference)
                || !$this->isReference($attemptReference)
            ) {
                return $this->failure('server');
            }
            return $this->client->query([
                'surface' => 'server',
                'country' => 'MLB',
                'payment_contract' => OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
                'attempt_reference' => $attemptReference,
                'order_reference' => $candidateReference,
                'amount' => null,
                'refund_amount' => null,
            ]);
        } catch (Throwable $failure) {
            $this->reportError('internal_error', 'server', SmartPixDiagnostic::exception($failure));
            throw $failure;
        }
    }

    /** Server-only diagnostics; absent IDs are never replaced with a creation request ID. */
    public function metricDetails(WC_Order $order): array
    {
        $details = SmartPixMetricContext::forOrder($order);
        if ($this->client instanceof SmartPixRequestObservation) {
            $details = array_merge($details, $this->client->requestDetails());
        }
        return $details;
    }

    /** @return array<string, mixed> */
    private function queryLinkedOrder(WC_Order $order, string $surface, ?string $candidateReference): array
    {
        if (
            !in_array($surface, ['classic', 'blocks', 'order_pay', 'server'], true)
            || $order->get_id() <= 0
            || $order->get_payment_method() !== PixGateway::ID
            || $order->get_currency() !== 'BRL'
            || $this->seller->getSiteId() !== 'MLB'
        ) {
            return $this->failure($surface);
        }

        // Read authoritative metadata only; never create an attempt during a query.
        $order->read_meta_data(true);
        $contract = $this->metadata->getPaymentContract($order);
        $key = $this->metadata->getIdempotencyKey($order);
        $stored = $this->metadata->getPpOrderId($order);
        // The checkout may persist the same Order meanwhile; that candidate is read as the linked Order.
        // A different persisted reference still rejects the candidate below.
        if (
            $candidateReference !== null && is_string($stored) && $stored !== ''
            && hash_equals($stored, $candidateReference)
        ) {
            $candidateReference = null;
        }
        $reference = $candidateReference ?? $stored;
        $attemptReference = get_option('_mp_store_identificator', 'WC-') . $order->get_id();
        if (
            ($candidateReference !== null && $stored !== '' && $stored !== null)
            || $contract !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            || !is_string($key) || $key === ''
            || !$this->isReference($reference)
            || !$this->isReference($attemptReference)
        ) {
            return $this->failure($surface);
        }

        $this->telemetry->observeOrder($order);
        $result = $this->client->query([
            'surface' => $surface,
            'country' => 'MLB',
            'payment_contract' => $contract,
            'attempt_reference' => $attemptReference,
            'order_reference' => $reference,
            'amount' => null,
            'refund_amount' => null,
        ]);
        $this->recordObservation($order, $reference, $result);
        return $result;
    }

    /**
     * Shared recorder of provider facts from a validated linked read or confirmed mutation.
     * Recording is informational; a failure never changes the returned result.
     * @param array<string, mixed> $result
     */
    public function recordObservation(WC_Order $order, string $reference, array $result): void
    {
        $observed = $result['observed'] ?? null;
        if (($result['order_reference'] ?? null) !== $reference || !$this->isObservation($observed)) {
            return;
        }
        try {
            $this->metadata->persistSmartPixObservation($order, $observed);
        } catch (Throwable $failure) {
            try {
                wc_get_logger()->warning('Smart Pix observation was not recorded.', [
                    'source' => 'mercadopago-smart-pix', 'reason' => 'observation_write_failed',
                ]);
            } catch (Throwable $loggingFailure) {
                // Logging is optional and never carries provider values.
            }
        }
    }

    /**
     * @param mixed $observed
     * Order reads, lifecycle reads included, also project the first payment id and its layer reference.
     * @phpstan-assert-if-true array{order_status: string, order_status_detail: string, payment_status: string,
     *     payment_status_detail: string, total_failed: string|null, payment_id?: string,
     *     payment_reference?: string|null} $observed
     */
    private function isObservation($observed): bool
    {
        if (!is_array($observed)) {
            return false;
        }
        $keys = array_keys($observed);
        sort($keys);
        $base = ['order_status', 'order_status_detail', 'payment_status', 'payment_status_detail', 'total_failed'];
        $withPayment = [
            'order_status', 'order_status_detail', 'payment_id', 'payment_reference',
            'payment_status', 'payment_status_detail', 'total_failed',
        ];
        if ($keys !== $base && $keys !== $withPayment) {
            return false;
        }
        foreach (['order_status', 'order_status_detail', 'payment_status', 'payment_status_detail'] as $key) {
            if (!is_string($observed[$key]) || preg_match('/^[a-z0-9_]{1,64}$/D', $observed[$key]) !== 1) {
                return false;
            }
        }
        if (
            $keys === $withPayment
            && (!$this->isReference($observed['payment_id'])
                || ($observed['payment_reference'] !== null && !$this->isReference($observed['payment_reference'])))
        ) {
            return false;
        }
        return $observed['total_failed'] === null || (is_string($observed['total_failed'])
            && strlen($observed['total_failed']) <= 64
            && preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D', $observed['total_failed']) === 1);
    }

    private function reportError(string $category, string $surface, string $message = ''): void
    {
        $surface = in_array($surface, ['classic', 'blocks', 'order_pay', 'server'], true) ? $surface : 'server';
        $this->telemetry->error($this->source === 'notification' ? 'notification' : 'get', $category, $surface, $this->source, $message);
    }

    /** @param mixed $reference */
    private function isReference($reference): bool
    {
        return is_string($reference) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $reference) === 1;
    }

    /** @return array<string, mixed> */
    private function failure(string $surface): array
    {
        $this->reportError('validation', $surface, 'query.order_context: missing, conflicting or invalid WooCommerce order/attempt reference, payment contract, gateway, currency or site');
        return [
            'outcome' => 'failure', 'state' => 'error', 'order_reference' => null,
            'eligibility' => null, 'authorization' => null, 'payments' => [],
            'error' => ['category' => 'validation', 'code' => 'SMART_PIX_VALIDATION', 'retryable' => false],
        ];
    }
}
