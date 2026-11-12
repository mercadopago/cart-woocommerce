<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Projects validated Orders responses into the internal contract without native effects.
 */
final class SmartPixOrderNormalizer
{
    private string $diagnosticMessage = '';

    /** Diagnostics are a side channel, never part of the financial/buyer result contract. */
    public function diagnosticMessage(): string
    {
        return $this->diagnosticMessage;
    }

    private function invalid(bool $invalid, string $message): bool
    {
        if ($invalid) {
            $this->diagnosticMessage = $message;
        }
        return $invalid;
    }

    private const REJECTION_CODE_PREFIX = 'SMART_PIX_REJECTED_';

    /** A confirmed provider rejection is an expected buyer outcome, not a server error. */
    private const REJECTION_CATEGORY = 'rejected';

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $expectedAmount Server-owned create request amount.
     * @return array<string, mixed>
     */
    public function normalize(
        array $data,
        ?string $expectedReference = null,
        ?array $expectedAmount = null,
        ?string $expectedAttemptReference = null
    ): array {
        $this->diagnosticMessage = '';
        $reference = $data['id'] ?? null;
        if ($this->invalid(!$this->isReference($reference), 'order.id is missing or invalid')) {
            return $this->failure('invalid_response', $expectedReference);
        }
        if ($this->invalid($expectedReference !== null && !hash_equals($expectedReference, $reference), 'order.id does not match the linked order')) {
            return $this->failure('linkage_mismatch', $expectedReference);
        }
        if (
            $this->invalid(!$this->isReference($data['external_reference'] ?? null), 'order.external_reference is missing or invalid')
            || $this->invalid(($expectedAttemptReference !== null && !hash_equals($expectedAttemptReference, $data['external_reference'])), 'order.external_reference does not match the attempt')
        ) {
            return $this->failure('linkage_mismatch', $expectedReference);
        }

        // The P&P envelope is explicit; never merge it with the nested fixture contract.
        if (array_key_exists('site_id', $data)) {
            return $this->normalizePpOrder($data, $reference, $expectedAmount);
        }

        if (
            $this->invalid(($data['status'] ?? null) !== 'processing', 'legacy order.status must be processing')
            || $this->invalid(($data['status_detail'] ?? null) !== 'in_process', 'legacy order.status_detail must be in_process')
        ) {
            return $this->failure('unknown', $reference);
        }

        if (
            $this->invalid(($data['type'] ?? null) !== 'online', 'order.type must be online')
            || $this->invalid(($data['country_code'] ?? null) !== 'BRA', 'order.country_code must be BRA')
            || $this->invalid(($data['currency'] ?? null) !== 'BRL', 'order.currency must be BRL')
            || $this->invalid(!$this->isPositiveAmount($data['total_amount'] ?? null), 'order.total_amount must be a positive decimal string')
            || $this->invalid(($data['total_paid_amount'] ?? null) !== '0.00', 'processing order.total_paid_amount must be 0.00')
            || $this->invalid(($expectedAmount !== null && ( ($expectedAmount['currency'] ?? null) !== $data['currency'] || ($expectedAmount['value'] ?? null) !== $data['total_amount'] )), 'order amount or currency does not match the creation request')
            || $this->invalid((array_key_exists('payments', $data) && $data['payments'] !== []), 'mixed order envelopes: payments and transactions')
        ) {
            return $this->failure('invalid_response', $reference);
        }

        $transactions = $data['transactions'] ?? null;
        $rawPayments  = is_array($transactions) ? ($transactions['payments'] ?? null) : null;
        if (
            $this->invalid(!is_array($rawPayments), 'transactions.payments must be an array')
            || $this->invalid($rawPayments === [], 'transactions.payments must not be empty')
            || $this->invalid(array_keys($rawPayments) !== range(0, count($rawPayments) - 1), 'transactions.payments must be a sequential list')
        ) {
            return $this->failure('invalid_response', $reference);
        }

        $payments = [];
        $seen     = [];
        $sum      = '0.00';
        foreach ($rawPayments as $rawPayment) {
            if ($this->invalid(!is_array($rawPayment), 'transactions.payments entry must be an object')) {
                return $this->failure('invalid_response', $reference);
            }
            $paymentReference = $rawPayment['id'] ?? null;
            $method           = $rawPayment['payment_method'] ?? null;
            if (
                $this->invalid(!$this->isReference($paymentReference), 'payment.id is missing or invalid')
                || $this->invalid(in_array($paymentReference, $seen, true), 'duplicate payment.id')
                || $this->invalid(!$this->isPositiveAmount($rawPayment['amount'] ?? null), 'payment.amount must be a positive decimal string')
                || $this->invalid((count($rawPayments) === 1 && $rawPayment['amount'] !== $data['total_amount']), 'payment.amount does not match order.total_amount')
                || $this->invalid(!is_array($method), 'payment.payment_method must be an object')
                || $this->invalid(($method['id'] ?? null) !== 'smart_transfer', 'payment.payment_method.id must be smart_transfer')
                || $this->invalid(($method['type'] ?? null) !== 'smart_transfer', 'payment.payment_method.type must be smart_transfer')
            ) {
                return $this->failure('invalid_response', $reference);
            }
            if (
                $this->invalid(($rawPayment['status'] ?? null) !== 'processing', 'legacy payment.status must be processing')
                || $this->invalid(($rawPayment['status_detail'] ?? null) !== 'in_process', 'legacy payment.status_detail must be in_process')
            ) {
                return $this->failure('unknown', $reference);
            }

            $seen[]     = $paymentReference;
            $sum        = $this->add($sum, $rawPayment['amount']);
            $payments[] = [
                'reference' => $paymentReference,
                'state'     => 'processing',
                'amount'    => ['value' => $rawPayment['amount'], 'currency' => 'BRL'],
            ];
        }

        if ($this->invalid($sum !== $data['total_amount'], 'sum of payment amounts does not match order.total_amount')) {
            return $this->failure('invalid_response', $reference);
        }

        return [
            'outcome'         => 'success',
            'state'           => 'processing',
            'order_reference' => $reference,
            'eligibility'     => null,
            'authorization'   => null,
            'payments'        => $payments,
            'error'           => null,
        ];
    }

    /**
     * Only processed/accredited with fully matching financial facts can approve.
     * A failed Order is final: one unpaid payment mirroring the Order rejection reason.
     * Authorization fields are never projected. Validated status facts, the first payment id and
     * its layer reference are projected in `observed` for server-side records only; they never
     * drive decisions and must not reach the browser.
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $expectedAmount
     * @return array<string, mixed>
     */
    private function normalizePpOrder(array $data, string $reference, ?array $expectedAmount): array
    {
        $status = $data['status'] ?? null;
        $detail = $data['status_detail'] ?? null;
        $approved = $status === 'processed' && $detail === 'accredited';
        $rejected = $status === 'failed' && $this->isRejectionDetail($detail);
        // Non-terminal states mirror on the payment and may still end processed or failed.
        // processing/waiting_transfer is not yet confirmed against GET /v1/orders. It was only
        // observed in a Core P&P notification envelope, whose payment still reported
        // action_required/waiting_transfer, so that payment status is accepted for this pair alone.
        $waitingTransfer = $status === 'processing' && $detail === 'waiting_transfer';
        $pending = ($status === 'action_required' && $detail === 'waiting_transfer')
            || ($status === 'processing' && $detail === 'in_process')
            || $waitingTransfer;
        if ($this->invalid(!$approved && !$rejected && !$pending, 'unsupported order status and status_detail: ' . SmartPixDiagnostic::statusFacts($data))) {
            return $this->failure('unknown', $reference);
        }
        $total = $this->decimal($data['total_amount'] ?? null);
        $paid = $this->decimal($data['total_paid_amount'] ?? null);
        // The failed total follows the total_paid_amount naming; it is optional, never malformed.
        $hasFailed = array_key_exists('total_failed_amount', $data);
        $failed = $hasFailed ? $this->decimal($data['total_failed_amount']) : null;
        $rawPayments = $data['payments'] ?? null;
        if (
            // A rejection fails the whole Order total; any other failed amount is contradictory.
            // Only the funding payment exists for a rejection; no settlement payment follows.
            $this->invalid(($data['type'] ?? null) !== 'online', 'order.type must be online')
            || $this->invalid(($data['site_id'] ?? null) !== 'MLB', 'order.site_id must be MLB')
            || $this->invalid(($data['currency'] ?? null) !== 'BRL', 'order.currency must be BRL')
            || $this->invalid(array_key_exists('transactions', $data), 'mixed order envelopes: payments and transactions')
            || $this->invalid((array_key_exists('country_code', $data) && $data['country_code'] !== 'BRA'), 'order.country_code must be BRA')
            || $this->invalid($total === null, 'order.total_amount is not a valid decimal string')
            || $this->invalid($total === '0.00', 'order.total_amount must be positive')
            || $this->invalid($paid === null, 'order.total_paid_amount is not a valid decimal string')
            || $this->invalid(($hasFailed && $failed === null), 'order.total_failed_amount is not a valid decimal string')
            || $this->invalid(($rejected && $hasFailed && $failed !== $total), 'rejected order.total_failed_amount must match total_amount')
            || $this->invalid(($approved && $paid !== $total), 'approved order.total_paid_amount must match total_amount')
            || $this->invalid(($rejected && $paid !== '0.00'), 'rejected order.total_paid_amount must be zero')
            || $this->invalid(($pending && $paid !== '0.00' && $paid !== $total), 'processing order.total_paid_amount must be zero or the order total')
            || $this->invalid(($expectedAmount !== null && ( ($expectedAmount['currency'] ?? null) !== 'BRL' || $this->decimal($expectedAmount['value'] ?? null) !== $total )), 'order amount or currency does not match the creation request')
            || $this->invalid(!is_array($rawPayments), 'order.payments must be an array')
            || $this->invalid($rawPayments === [], 'order.payments must not be empty')
            || $this->invalid(count($rawPayments) > 100, 'order.payments exceeds the 100-payment limit')
            || $this->invalid(array_keys($rawPayments) !== range(0, count($rawPayments) - 1), 'order.payments must be a sequential list')
            || $this->invalid(($rejected && count($rawPayments) !== 1), 'rejected order must contain exactly one payment')
        ) {
            return $this->failure('invalid_response', $reference);
        }
        $payments = [];
        $seen = [];
        $sum = '0.00';
        $paymentStatus = null;
        foreach ($rawPayments as $rawPayment) {
            if ($this->invalid(!is_array($rawPayment), 'order.payments entry must be an object')) {
                return $this->failure('invalid_response', $reference);
            }
            $id = $rawPayment['id'] ?? null;
            $amount = $this->decimal($rawPayment['amount'] ?? null);
            $paymentPaid = $this->decimal($rawPayment['paid_amount'] ?? null);
            $method = $rawPayment['payment_method'] ?? null;
            if (
                // A rejected payment may omit its paid amount, but never report a paid one.
                $this->invalid(!$this->isReference($id), 'payment.id is missing or invalid')
                || $this->invalid(in_array($id, $seen, true), 'duplicate payment.id')
                || $this->invalid($amount === null, 'payment.amount is not a valid decimal string')
                || $this->invalid($amount === '0.00', 'payment.amount must be positive')
                || $this->invalid(($approved && $paymentPaid !== $amount), 'approved payment.paid_amount must match payment.amount')
                || $this->invalid(($pending && $paymentPaid !== '0.00'), 'processing payment.paid_amount must be zero')
                || $this->invalid(($rejected && array_key_exists('paid_amount', $rawPayment) && $paymentPaid !== '0.00'), 'rejected payment.paid_amount must be zero or absent')
                || $this->invalid(!is_array($method), 'payment.payment_method must be an object')
                || $this->invalid(($method['id'] ?? null) !== 'smart_transfer', 'payment.payment_method.id must be smart_transfer')
                || $this->invalid(($method['type'] ?? null) !== 'smart_transfer', 'payment.payment_method.type must be smart_transfer')
            ) {
                return $this->failure('invalid_response', $reference);
            }
            // Refund lifecycle mappings are unconfirmed; never approve or project refunded payments.
            // Null fields are omitted by the provider, so a present null is malformed.
            $refunds = array_key_exists('refunds', $rawPayment) ? $rawPayment['refunds'] : [];
            if (
                $this->invalid(!is_array($refunds), 'payment.refunds must be an array')
                || $this->invalid(($refunds !== [] && array_keys($refunds) !== range(0, count($refunds) - 1)), 'payment.refunds must be a sequential list')
            ) {
                return $this->failure('invalid_response', $reference);
            }
            if ($this->invalid($refunds !== [], 'payment has refunds; creation/query normalizer cannot establish its lifecycle')) {
                return $this->failure('unknown', $reference);
            }
            // Every payment shares one status, so the observed payment facts stay unambiguous.
            $current = $rawPayment['status'] ?? null;
            if (
                $this->invalid(!in_array($current, $waitingTransfer ? [$status, 'action_required'] : [$status], true), 'payment.status does not match order.status')
                || $this->invalid(($paymentStatus !== null && $current !== $paymentStatus), 'payments have conflicting statuses')
                || $this->invalid(($rawPayment['status_detail'] ?? null) !== $detail, 'payment.status_detail does not match order.status_detail')
            ) {
                return $this->failure('unknown', $reference);
            }
            $paymentStatus = $current;
            $seen[] = $id;
            $sum = $this->add($sum, $amount);
            $payments[] = [
                'reference' => $id,
                'state' => $approved ? 'approved' : ($rejected ? 'rejected' : 'processing'),
                'amount' => ['value' => $amount, 'currency' => 'BRL'],
            ];
        }
        if ($this->invalid($sum !== $total, 'sum of payment amounts does not match order.total_amount')) {
            return $this->failure('invalid_response', $reference);
        }
        // Payments mirror the Order detail; only processing/waiting_transfer may differ in status.
        $observed = [
            'order_status' => $status, 'order_status_detail' => $detail,
            'payment_status' => $paymentStatus ?? $status, 'payment_status_detail' => $detail, 'total_failed' => $failed,
            'payment_id' => $rawPayments[0]['id'], 'payment_reference' => self::layerReference($rawPayments[0]),
        ];
        if ($rejected) {
            return [
                'outcome' => 'failure', 'state' => 'terminal_failure', 'order_reference' => $reference,
                'eligibility' => null, 'authorization' => null, 'payments' => $payments,
                'error' => self::rejectionError($detail), 'observed' => $observed,
            ];
        }
        return [
            'outcome' => 'success', 'state' => $approved ? 'terminal_success' : 'processing', 'order_reference' => $reference,
            'eligibility' => null, 'authorization' => null, 'payments' => $payments, 'error' => null,
            'observed' => $observed,
        ];
    }

    /**
     * Recover the closed rejection reason from a normalized terminal rejection error.
     * The result feeds SmartPixBuyerError::fromRejectionStatusDetail(); null means not a rejection.
     * @param mixed $error
     */
    public static function rejectionStatusDetail($error): ?string
    {
        if (
            !is_array($error) || count($error) !== 3
            || ($error['category'] ?? null) !== self::REJECTION_CATEGORY || ($error['retryable'] ?? null) !== false
            || !is_string($error['code'] ?? null)
            || preg_match('/^' . self::REJECTION_CODE_PREFIX . '([A-Z0-9_]{1,64})$/D', $error['code'], $parts) !== 1
        ) {
            return null;
        }
        return strtolower($parts[1]);
    }

    /**
     * The internal error contract is closed, so the bounded reason travels in the code.
     * @return array{category: string, code: string, retryable: bool}
     */
    private static function rejectionError(string $detail): array
    {
        return ['category' => self::REJECTION_CATEGORY, 'code' => self::REJECTION_CODE_PREFIX . strtoupper($detail), 'retryable' => false];
    }

    /**
     * Id of the first payment in the layer behind Core, chosen by its source as in the other
     * Orders integrations: `mp_order` -> `order_id`, `mp_payments` -> `payment_id`.
     * Informational only: an absent, empty or malformed value is null and never fails the response.
     * Lifecycle reads (SmartPixMutationNormalizer) project it the same way.
     *
     * @param array<string, mixed> $payment
     */
    public static function layerReference(array $payment): ?string
    {
        $references = $payment['references'] ?? null;
        $source = is_array($references) ? ($references['source'] ?? null) : null;
        $key = is_string($source) ? (['mp_order' => 'order_id', 'mp_payments' => 'payment_id'][$source] ?? null) : null;
        $value = $key === null ? null : ($references[$key] ?? null);
        return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $value) === 1 ? $value : null;
    }

    /** @param mixed $detail */
    private function isRejectionDetail($detail): bool
    {
        return is_string($detail) && preg_match('/^[a-z0-9_]{1,64}$/D', $detail) === 1;
    }

    /** @param mixed $value */
    private function decimal($value): ?string
    {
        if (
            !is_string($value) || strlen($value) > 64
            || preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2})0*)?$/D', $value, $parts) !== 1
        ) {
            return null;
        }
        return $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
    }

    /** Add canonical nonnegative amounts digit by digit without floats or integer overflow. */
    private function add(string $left, string $right): string
    {
        $left = str_replace('.', '', $left);
        $right = str_replace('.', '', $right);
        $length = max(strlen($left), strlen($right));
        $left = str_pad($left, $length, '0', STR_PAD_LEFT);
        $right = str_pad($right, $length, '0', STR_PAD_LEFT);
        $carry = 0;
        $result = '';
        for ($index = $length - 1; $index >= 0; $index--) {
            $digit = (int) $left[$index] + (int) $right[$index] + $carry;
            $result = (string) ($digit % 10) . $result;
            $carry = intdiv($digit, 10);
        }
        $result = str_pad(ltrim(($carry > 0 ? (string) $carry : '') . $result, '0'), 3, '0', STR_PAD_LEFT);
        return substr($result, 0, -2) . '.' . substr($result, -2);
    }

    /** @param mixed $reference */
    private function isReference($reference): bool
    {
        return is_string($reference)
            && strlen($reference) <= 128
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $reference) === 1;
    }

    /** @param mixed $amount */
    private function isPositiveAmount($amount): bool
    {
        return is_string($amount)
            && strlen($amount) <= 64
            && $amount !== '0.00'
            && preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D', $amount) === 1;
    }

    /** @return array<string, mixed> */
    private function failure(string $category, ?string $reference): array
    {
        return [
            'outcome'         => 'failure',
            'state'           => 'error',
            'order_reference' => $reference,
            'eligibility'     => null,
            'authorization'   => null,
            'payments'        => [],
            'error'           => [
                'category' => $category,
                'code' => 'SMART_PIX_' . strtoupper($category),
                'retryable' => false,
            ],
        ];
    }
}
