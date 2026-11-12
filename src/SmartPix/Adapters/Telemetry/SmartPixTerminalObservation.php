<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

use Closure;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixStatusEndpoint;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Counts each Smart Pix attempt's terminal outcome once, whoever moved the order there (this
 * polling, a notification, the store) and wherever it is first seen (status poll or page render).
 * It only reads the order status and writes its own flag: it never repeats a transition effect.
 * Concurrent observers (a poll and a render, or two polls) are serialized by a per-order lease and
 * re-read the flag from the database under it, so only one of them reports.
 */
final class SmartPixTerminalObservation
{
    private OrderMetadata $metadata;

    private SmartPixStatusLock $lock;

    /** @var Closure */
    private Closure $reportMetric;

    /**
     * @param Closure $reportMetric Receives the state, duration and order; legacy two-argument callbacks remain valid.
     */
    public function __construct(OrderMetadata $metadata, SmartPixStatusLock $lock, Closure $reportMetric)
    {
        $this->metadata     = $metadata;
        $this->lock         = $lock;
        $this->reportMetric = $reportMetric;
    }

    /** The same durable flag is shared by all server-side observers. */
    public static function fromPlugin($mercadopago, string $source, array $context = [], ?Datadog $datadog = null): self
    {
        $sources = ['sync_response' => 'checkout', 'pending_query' => 'poll', 'internal' => 'server'];
        $source = $sources[$source] ?? $source;
        $events = SmartPixEventMetrics::fromPlugin($mercadopago, $datadog);
        return new self($mercadopago->orderMetadata,
            new SmartPixStatusLock(null, SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX),
            static function (string $state, string $duration, WC_Order $order) use ($mercadopago, $events, $source, $context): void {
                try {
                    $detail = $context['payment_status_detail'] ?? $mercadopago->orderMetadata->getPpPaymentStatusDetail($order);
                } catch (\Throwable $failure) {
                    $detail = null;
                }
                $details = SmartPixMetricContext::forOrder($order) + $context + ['source' => $source];
                if (is_string($detail) && preg_match('/\A[a-z0-9_]{1,64}\z/D', $detail) === 1) {
                    $details['payment_status_detail'] = $detail;
                    if ($state === 'rejected') {
                        $details['reason'] = $detail;
                    }
                }
                $events->send(SmartPixMetricPayload::STATUS_OBSERVED, $state, $duration, $details);
            });
    }

    public function record(WC_Order $order): void
    {
        try {
            $state = SmartPixStatusEndpoint::publicStateFor($order);
            if ($state === SmartPixStatusEndpoint::STATE_PENDING || $this->alreadyReported($order)) {
                return;
            }

            $lease = $this->lock->acquire($order->get_id());
            if ($lease === null) {
                return;
            }

            try {
                $this->reportOnce($order, $state);
            } finally {
                $this->lock->release($lease);
            }
        } catch (\Throwable $telemetryFailure) {
            // Observability is best-effort and must never change the public state or the page.
        }
    }

    private function reportOnce(WC_Order $order, string $state): void
    {
        // The pre-check read a cached copy; a concurrent observer may have flagged it since.
        $order->read_meta_data(true);
        if ($this->alreadyReported($order)) {
            return;
        }

        // Flag before reporting: a lost metric is preferable to a terminal outcome counted twice.
        $this->metadata->setPpOrderTerminalReported($order, $state);
        ($this->reportMetric)($state, $this->secondsSinceOrderCreation($order), $order);
    }

    /** Elapsed until first observation, not the provider's decision timestamp. */
    public function secondsSinceOrderCreation(WC_Order $order): string
    {
        try {
            $created = $order->get_date_created();
            return $created === null ? 'unknown' : max(0, time() - $created->getTimestamp()) . 's';
        } catch (\Throwable $failure) {
            return 'unknown';
        }
    }

    private function alreadyReported(WC_Order $order): bool
    {
        $reported = $this->metadata->getPpOrderTerminalReported($order);

        return $reported !== '' && $reported !== null && $reported !== false;
    }

    /** Never the WooCommerce order creation: on Order Pay the order can predate the attempt by days. */
    public function secondsSinceAttemptStart(WC_Order $order): string
    {
        $pendingSince = $this->metadata->getPpOrderPendingSince($order);
        if (is_int($pendingSince)) {
            $pendingSince = (string) $pendingSince;
        }
        if (!is_string($pendingSince) || !ctype_digit($pendingSince) || (int) $pendingSince <= 0) {
            return 'unknown';
        }

        return max(0, time() - (int) $pendingSince) . 's';
    }
}
