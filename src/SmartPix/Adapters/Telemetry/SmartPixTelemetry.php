<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

use Closure;
use Throwable;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixBuyerError;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;

if (!defined('ABSPATH')) {
    // @codeCoverageIgnoreStart
    exit;
    // @codeCoverageIgnoreEnd
}

/** Best-effort semantic telemetry. A missing sink emits nothing and never activates callers. */
final class SmartPixTelemetry
{
    private ?SmartPixMetricSink $sink;
    private Closure $clock;
    private SmartPixMetricContext $metricContext;
    private ?Closure $terminalObserved;

    public function __construct(?SmartPixMetricSink $sink = null, ?callable $clock = null, ?SmartPixMetricContext $metricContext = null, ?callable $terminalObserved = null)
    {
        $this->sink = $sink;
        $this->metricContext = $metricContext ?? new SmartPixMetricContext();
        $this->terminalObserved = $terminalObserved === null ? null : Closure::fromCallable($terminalObserved);
        $this->clock = Closure::fromCallable($clock ?? static fn(): float => hrtime(true) / 1000000000);
    }

    /** One production composition for checkout, notifications, admin and cron. */
    public static function fromPlugin($mercadopago, ?SmartPixMetricContext $context = null): self
    {
        $context = $context ?? new SmartPixMetricContext();
        return new self(
            SmartPixCoreMonitorMetricSink::fromPlugin($mercadopago, $context),
            null,
            $context,
            static function (int $orderId, string $source, array $observed) use ($mercadopago, $context): void {
                $order = wc_get_order($orderId);
                if ($order instanceof \WC_Order) {
                    $details = $context->details();
                    if (isset($observed['payment_status_detail'])) {
                        $details['payment_status_detail'] = $observed['payment_status_detail'];
                    }
                    SmartPixTerminalObservation::fromPlugin($mercadopago, $source, $details)->record($order);
                }
            }
        );
    }

    public function terminalResult(int $orderId, string $source, array $observed = []): void
    {
        try {
            if ($this->terminalObserved !== null) {
                ($this->terminalObserved)($orderId, $source, $observed);
            }
        } catch (Throwable $failure) {
            // A diagnostic failure must never change a verified financial result.
        }
    }

    public function resetContext(): void
    {
        $this->metricContext->reset();
    }

    public function observeOrder(\WC_Order $order): void
    {
        $this->metricContext->observeOrder($order);
    }

    public function observeRequest(?string $requestId, ?string $apiRoute): void
    {
        $this->metricContext->observeRequest($requestId, $apiRoute);
    }

    public function startTimer(): ?float
    {
        try {
            $value = ($this->clock)();
            return (is_float($value) || is_int($value)) && is_finite((float) $value) ? (float) $value : null;
        } catch (Throwable $failure) {
            return null;
        }
    }

    public function elapsed(?float $startedAt): ?float
    {
        $now = $startedAt === null ? null : $this->startTimer();
        $seconds = $now === null ? null : $now - $startedAt;
        return $seconds !== null && is_finite($seconds) && $seconds > 0 ? $seconds : null;
    }

    public function error(string $operation, string $category, string $surface, string $source, string $message = ''): void
    {
        $details = $this->context($operation, $surface, $source);
        $details['category'] = $category;
        $this->count(SmartPixMetricSchema::ERROR, 'true', $details, SmartPixDiagnostic::message($message));
    }

    public function processingTime(string $operation, ?float $seconds, string $outcome, string $surface, string $source): void
    {
        $this->distribution(SmartPixMetricSchema::TIME, $seconds, $operation, $outcome, $surface, $source);
    }

    /** Successful checkout creation, measured over the whole gateway handler. */
    public function orderCreationTime(?float $seconds, string $surface): void
    {
        $this->distribution(SmartPixMetricSchema::ORDER_CREATION_TIME, $seconds, 'create', 'success', $surface, 'sync_response');
    }

    /** From the attempt's pending start until its approval was first observed. */
    public function paymentApprovalTime(?float $seconds, string $operation, string $surface, string $source): void
    {
        $this->distribution(SmartPixMetricSchema::PAYMENT_APPROVAL_TIME, $seconds, $operation, 'success', $surface, $source);
    }

    public function paymentRejectionTime(?float $seconds, string $operation, string $surface, string $source): void
    {
        $this->distribution(SmartPixMetricSchema::PAYMENT_REJECTION_TIME, $seconds, $operation, 'success', $surface, $source);
    }

    /** Browser navigation to fully displayed result, independent of the order/attempt clocks. */
    public function thankyouResultTime(?float $seconds): void
    {
        $this->distribution(SmartPixMetricSchema::THANKYOU_RESULT_TIME, $seconds, 'get', 'success', 'server', 'internal');
    }

    public function mutationResult(string $operation, string $outcome, string $reason = ''): void
    {
        $details = $this->context($operation, 'server', 'internal');
        if ($outcome === 'failed') {
            $details['category'] = in_array($reason, ['timeout', 'unavailable', 'authentication', 'validation'], true)
                ? $reason : (strpos($reason, 'conflict') !== false || $reason === 'already_approved' ? 'conflict' : 'internal_error');
        }
        $this->count(SmartPixMetricSchema::MUTATION, $outcome, $details);
    }

    public function pendingExpiration(): void
    {
        $this->count(SmartPixMetricSchema::EXPIRATION, 'cancel_requested', $this->context('cancel', 'server', 'internal'));
    }

    /** Store-configured paid statuses use the fixed `paid_custom` value; their names are never sent. */
    public function effectResult(
        string $outcome,
        string $reason,
        ?string $status,
        string $surface,
        string $source,
        bool $paid = false
    ): void {
        $details = $this->context('apply', $surface, $source);
        $details['reason_class'] = $this->reasonClass($reason);
        $details['native_status'] = in_array($status, ['pending', 'on-hold', 'processing', 'completed', 'failed', 'cancelled', 'refunded'], true)
            ? $status : ($paid && $status !== null ? 'paid_custom' : 'unknown');
        $this->count(SmartPixMetricSchema::EFFECT, $outcome, $details);
    }

    /**
     * Count one confirmed rejection. The value follows the buyer-facing rejection code and the
     * message carries the provider detail only when allowlisted, keeping cardinality bounded.
     */
    public function paymentRejected(?string $statusDetail, string $surface, string $source): void
    {
        $classes = [SmartPixBuyerError::INSUFFICIENT_BALANCE => 'insufficient_balance',
            SmartPixBuyerError::LIMIT_EXCEEDED => 'limit_exceeded', SmartPixBuyerError::REJECTED => 'rejected'];
        $value = $classes[SmartPixBuyerError::fromRejectionStatusDetail($statusDetail)] ?? 'rejected';
        $message = in_array($statusDetail, ['insufficient_amount', 'rejected_cap_exceeded', 'high_risk'], true)
            ? (string) $statusDetail : 'other';
        $this->count(SmartPixMetricSchema::PAYMENT_REJECTED, $value, $this->context('apply', $surface, $source), $message);
    }

    /** One WP-Cron reconciliation outcome: resolved, rescheduled or escalated to manual review. */
    /** @param string $reason Closed schema message distinguishing a cause within the outcome. */
    public function reconciliation(string $outcome, string $reason = ''): void
    {
        $this->count(SmartPixMetricSchema::RECONCILIATION, $outcome, $this->context('apply', 'server', 'internal'), $reason);
    }

    /** An approved Payments payment recorded on an order holding the Smart Pix contract. */
    public function legacyPaymentRecorded(string $source): void
    {
        $this->count(SmartPixMetricSchema::LEGACY_PAYMENT, 'approved', $this->context('notification', 'server', $source));
    }

    /** @param string $reason Closed schema message distinguishing a cause within the category. */
    public function notificationRejected(string $category, string $reason = ''): void
    {
        $this->count(
            SmartPixMetricSchema::NOTIFICATION_REJECTED,
            $category,
            $this->context('notification', 'server', 'notification'),
            $reason
        );
    }

    /**
     * Classify normalized categories without forwarding provider codes or messages.
     * @param array<string, mixed> $result
     */
    public function normalizedErrorCategory(array $result): ?string
    {
        if (($result['outcome'] ?? null) === 'success' || ($result['state'] ?? null) === 'terminal_failure') {
            return null;
        }
        $error = $result['error'] ?? null;
        $category = is_array($error) ? ($error['category'] ?? null) : null;
        $map = ['timeout' => 'timeout', 'rate_limit' => 'http_4xx', 'authentication' => 'authentication',
            'validation' => 'validation', 'invalid_response' => 'invalid_contract', 'linkage_mismatch' => 'invalid_contract',
            'unknown' => 'unknown_state', 'unavailable' => 'unavailable', 'persistence' => 'persistence', 'conflict' => 'conflict'];
        return is_string($category) ? ($map[$category] ?? 'internal_error') : 'invalid_contract';
    }

    /**
     * Closed error category of an application outcome, derived from its reason class: an invalid
     * normalized context is a contract failure, an order/state disagreement is a conflict, ownership
     * and native-effect failures are persistence, and other caught exceptions are internal errors.
     */
    public function applicationErrorCategory(string $reason, bool $exception = false): string
    {
        $class = $this->reasonClass($reason);
        if ($class === 'invalid_context') {
            return 'invalid_contract';
        }
        if (strpos($reason, 'conflict') !== false) {
            return 'conflict';
        }
        if (in_array($class, ['ownership', 'native_effect', 'native_verification'], true)) {
            return 'persistence';
        }
        return $exception ? 'internal_error' : 'conflict';
    }

    /** @return array<string, string> */
    private function context(string $operation, string $surface, string $source): array
    {
        return ['operation' => $operation, 'source' => $source, 'surface' => $surface,
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
    }

    private function reasonClass(string $reason): string
    {
        if ($reason === 'invalid_terminal_context') {
            return 'invalid_context';
        }
        if (in_array($reason, ['order_context_mismatch', 'paid_facts_disagree', 'refund_context_conflict', 'unsupported_order_total', 'order_read_failed'], true)) {
            return 'order_context';
        }
        if (
            in_array($reason, ['native_effect_in_progress', 'ownership_context_conflict', 'native_effect_uncertain', 'ownership_unavailable',
            'refund_operation_unresolved', 'refund_reconciliation_unavailable',
            'ownership_completion_not_verified', 'ownership_execution_failed', 'ownership_release_not_verified',
            'ownership_finalization_failed', 'ownership_start_not_verified'], true)
        ) {
            return 'ownership';
        }
        if (in_array($reason, ['native_completion_threw', 'native_status_threw', 'native_completion_reported_failure', 'native_status_reported_failure'], true)) {
            return 'native_effect';
        }
        if (
            in_array($reason, ['completed_effect_not_verified', 'native_completion_not_verified', 'native_status_not_verified',
            'native_refund_verified', 'native_refund_not_verified',
            'completion_reload_failed', 'status_reload_failed', 'native_paid_state_verified', 'native_unpaid_status_verified'], true)
        ) {
            return 'native_verification';
        }
        return 'state_decision';
    }

    private function distribution(string $name, ?float $seconds, string $operation, string $outcome, string $surface, string $source): void
    {
        if ($seconds === null) {
            return;
        }
        $details = $this->context($operation, $surface, $source);
        $details['outcome'] = $outcome;
        if (!SmartPixMetricSchema::validDistribution($name, $seconds, $details)) {
            return;
        }
        try {
            if ($this->sink !== null) {
                $this->sink->distribution($name, $seconds, $details);
            }
        } catch (Throwable $failure) {
            // Metrics do not authorize retries, change financial results or release ownership.
        }
    }

    /** @param array<string, string> $details */
    private function count(string $name, string $value, array $details, string $message = ''): void
    {
        if (
            !SmartPixMetricSchema::validCount($name, $value, $details)
            || !SmartPixMetricSchema::validMessage($name, $value, $message)
        ) {
            return;
        }
        try {
            if ($this->sink !== null && $message === '') {
                $this->sink->count($name, $value, $details);
            } elseif ($this->sink !== null) {
                $this->sink->count($name, $value, $details, $message);
            }
        } catch (Throwable $failure) {
            // Do not log raw transport exceptions or sensitive metric inputs.
        }
    }
}
