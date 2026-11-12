<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use RuntimeException;
use Throwable;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\SmartPixDiagnosticException;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMutationDispatchClient;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixPaidStatus;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

/** Caller authorizes the native order; adapters must supply authenticated mutation snapshots. */
final class SmartPixMutationProcessor
{
    public const SNAPSHOT_CONTRACT = 'smart-pix-mutations/v1';

    private SmartPixOrderClient $client;
    private OrderMetadata $metadata;
    private Seller $seller;
    private SmartPixOperationRepository $operations;
    /** @var callable */
    private $metric;
    /** @var callable */
    private $applyState;

    private SmartPixTelemetry $telemetry;
    private string $outcome = 'failure';
    private string $diagnosticStage = 'order_context';
    private string $diagnosticMessage = '';

    public function __construct(
        SmartPixOrderClient $client,
        OrderMetadata $metadata,
        Seller $seller,
        SmartPixOperationRepository $operations,
        callable $metric,
        callable $applyState,
        ?SmartPixTelemetry $telemetry = null
    ) {
        $this->client = $client;
        $this->metadata = $metadata;
        $this->seller = $seller;
        $this->operations = $operations;
        $this->metric = $metric;
        $this->applyState = $applyState;
        $this->telemetry = $telemetry ?? new SmartPixTelemetry();
    }

    /** @return array{success: bool, reason: string} */
    public function refund(WC_Order $order, string $identity, string $amount): array
    {
        return $this->execute($order, 'refund', $identity, $amount, false);
    }

    /** @return array{success: bool, reason: string} */
    public function cancel(WC_Order $order, bool $expiration = false): array
    {
        return $this->execute($order, 'cancel', 'cancel', '0.00', $expiration);
    }

    public function rejectRefundContext(?WC_Order $order = null): void
    {
        $this->telemetry->resetContext();
        if ($order !== null) {
            $this->telemetry->observeOrder($order);
        }
        $this->failure('refund', 'refund_context_invalid');
    }

    /** @return array{success: bool, reason: string} */
    private function execute(WC_Order $order, string $operation, string $identity, string $amount, bool $expiration): array
    {
        $started = microtime(true);
        $timer = $this->telemetry->startTimer();
        $this->telemetry->resetContext();
        $this->telemetry->observeOrder($order);
        $this->outcome = 'failure';
        $this->diagnosticStage = 'order_context';
        $this->diagnosticMessage = '';
        $reservation = null;
        $mutationSent = false;
        $nativeEffectStarted = false;
        $initialReadPending = true;
        $adapterReadPending = false;
        $readOnlyRecovery = false;
        try {
            $context = $this->context($order, $operation, $amount);
            // The reservation covers all operation initiators, including before provider reads.
            // No lease is held during HTTP. An abandoned dispatch remains blocked until reconciled.
            $this->diagnosticStage = 'operation_reservation';
            $reservation = $this->operations->reserve($order, $identity, $context);
            $readOnlyRecovery = $operation === 'cancel' && $reservation['dispatch'] === 'read_only';
            if ($reservation['dispatch'] !== 'yes' && !$readOnlyRecovery) {
                return $reservation['status'] === 'succeeded'
                    ? $this->success($operation, 'operation_already_applied')
                    : $this->failure($operation, 'operation_unresolved');
            }
            $request = $this->request($context);
            $this->diagnosticStage = 'preflight_read';
            $response = $this->client->query($request);
            $snapshot = $this->snapshot($response, $context);
            $observer = new SmartPixOrderQuery($this->client, $this->metadata, $this->seller);
            $observer->recordObservation($order, $context['order_reference'], $response);
            $initialReadPending = false;
            if ($operation === 'cancel' && $snapshot['payment_state'] === 'approved') {
                $nativeEffectStarted = true;
                $this->applyTerminal($order, $context['attempt_key'], $response);
                $this->operations->finish($order, $reservation, 'rejected');
                return $this->failure($operation, 'already_approved');
            }
            if ($operation === 'cancel' && in_array($snapshot['payment_state'], ['cancelled', 'rejected'], true)) {
                $fresh = $this->reload($order->get_id());
                if ($snapshot['refunded'] !== '0.00' || $fresh === null || $this->context($fresh, $operation, $amount) !== $context) {
                    throw new RuntimeException('order_context_conflict');
                }
                $nativeEffectStarted = true;
                $this->applyTerminal($order, $context['attempt_key'], $response);
                $this->operations->finish($order, $reservation, 'succeeded');
                return $this->success($operation, 'remote_terminal_confirmed');
            }
            if ($readOnlyRecovery) {
                throw new RuntimeException('operation_unresolved');
            }
            if (
                ($operation === 'refund' && $snapshot['payment_state'] !== 'approved')
                || ($operation === 'cancel' && $snapshot['payment_state'] !== 'processing')
            ) {
                throw new RuntimeException('state_conflict');
            }
            $remaining = SmartPixMoney::subtract($context['total'], $snapshot['refunded']);
            if ($remaining === null || ($operation === 'refund' && SmartPixMoney::compare($amount, $remaining) > 0)) {
                throw new RuntimeException('amount_conflict');
            }
            // Re-read server facts after the GET. An admin edit must never alter an in-flight intent.
            $fresh = $this->reload($order->get_id());
            if ($fresh === null || $this->context($fresh, $operation, $amount) !== $context) {
                throw new RuntimeException('order_context_conflict');
            }
            $request['operation_context'] = [
                'contract_version' => self::SNAPSHOT_CONTRACT,
                'idempotency_key' => $reservation['key'],
                'seller_reference' => $context['seller'],
            ];
            if ($operation === 'refund') {
                $request['refund_amount'] = ['value' => $amount, 'currency' => 'BRL'];
            } elseif ($expiration) {
                $this->emit('smart_pix_pending_expiration', 'cancel_requested', null);
                $this->telemetry->pendingExpiration();
            }
            $this->diagnosticStage = 'provider_mutation';
            if ($this->client instanceof SmartPixMutationDispatchClient) {
                $adapterReadPending = true;
                $onDispatch = static function () use (&$mutationSent): void {
                    $mutationSent = true;
                };
                $response = $operation === 'refund'
                    ? $this->client->refund($request, $onDispatch) : $this->client->cancel($request, $onDispatch);
            } else {
                // Without explicit adapter evidence, a mutation invocation may already send a POST.
                $mutationSent = true;
                $response = $operation === 'refund' ? $this->client->refund($request) : $this->client->cancel($request);
            }
            $this->diagnosticStage = 'mutation_confirmation';
            $final = $this->snapshot($response, $context, $operation === 'refund' ? $amount : null);
            if ($operation === 'cancel' && $final['payment_state'] === 'approved') {
                $observer->recordObservation($order, $context['order_reference'], $response);
                $nativeEffectStarted = true;
                $this->applyTerminal($order, $context['attempt_key'], $response);
                $this->operations->finish($order, $reservation, 'rejected');
                return $this->failure($operation, 'already_approved');
            }
            $expectedRefunded = $operation === 'refund'
                ? SmartPixMoney::subtract($context['total'], (string) SmartPixMoney::subtract((string) $remaining, $amount))
                : $snapshot['refunded'];
            if (
                ($operation === 'refund' && (($response['outcome'] ?? null) !== 'success' || $final['payment_state'] !== 'refunded'
                    || $response['state'] !== 'terminal_success' || $final['refunded'] !== $expectedRefunded))
                || ($operation === 'cancel' && (!in_array($final['payment_state'], ['cancelled', 'rejected'], true)
                    || $response['state'] !== 'terminal_failure' || $final['refunded'] !== '0.00'))
            ) {
                throw new RuntimeException('operation_not_confirmed');
            }
            $fresh = $this->reload($order->get_id());
            if ($fresh === null || $this->context($fresh, $operation, $amount) !== $context) {
                throw new RuntimeException('order_context_conflict');
            }
            $observer->recordObservation($order, $context['order_reference'], $response);
            if ($operation === 'cancel') {
                $nativeEffectStarted = true;
                $this->applyTerminal($order, $context['attempt_key'], $response);
            }
            $this->operations->finish($order, $reservation, 'succeeded');
            return $this->success($operation, 'remote_operation_confirmed');
        } catch (Throwable $exception) {
            // A provider write may already have succeeded. Keep the identity and never retry blindly.
            if ($reservation !== null && ($reservation['dispatch'] === 'yes' || $readOnlyRecovery)) {
                try {
                    $reason = $this->reason($exception);
                    $safeReadFailure = !$readOnlyRecovery && (($operation === 'cancel' && $initialReadPending) || $adapterReadPending)
                        && !$mutationSent && !$nativeEffectStarted
                        && (in_array($reason, ['timeout', 'unavailable', 'rate_limit'], true)
                            || ($operation === 'cancel' && $reason === 'invalid_response'));
                    $this->operations->finish($order, $reservation, $mutationSent || $nativeEffectStarted ? 'uncertain'
                        : ($safeReadFailure ? 'read_failed' : 'rejected'));
                } catch (Throwable $persistenceFailure) {
                    // The persisted dispatching record still blocks another operation.
                }
            }
            return $this->failure($operation, $this->reason($exception), SmartPixDiagnostic::exception($exception));
        } finally {
            $this->emit('smart_pix_server_processing_time', round(microtime(true) - $started, 3), $operation);
            $this->telemetry->processingTime($operation, $this->telemetry->elapsed($timer), $this->outcome, 'server', 'internal');
        }
    }

    /** @return array<string, string> */
    private function context(WC_Order $order, string $operation, string $amount): array
    {
        $order->read_meta_data(true);
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        $seller = $this->seller->getCustIdFromAT();
        $key = $this->metadata->getIdempotencyKey($order);
        $reference = $this->metadata->getPpOrderId($order);
        $attemptReference = $this->metadata->getSmartPixAttemptReference($order);
        if (
            $order->get_id() <= 0 || $order->get_payment_method('edit') !== PixGateway::ID
            || $order->get_currency('edit') !== 'BRL' || $this->seller->getSiteId() !== 'MLB'
            || $this->metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            || $total === null || $total === '0.00' || !is_string($seller) || $seller === ''
            || !is_string($key) || $key === '' || !is_string($reference)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $reference) !== 1
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $attemptReference) !== 1
            || SmartPixMoney::normalize($amount) !== $amount
            || ($operation === 'refund' && ($amount === '0.00'
                || !SmartPixPaidStatus::isPaid($order->get_status('edit'), $order->is_paid())
                || $order->get_date_paid('edit') === null))
            || ($operation === 'cancel' && ($order->is_paid() || $order->get_date_paid('edit') !== null
                || !in_array($order->get_status('edit'), ['pending', 'on-hold'], true) || $order->get_refunds() !== []))
        ) {
            throw new RuntimeException('validation');
        }
        return [
            'operation' => $operation, 'attempt_key' => $key, 'order_reference' => $reference,
            'attempt_reference' => $attemptReference, 'seller' => $seller,
            'total' => $total, 'amount' => $amount, 'currency' => 'BRL',
        ];
    }

    /** @param array<string, string> $context @return array<string, mixed> */
    private function request(array $context): array
    {
        return [
            'surface' => 'server', 'country' => 'MLB', 'payment_contract' => 'orders_smart_pix_v1',
            'attempt_reference' => $context['attempt_reference'], 'order_reference' => $context['order_reference'],
            'amount' => null, 'refund_amount' => null,
        ];
    }

    private function reload(int $orderId): ?WC_Order
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order || $order->get_id() !== $orderId) {
            return null;
        }
        $order->get_data_store()->read($order);
        return $order;
    }

    /** @param array<string, mixed> $response @param array<string, string> $context @return array{payment_state: string, refunded: string} */
    private function snapshot(array $response, array $context, ?string $refundAmount = null): array
    {
        $this->diagnosticMessage = '';
        // Provider observations are informational and do not select a financial operation.
        unset($response['observed']);
        if (
            ($response['outcome'] ?? null) === 'failure' && is_array($response['error'] ?? null)
            && !in_array($response['state'] ?? null, ['terminal_success', 'terminal_failure'], true)
        ) {
            $category = $response['error']['category'] ?? null;
            throw new RuntimeException(in_array($category, ['timeout', 'unavailable', 'rate_limit', 'validation', 'authentication', 'conflict'], true)
                ? $category : 'invalid_response');
        }
        $snapshot = $response['order_context'] ?? null;
        $payments = $response['payments'] ?? null;
        if (
            $this->invalid(!$this->hasKeys($response, ['outcome', 'state', 'order_reference', 'eligibility', 'authorization', 'payments', 'error', 'order_context']), 'normalized response has missing or unexpected fields')
            || $this->invalid(!is_array($snapshot), 'normalized order_context is missing')
            || $this->invalid(!$this->hasKeys($snapshot, ['contract_version', 'seller_reference', 'attempt_reference', 'amount', 'refunded_amount']), 'normalized order_context has missing or unexpected fields')
            || $this->invalid(($snapshot['contract_version'] ?? null) !== self::SNAPSHOT_CONTRACT, 'mutation snapshot contract_version is unsupported')
            || $this->invalid(($snapshot['seller_reference'] ?? null) !== $context['seller'], 'snapshot seller does not match the authenticated seller')
            || $this->invalid(($snapshot['attempt_reference'] ?? null) !== $context['attempt_reference'], 'snapshot attempt_reference does not match the persisted attempt')
            || $this->invalid(($snapshot['amount'] ?? null) !== ['value' => $context['total'], 'currency' => 'BRL'], 'snapshot amount/currency differs from the WooCommerce order')
            || $this->invalid(SmartPixMoney::normalize($snapshot['refunded_amount'] ?? null) !== ($snapshot['refunded_amount'] ?? null), 'snapshot refunded_amount is not a canonical decimal')
            || $this->invalid(($snapshot['refunded_amount'] ?? null) === null, 'snapshot refunded_amount is missing')
            || $this->invalid(SmartPixMoney::compare($snapshot['refunded_amount'], $context['total']) > 0, 'snapshot refunded_amount exceeds the order total')
            || $this->invalid(($response['order_reference'] ?? null) !== $context['order_reference'], 'snapshot order_reference differs from the linked order')
            || $this->invalid(!array_key_exists('eligibility', $response), 'normalized eligibility field is missing')
            || $this->invalid($response['eligibility'] !== null, 'normalized eligibility must be null')
            || $this->invalid(!array_key_exists('authorization', $response), 'normalized authorization field is missing')
            || $this->invalid($response['authorization'] !== null, 'normalized authorization must be null')
            || $this->invalid(!is_array($payments), 'normalized payments must be an array')
            || $this->invalid(count($payments) !== 1, 'mutation snapshot requires exactly one payment')
            || $this->invalid(array_keys($payments) !== [0], 'normalized payments must be a sequential single-payment list')
        ) {
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        // Only the explicitly supported single-payment contract; never pick one of several movements.
        $payment = reset($payments);
        $state = $payment['state'] ?? null;
        if (
            $this->invalid(!is_array($payment), 'normalized payment must be an object')
            || $this->invalid(!$this->hasKeys($payment, ['reference', 'state', 'amount']), 'normalized payment has missing or unexpected fields')
            || $this->invalid(!is_string($payment['reference'] ?? null), 'normalized payment.reference is missing or invalid')
            || $this->invalid(preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $payment['reference']) !== 1, 'normalized payment.reference has an invalid format')
            || $this->invalid(!in_array($state, ['approved', 'processing', 'cancelled', 'rejected', 'refunded'], true), 'normalized payment.state is unsupported')
            || $this->invalid(($payment['amount'] ?? null) !== ['value' => $refundAmount ?? $context['total'], 'currency' => 'BRL'], 'normalized payment.amount differs from the expected order/refund amount')
            || $this->invalid(!in_array($response['outcome'] ?? null, ['success', 'failure'], true), 'normalized outcome is unsupported')
            || $this->invalid(!in_array($response['state'] ?? null, ['processing', 'terminal_success', 'terminal_failure'], true), 'normalized order state is unsupported')
            || $this->invalid(!array_key_exists('error', $response), 'normalized error field is missing')
            || $this->invalid(($state !== 'rejected' && $response['error'] !== null), 'non-rejected payment must not contain an error')
            || $this->invalid(($state === 'approved' && ($response['outcome'] !== 'success' || $response['state'] !== 'terminal_success')), 'approved payment must have a successful terminal result')
            || $this->invalid(($state === 'cancelled' && ($response['outcome'] !== 'success' || $response['state'] !== 'terminal_failure')), 'cancelled payment must have a terminal failure state')
            || $this->invalid(($state === 'processing' && $response['state'] !== 'processing'), 'processing payment must have a processing order state')
            || $this->invalid(($state === 'rejected' && ($response['outcome'] !== 'failure' || $response['state'] !== 'terminal_failure')), 'rejected payment must have a failed terminal result')
            || $this->invalid(($state === 'rejected' && !$this->isRejectionError($response['error'])), 'rejected payment has an invalid rejection error contract')
        ) {
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        return ['payment_state' => $state, 'refunded' => $snapshot['refunded_amount']];
    }

    /** @param array<string, mixed> $value @param array<string> $expected */
    private function hasKeys(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);
        return $actual === $expected;
    }

    /** @param mixed $error */
    private function isRejectionError($error): bool
    {
        return SmartPixOrderNormalizer::rejectionStatusDetail($error) !== null;
    }

    /** @param array<string, mixed> $response */
    private function applyTerminal(WC_Order $order, string $key, array $response): void
    {
        unset($response['order_context']);
        $this->diagnosticStage = 'woocommerce_status';
        $result = ($this->applyState)($order->get_id(), $key, $response);
        $state = $response['payments'][0]['state'];
        // A store-configured paid status is valid: approval is confirmed by the applicator's verified paid-state reasons.
        $confirmed = $state === 'approved'
            ? in_array($result['reason'] ?? null, ['native_paid_state_verified', 'approval_already_applied'], true)
            : ($result['status'] ?? null) === ($state === 'cancelled' ? 'cancelled' : 'failed');
        if (
            !is_array($result) || !in_array($result['outcome'] ?? null, ['applied', 'no_change'], true)
            || !$confirmed
        ) {
            throw new RuntimeException('native_effect_not_confirmed');
        }
    }

    private function invalid(bool $invalid, string $message): bool
    {
        if ($invalid) {
            $this->diagnosticMessage = $message;
        }
        return $invalid;
    }

    private function reason(Throwable $exception): string
    {
        $allowed = [
            'validation', 'invalid_response', 'state_conflict', 'amount_conflict', 'order_context_conflict',
            'operation_context_conflict', 'operation_busy', 'operation_unresolved', 'operation_limit',
            'native_effect_not_confirmed',
            'operation_journal_invalid', 'operation_ownership_lost', 'operation_persistence_failed', 'operation_not_confirmed',
            'timeout', 'unavailable', 'rate_limit', 'authentication', 'conflict',
        ];
        return $exception instanceof RuntimeException && in_array($exception->getMessage(), $allowed, true)
            ? $exception->getMessage() : 'unexpected';
    }

    /** @return array{success: bool, reason: string} */
    private function failure(string $operation, string $reason, string $diagnostic = ''): array
    {
        $this->emit('smart_pix_server_error', $operation, $reason);
        $category = in_array($reason, ['timeout', 'unavailable', 'authentication', 'validation'], true) ? $reason
            : (strpos($reason, 'conflict') !== false || $reason === 'already_approved' ? 'conflict' : 'internal_error');
        $this->telemetry->error($operation, $category, 'server', 'internal', $operation . '.' . $this->diagnosticStage . ': ' . ($diagnostic !== '' ? $diagnostic : SmartPixDiagnostic::reason($reason)));
        $this->telemetry->mutationResult($operation, 'failed', $reason);
        return ['success' => false, 'reason' => $reason];
    }

    private function success(string $operation, string $reason): array
    {
        $this->outcome = 'success';
        $this->telemetry->mutationResult($operation, $reason === 'operation_already_applied' ? 'already_applied' : 'succeeded');
        return ['success' => true, 'reason' => $reason];
    }

    /** @param mixed $value */
    private function emit(string $name, $value, ?string $message): void
    {
        try {
            ($this->metric)($name, $value, $message);
        } catch (Throwable $telemetryFailure) {
            // Telemetry cannot change a financial outcome.
        }
    }
}
