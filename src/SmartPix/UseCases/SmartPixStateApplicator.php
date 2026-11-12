<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use Throwable;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixPaidStatus;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applies validated normalized terminal decisions through native WooCommerce methods.
 * Shared ownership prevents duplicate native effects from polling and notifications.
 */
final class SmartPixStateApplicator
{
    private OrderMetadata $metadata;

    private SmartPixStateReducer $reducer;

    private Seller $seller;

    private SmartPixNativeEffects $nativeEffects;

    private SmartPixEffectOwnershipStore $ownership;

    private SmartPixTelemetry $telemetry;

    /** @var callable(WC_Order, string): void|null */
    private $recordNotes;

    private SmartPixRefundReconciler $refunds;

    /** @var list<string> */
    private array $diagnostics = [];

    /** Last verified WooCommerce paid fact; lets telemetry classify store-configured paid statuses. */
    private bool $observedPaid = false;

    public function __construct(
        OrderMetadata $metadata,
        SmartPixStateReducer $reducer,
        Seller $seller,
        SmartPixEffectOwnershipStore $ownership,
        ?SmartPixTelemetry $telemetry = null,
        ?SmartPixNativeEffects $nativeEffects = null,
        ?callable $recordNotes = null,
        ?SmartPixRefundReconciler $refunds = null
    ) {
        $this->metadata = $metadata;
        $this->reducer = $reducer;
        $this->seller = $seller;
        $this->ownership = $ownership;
        $this->telemetry = $telemetry ?? new SmartPixTelemetry();
        $this->nativeEffects = $nativeEffects ?? new SmartPixNativeEffects();
        $this->recordNotes = $recordNotes;
        $this->refunds = $refunds ?? new SmartPixRefundReconciler($metadata, $seller);
    }

    /**
     * The caller owns authorization and response validation. IDs and attempt keys must
     * come from trusted server context, never from an unchecked browser/webhook body.
     * Accepts only the normalized internal terminal contract, never raw Orders data.
     * State verification does not establish stock, email or arbitrary hook delivery.
     *
     * @param array<string, mixed> $response
     * @return array{outcome: string, reason: string, status: string|null}
     */
    public function apply(
        int $orderId,
        string $attemptKey,
        array $response,
        string $source = 'internal',
        string $surface = 'server'
    ): array {
        $this->diagnostics = [];
        $this->observedPaid = false;
        $result = null;
        try {
            $result = $this->applyInternal($orderId, $attemptKey, $response);
            return $result;
        } finally {
            // Transport happens only after ownership finalization, never inside native hooks.
            if ($result !== null) {
                /** @var list<string> $diagnostics Populated by native/ownership exception handlers. */
                $diagnostics = $this->diagnostics;
                if ($diagnostics !== [] || !in_array($result['outcome'], ['applied', 'no_change', 'busy'], true)) {
                    $this->telemetry->error(
                        'apply',
                        $this->telemetry->applicationErrorCategory($result['reason'], $diagnostics !== []),
                        $surface,
                        $source,
                        $diagnostics === [] ? $result['reason'] : implode(' | ', $diagnostics)
                    );
                }
                $this->telemetry->effectResult($result['outcome'], $result['reason'], $result['status'], $surface, $source, $this->observedPaid);
                if (
                    in_array($result['outcome'], ['applied', 'no_change'], true)
                    && in_array($response['payments'][0]['state'] ?? null, ['approved', 'rejected', 'cancelled'], true)
                    && !isset($response['refund_context'])
                    && ($response['observed']['order_status_detail'] ?? null) !== 'partially_refunded'
                ) {
                    $this->telemetry->terminalResult($orderId, $source, $response['observed'] ?? []);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return array{outcome: string, reason: string, status: string|null}
     */
    private function applyInternal(int $orderId, string $attemptKey, array $response): array
    {
        if ($orderId <= 0 || $attemptKey === '' || !$this->isTerminalShape($response)) {
            return $this->result('unsupported', 'invalid_terminal_context');
        }

        if ($this->isPartialRefund($response)) {
            // Partial imports use the shared operation journal, never payment approval ownership.
            try {
                $order = $this->load($orderId);
                return $order !== null && $this->matchesContext($order, $attemptKey, $response)
                    ? $this->refunds->applyPartial($order, $attemptKey, $response)
                    : $this->result('conflict', 'order_context_mismatch');
            } catch (Throwable $failure) {
                $this->logFailure('refund_reconciliation_unavailable', $failure);
                return $this->result('uncertain', 'refund_reconciliation_unavailable');
            }
        }

        $effect = $response['payments'][0]['state'] === 'refunded' ? 'record_full_refund'
            : ($response['payments'][0]['state'] === 'approved' ? 'complete_payment'
            : ($response['payments'][0]['state'] === 'rejected' ? 'mark_failed' : 'mark_cancelled'));
        try {
            $claim = $this->ownership->acquire($orderId, $attemptKey, $effect);
        } catch (Throwable $exception) {
            $this->logFailure('ownership_unavailable', $exception);
            return $this->result('uncertain', 'ownership_unavailable');
        }
        if ($claim['outcome'] === 'completed') {
            // A tombstone denies effects, but the local native facts still need validation.
            $started = false;
            return $this->applyOwned($orderId, $attemptKey, $response, $effect, '', $started, true);
        }
        if (
            $claim['outcome'] !== 'acquired' || !is_string($claim['owner'])
            || preg_match('/^[a-f0-9]{64}$/D', $claim['owner']) !== 1
        ) {
            $outcome = $claim['outcome'] === 'busy' ? 'busy'
                : ($claim['outcome'] === 'conflict' ? 'conflict' : 'uncertain');
            $reason = ['busy' => 'native_effect_in_progress', 'conflict' => 'ownership_context_conflict',
                'uncertain' => 'native_effect_uncertain'][$claim['outcome']] ?? 'ownership_unavailable';
            return $this->result($outcome, $reason);
        }

        $started = false;
        try {
            $result = $this->applyOwned($orderId, $attemptKey, $response, $effect, $claim['owner'], $started);
            if ($started && $result['outcome'] === 'applied') {
                if (!$this->ownership->complete($orderId, $attemptKey, $effect, $claim['owner'])) {
                    $result = $this->result('uncertain', 'ownership_completion_not_verified', $result['status']);
                }
            }
        } catch (Throwable $exception) {
            $this->logFailure('ownership_execution_failed', $exception);
            $result = $this->result('uncertain', 'ownership_execution_failed');
        }
        try {
            if (!$started) {
                if (!$this->ownership->releaseReserved($orderId, $attemptKey, $effect, $claim['owner'])) {
                    return $this->result('uncertain', 'ownership_release_not_verified', $result['status']);
                }
            } elseif ($result['outcome'] !== 'applied') {
                $this->ownership->markUncertain($orderId, $attemptKey, $effect, $claim['owner']);
            }
        } catch (Throwable $exception) {
            $this->logFailure('ownership_finalization_failed', $exception);
            return $this->result('uncertain', 'ownership_finalization_failed', $result['status']);
        }
        return $result;
    }

    /**
     * Reload all local facts after admission; do not decide effects from a pre-claim snapshot.
     * @param array<string, mixed> $response
     * @return array{outcome: string, reason: string, status: string|null}
     */
    private function applyOwned(
        int $orderId,
        string $attemptKey,
        array $response,
        string $effect,
        string $owner,
        bool &$started,
        bool $completedClaim = false
    ): array {

        try {
            $order = $this->load($orderId);
            if ($order === null || !$this->matchesContext($order, $attemptKey, $response)) {
                return $this->result('conflict', 'order_context_mismatch');
            }
            $this->telemetry->observeOrder($order);
            if ($effect === 'record_full_refund') {
                return $this->refunds->apply($order, $attemptKey, $response, function () use ($orderId, $attemptKey, $effect, $owner, &$started, $completedClaim): bool {
                    if ($completedClaim || !$this->ownership->start($orderId, $attemptKey, $effect, $owner)) {
                        return false;
                    }
                    $started = true;
                    return true;
                });
            }
            $amount = $this->amount($order);
            if ($amount === null) {
                return $this->result('unsupported', 'unsupported_order_total');
            }
            $status = $order->get_status('edit');
            $paid = $order->is_paid();
            $this->observedPaid = $paid;
            if ($paid !== ($order->get_date_paid('edit') !== null)) {
                return $this->result('conflict', 'paid_facts_disagree', $status);
            }
            if ($order->get_refunds() !== []) {
                return $this->result('conflict', 'refund_context_conflict', $status);
            }
            // No refund objects means canonical zero; never coerce refunded totals from floats.
            $decision = $this->reducer->reduce($status, $paid, '0.00', $response, $amount);
            if ($decision['decision'] !== 'propose_effects') {
                return $this->result($decision['decision'], $decision['reason'], $status);
            }
            $approval = $decision['effects'] === ['complete_payment'] && $decision['next_status'] === null;
            $target = $decision['next_status'];
            $statusChange = ($decision['effects'] === ['mark_failed'] && $target === 'failed')
                || ($decision['effects'] === ['mark_cancelled'] && $target === 'cancelled');
            if (!$approval && !$statusChange) {
                return $this->result('unsupported', 'unsupported_native_effect', $status);
            }
            if ($completedClaim) {
                return $this->result('uncertain', 'completed_effect_not_verified', $status);
            }
            $transactionId = $order->get_transaction_id('edit');
        } catch (Throwable $exception) {
            $this->logFailure('order_read_failed', $exception);
            return $this->result('uncertain', 'order_read_failed');
        }

        if (!$this->ownership->start($orderId, $attemptKey, $effect, $owner)) {
            return $this->result('uncertain', 'ownership_start_not_verified', $status);
        }
        // No lease takeover exists. A crash from this point leaves execution blocked.
        $started = true;

        // No provider transaction-ID mapping is evidenced yet. Preserve the native ID.
        // Native methods own status, paid date, notes and stock/email hooks.
        try {
            $completed = $approval ? $order->payment_complete() : ($target === 'cancelled'
                ? $this->nativeEffects->cancel($order, static function () use ($order): bool {
                    return $order->update_status('cancelled');
                })
                : $order->update_status($target));
        } catch (Throwable $exception) {
            $this->logFailure($approval ? 'native_completion_threw' : 'native_status_threw', $exception);
            $completed = false;
        }

        try {
            $persisted = $this->load($orderId);
            if (
                $persisted === null
                || !$this->matchesContext($persisted, $attemptKey, $response)
                || $this->amount($persisted) !== $amount
                || $persisted->get_refunds() !== []
                || $persisted->get_transaction_id('edit') !== $transactionId
                // Stores may choose their own paid status; it must still agree with is_paid().
                || ($approval && (
                    !SmartPixPaidStatus::isPaid($persisted->get_status('edit'), $persisted->is_paid())
                    || $persisted->get_date_paid('edit') === null
                ))
                || (!$approval && (
                    $persisted->is_paid()
                    || $persisted->get_date_paid('edit') !== null
                    || $persisted->get_status('edit') !== $target
                ))
            ) {
                return $this->result('uncertain', $approval ? 'native_completion_not_verified' : 'native_status_not_verified');
            }
            $persistedStatus = $persisted->get_status('edit');
            // Verified above: approval requires is_paid(), a status change requires !is_paid().
            $this->observedPaid = $approval;
        } catch (Throwable $exception) {
            $this->logFailure($approval ? 'completion_reload_failed' : 'status_reload_failed', $exception);
            return $this->result('uncertain', $approval ? 'completion_reload_failed' : 'status_reload_failed');
        }

        if ($completed === true && $this->recordNotes !== null) {
            // Only the admitted, verified transition writes notes, never a replay/no-change. The
            // transition is already persisted and verified: a note or e-mail failure cannot undo it.
            try {
                ($this->recordNotes)($persisted, $approval ? 'approved' : $target);
            } catch (Throwable $noteFailure) {
                $this->logFailure('status_note_failed', $noteFailure);
            }
        }

        return $completed === true
            ? $this->result('applied', $approval ? 'native_paid_state_verified' : 'native_unpaid_status_verified', $persistedStatus)
            : $this->result('uncertain', $approval ? 'native_completion_reported_failure' : 'native_status_reported_failure', $persistedStatus);
    }

    /** Log fixed reasons only; telemetry failure must not change native-effect verification. */
    private function logFailure(string $reason, ?Throwable $exception = null): void
    {
        $this->diagnostics[] = $reason . ($exception === null ? '' : ': ' . SmartPixDiagnostic::exception($exception));
        try {
            wc_get_logger()->warning('Smart Pix state application failed.', [
                'source' => 'mercadopago-smart-pix', 'reason' => $reason,
            ]);
        } catch (Throwable $loggingFailure) {
            // Preserve the uncertain result when an optional logging integration fails.
        }
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

    /** @param array<string, mixed> $response */
    private function matchesContext(WC_Order $order, string $attemptKey, array $response): bool
    {
        return $order->get_payment_method('edit') === PixGateway::ID
            && $order->get_currency('edit') === 'BRL'
            && $this->seller->getSiteId() === 'MLB'
            && $this->metadata->getPaymentContract($order) === OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            && $this->metadata->getIdempotencyKey($order) === $attemptKey
            && $this->metadata->getPpOrderId($order) === $response['order_reference'];
    }

    /** @return array{value: string, currency: string}|null */
    private function amount(WC_Order $order): ?array
    {
        // WooCommerce stubs describe float, but CRUD preserves the decimal string.
        return $this->decimalAmount($order->get_total('edit'));
    }

    /**
     * @param mixed $total
     * @return array{value: string, currency: string}|null
     */
    private function decimalAmount($total): ?array
    {
        if (
            !is_string($total)
            || strlen($total) > 64
            || preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2})0*)?$/D', $total, $parts) !== 1
        ) {
            return null;
        }
        $value = $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
        return $value === '0.00' ? null : ['value' => $value, 'currency' => 'BRL'];
    }

    /**
     * The envelope is the normalized internal contract, not a raw provider response.
     * Keep structural, reference and exact-money guards when replacing fixture mappings.
     * @param array<string, mixed> $response
     */
    private function isTerminalShape(array $response): bool
    {
        $keys = ['outcome', 'state', 'order_reference', 'eligibility', 'authorization', 'payments', 'error'];
        // Observed provider facts are server-side records only; they never select an effect.
        if (array_key_exists('observed', $response)) {
            $keys[] = 'observed';
        }
        if (array_key_exists('refund_context', $response)) {
            $keys[] = 'refund_context';
        }
        if (
            !$this->hasKeys($response, $keys)
            || (array_key_exists('observed', $response) && !is_array($response['observed']))
            || $response['eligibility'] !== null
            || $response['authorization'] !== null
            || !$this->isReference($response['order_reference'])
            || !is_array($response['payments'])
            || array_keys($response['payments']) !== [0]
        ) {
            return false;
        }
        $payment = $response['payments'][0];
        if (
            !is_array($payment)
            || !$this->hasKeys($payment, ['reference', 'state', 'amount'])
            || !$this->isReference($payment['reference'])
            || !is_array($payment['amount'])
            || !$this->hasKeys($payment['amount'], ['value', 'currency'])
        ) {
            return false;
        }
        $approved = $payment['state'] === 'approved' && $response['outcome'] === 'success'
            && $response['state'] === 'terminal_success' && $response['error'] === null;
        $refunded = $payment['state'] === 'refunded' && $response['outcome'] === 'success'
            && $response['state'] === 'terminal_success' && $response['error'] === null
            && is_array($response['refund_context'] ?? null);
        // The cancellation combination still requires a provider mapping before integration.
        $cancelled = $payment['state'] === 'cancelled' && $response['outcome'] === 'success'
            && $response['state'] === 'terminal_failure' && $response['error'] === null;
        $rejected = $payment['state'] === 'rejected' && $response['outcome'] === 'failure'
            && $response['state'] === 'terminal_failure'
            && SmartPixOrderNormalizer::rejectionStatusDetail($response['error']) !== null;
        if (
            (!$approved && !$cancelled && !$rejected && !$refunded)
            || (array_key_exists('refund_context', $response) && !$refunded && !$this->isPartialRefund($response))
            || ((($response['observed']['order_status_detail'] ?? null) === 'partially_refunded'
                    || ($response['observed']['payment_status_detail'] ?? null) === 'partially_refunded')
                && !$this->isPartialRefund($response))
        ) {
            return false;
        }
        // Structural money validation must remain for every accepted combination.
        $value = $payment['amount']['value'];
        return is_string($value) && strlen($value) <= 64 && $value !== '0.00'
            && preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1
            && is_string($payment['amount']['currency'])
            && preg_match('/^[A-Z]{3}$/D', $payment['amount']['currency']) === 1;
    }

    /** Partial refund observations may only confirm native facts, without payment effects. */
    private function isPartialRefund(array $response): bool
    {
        return ($response['payments'][0]['state'] ?? null) === 'approved'
            && is_array($response['refund_context'] ?? null)
            && ($response['observed']['order_status'] ?? null) === 'processed'
            && ($response['observed']['order_status_detail'] ?? null) === 'partially_refunded'
            && ($response['observed']['payment_status'] ?? null) === 'processed'
            && ($response['observed']['payment_status_detail'] ?? null) === 'partially_refunded';
    }

    /** @param mixed $reference */
    private function isReference($reference): bool
    {
        return is_string($reference) && strlen($reference) <= 128
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $reference) === 1;
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string> $expected
     */
    private function hasKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys);
        sort($expected);
        return $keys === $expected;
    }

    /** @return array{outcome: string, reason: string, status: string|null} */
    private function result(string $outcome, string $reason, ?string $status = null): array
    {
        return ['outcome' => $outcome, 'reason' => $reason, 'status' => $status];
    }
}
