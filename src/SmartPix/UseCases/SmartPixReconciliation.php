<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use Throwable;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-Cron safety net for a linked Smart Pix order whose notification could not be resolved.
 *
 * The notification receiver schedules it whenever it answers 500 (busy or uncertain effect,
 * retryable Orders read, unavailable ownership), so the order converges even if Core P&P stops
 * redelivering. Each run reads the Order through the same query and applies a terminal answer
 * through the same ownership-guarded applicator, never creating or replaying an effect itself.
 * Busy effects, retryable reads and still-processing Orders are retried with a growing delay up to
 * MAX_ATTEMPTS; uncertain, conflicting or unsupported effects and exhausted attempts are escalated
 * to manual review with one order note and a metric. Requires WP-Cron (or a real cron when
 * DISABLE_WP_CRON is set). Runs never throw into WP-Cron.
 */
final class SmartPixReconciliation
{
    public const HOOK = 'mp_smart_pix_reconcile_order';

    private const PAID_CONFLICT_REASONS = [
        'amount_mismatch', 'currency_mismatch', 'paid_state_conflict', 'local_state_conflict', 'terminal_state_conflict',
    ];

    public const MAX_ATTEMPTS = 10;

    /** Longer than the unstarted-reservation lease, so a stale reservation can be taken over. */
    private const FIRST_DELAY_SECONDS = 300;

    private const MAX_DELAY_SECONDS = 3600;

    private const OPEN_STATUSES = ['pending', 'on-hold'];

    private const TERMINAL_STATES = ['terminal_success', 'terminal_failure'];

    private SmartPixOrderQuery $query;

    private SmartPixStateApplicator $applicator;

    private OrderMetadata $metadata;

    private SmartPixTelemetry $telemetry;

    /** @var callable(int, string, array<int, int>): bool */
    private $scheduler;

    /** @var callable(string, array<int, int>): bool */
    private $isScheduled;

    /** @var callable(): int */
    private $clock;

    /**
     * @param (callable(int, string, array<int, int>): bool)|null $scheduler
     * @param (callable(string, array<int, int>): bool)|null $isScheduled
     * @param (callable(): int)|null $clock
     */
    public function __construct(
        SmartPixOrderQuery $query,
        SmartPixStateApplicator $applicator,
        OrderMetadata $metadata,
        ?SmartPixTelemetry $telemetry = null,
        ?callable $scheduler = null,
        ?callable $isScheduled = null,
        ?callable $clock = null
    ) {
        $this->query = $query;
        $this->applicator = $applicator;
        $this->metadata = $metadata;
        $this->telemetry = $telemetry ?? new SmartPixTelemetry();
        $this->scheduler = $scheduler ?? static function (int $timestamp, string $hook, array $args): bool {
            return wp_schedule_single_event($timestamp, $hook, $args) === true;
        };
        $this->isScheduled = $isScheduled ?? static function (string $hook, array $args): bool {
            return wp_next_scheduled($hook, $args) !== false;
        };
        $this->clock = $clock ?? static fn(): int => time();
    }

    /** Schedules one attempt; past MAX_ATTEMPTS the order goes to manual review instead. */
    public function schedule(int $orderId, int $attempt = 1): void
    {
        try {
            if ($orderId <= 0 || $attempt <= 0) {
                return;
            }
            if ($attempt > self::MAX_ATTEMPTS) {
                // A closed order also gets here when its uncertain ownership was never surfaced.
                $order = wc_get_order($orderId);
                if ($order instanceof WC_Order && $this->isLinked($order)) {
                    $this->escalate($order);
                }
                return;
            }
            $args = [$orderId, $attempt];
            if (($this->isScheduled)(self::HOOK, $args)) {
                return;
            }
            $delay = min(self::MAX_DELAY_SECONDS, self::FIRST_DELAY_SECONDS * (2 ** ($attempt - 1)));
            ($this->scheduler)(($this->clock)() + $delay, self::HOOK, $args);
        } catch (Throwable $exception) {
            // Scheduling is a safety net; a failure never changes the caller's answer.
        }
    }

    /** WP-Cron callback for HOOK. */
    public function run(int $orderId, int $attempt): void
    {
        try {
            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order || !$this->isLinked($order)) {
                return;
            }
            // A native effect may persist the paid status and still fail in its hooks, leaving its
            // ownership uncertain. A closed order is read once more only to surface that ownership,
            // so it keeps the same retries until a terminal answer can be applied.
            $open = $this->isOpen($order);
            $response = $this->query->query($order, 'server');
            if (!in_array($response['state'] ?? null, self::TERMINAL_STATES, true)) {
                // Still processing, or the read failed: the Order may settle later.
                $this->reschedule($orderId, $attempt);
                return;
            }
            $key = $this->metadata->getIdempotencyKey($order);
            $result = $this->applicator->apply($orderId, is_string($key) ? $key : '', $response, 'internal', 'server');
            $paidConflict = self::isPaidConflict($result, $response);
            if (!$open) {
                if ($result['outcome'] === 'busy' || $result['reason'] === 'ownership_unavailable') {
                    $this->reschedule($orderId, $attempt);
                } elseif ($result['outcome'] === 'uncertain' || $paidConflict) {
                    $this->escalate($order);
                }
                return;
            }
            if ($result['outcome'] === 'applied' || $result['outcome'] === 'no_change') {
                $this->telemetry->reconciliation('resolved');
                return;
            }
            if ($result['outcome'] === 'busy' || $result['reason'] === 'ownership_unavailable') {
                $this->reschedule($orderId, $attempt);
                return;
            }
            // Uncertain, conflict or unsupported: never replay. A visible status resolves only conflicts and
            // unsupported effects; an uncertain effect may have missed its hooks, so it always goes to review.
            $fresh = wc_get_order($orderId);
            if ($result['outcome'] !== 'uncertain' && !$paidConflict && $fresh instanceof WC_Order && !$this->isOpen($fresh)) {
                $this->telemetry->reconciliation('resolved');
                return;
            }
            $this->escalate($fresh instanceof WC_Order ? $fresh : $order);
        } catch (Throwable $exception) {
            $this->reschedule($orderId, $attempt);
        }
    }

    /**
     * The provider holds a paid Order that the native order state refuses (another amount or
     * currency, or a closed or differently paid order): the merchant must see it, whatever the
     * native status. Ownership and context conflicts are not provider facts and stay silent.
     * @param array<string, mixed> $result
     * @param array<string, mixed> $response
     */
    public static function isPaidConflict(array $result, array $response): bool
    {
        return ($result['outcome'] ?? null) === 'conflict'
            && ($response['state'] ?? null) === 'terminal_success'
            && in_array($result['reason'] ?? null, self::PAID_CONFLICT_REASONS, true);
    }

    /** A linked Smart Pix order not yet sent to manual review. */
    private function isLinked(WC_Order $order): bool
    {
        $reference = $this->metadata->getPpOrderId($order);

        return $order->get_payment_method() === PixGateway::ID
            && $this->metadata->getPaymentContract($order) === OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            && is_string($reference) && $reference !== ''
            && $this->metadata->getPpOrderReconcileState($order) !== OrderMetadata::PP_ORDER_RECONCILE_STATE_MANUAL_REVIEW;
    }

    /** A linked, unpaid Smart Pix order still waiting for its terminal state. */
    private function isOpen(WC_Order $order): bool
    {
        return in_array($order->get_status(), self::OPEN_STATUSES, true)
            && !$order->is_paid()
            && $this->isLinked($order);
    }

    private function reschedule(int $orderId, int $attempt): void
    {
        $this->telemetry->reconciliation('rescheduled');
        $this->schedule($orderId, $attempt + 1);
    }

    private function escalate(WC_Order $order): void
    {
        $this->metadata->setPpOrderReconcileState($order, OrderMetadata::PP_ORDER_RECONCILE_STATE_MANUAL_REVIEW);
        $order->add_order_note(__(
            'Mercado Pago: the Smart Pix payment status could not be reconciled automatically. Review the order and its payment in the Mercado Pago panel.',
            'woocommerce-mercadopago'
        ));
        $this->telemetry->reconciliation('manual_review');
    }
}
