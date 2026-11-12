<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Computes decisions without applying WooCommerce effects.
 * Approval and rejection consume normalized P&P facts; cancellation remains synthetic test evidence.
 */
final class SmartPixStateReducer
{
    /**
     * The caller supplies authoritative local facts and an already validated normalized
     * Orders response. This decision is separate from the provider response contract.
     * Unsupported transitions preserve the supplied status and propose no effects.
     *
     * @param bool $isPaid WooCommerce `is_paid()`; store-configured paid statuses count as paid.
     * @param string $refundedAmount Canonical nonnegative decimal with exactly two fractional digits.
     *     The caller adapts WooCommerce refunded totals before invoking this component.
     * @param array<string, mixed> $response
     * @param array<string, mixed>|null $expectedAmount Authoritative server total for terminal decisions.
     * @return array{decision: string, reason: string, previous_status: string, next_status: string|null, effects: array<string>}
     */
    public function reduce(
        string $currentStatus,
        bool $isPaid,
        string $refundedAmount,
        array $response,
        ?array $expectedAmount = null
    ): array {
        $isPending = ($response['outcome'] ?? null) === 'success'
            && ($response['state'] ?? null) === 'processing'
            && array_key_exists('error', $response)
            && $response['error'] === null;

        $decision = $this->preserve($currentStatus, 'unsupported', 'unsupported_transition');

        if ($isPending) {
            if (!$isPaid && $refundedAmount === '0.00' && in_array($currentStatus, ['pending', 'on-hold'], true)) {
                return $this->preserve($currentStatus, 'no_change', 'pending_preserved');
            }
            if (SmartPixPaidStatus::isPaid($currentStatus, $isPaid) && $refundedAmount === '0.00') {
                return $this->preserve($currentStatus, 'no_change', 'paid_state_preserved');
            }
            if (!$isPaid && $refundedAmount === '0.00' && in_array($currentStatus, ['failed', 'cancelled'], true)) {
                return $this->preserve($currentStatus, 'no_change', 'terminal_state_preserved');
            }
            return $decision;
        }

        $paymentState = $this->singleTerminalPaymentState($response);
        if ($paymentState === null) {
            return $decision;
        }
        if (
            !$this->isCanonicalAmount($expectedAmount['value'] ?? null)
            || $expectedAmount['value'] === '0.00'
            || ($expectedAmount['currency'] ?? null) !== 'BRL'
        ) {
            return $decision;
        }
        $amount = $response['payments'][0]['amount'] ?? null;
        if (
            !is_array($amount) || !$this->isCanonicalAmount($amount['value'] ?? null)
            || !is_string($amount['currency'] ?? null)
            || preg_match('/^[A-Z]{3}$/D', $amount['currency']) !== 1
        ) {
            return $decision;
        }
        if ($amount['currency'] !== 'BRL') {
            return $this->preserve($currentStatus, 'conflict', 'currency_mismatch');
        }
        if ($amount['value'] !== $expectedAmount['value']) {
            return $this->preserve($currentStatus, 'conflict', 'amount_mismatch');
        }
        if ($refundedAmount !== '0.00' || $currentStatus === 'refunded') {
            return $this->preserve($currentStatus, 'conflict', 'refund_context_conflict');
        }
        if (SmartPixPaidStatus::isPaid($currentStatus, $isPaid)) {
            return $paymentState === 'approved'
                ? $this->preserve($currentStatus, 'no_change', 'approval_already_applied')
                : $this->preserve($currentStatus, 'conflict', 'paid_state_conflict');
        }
        if ($isPaid || in_array($currentStatus, ['processing', 'completed'], true)) {
            return $this->preserve($currentStatus, 'conflict', 'local_state_conflict');
        }
        if (in_array($currentStatus, ['failed', 'cancelled'], true)) {
            $matches = ($currentStatus === 'failed' && $paymentState === 'rejected')
                || ($currentStatus === 'cancelled' && $paymentState === 'cancelled');
            return $matches
                ? $this->preserve($currentStatus, 'no_change', 'terminal_already_applied')
                : $this->preserve($currentStatus, 'conflict', 'terminal_state_conflict');
        }
        if (!in_array($currentStatus, ['pending', 'on-hold'], true)) {
            return $decision;
        }

        $decision['decision'] = 'propose_effects';
        if ($paymentState === 'approved') {
            $decision['reason'] = 'payment_approved';
            // Native payment completion determines the final fulfillment status.
            $decision['next_status'] = null;
            $decision['effects'] = ['complete_payment'];
        } else {
            $decision['reason'] = $paymentState === 'rejected' ? 'payment_rejected' : 'payment_cancelled';
            $decision['next_status'] = $paymentState === 'rejected' ? 'failed' : 'cancelled';
            $decision['effects'] = $paymentState === 'rejected' ? ['mark_failed'] : ['mark_cancelled'];
        }

        return $decision;
    }

    /** @param array<string, mixed> $response */
    private function singleTerminalPaymentState(array $response): ?string
    {
        $payments = $response['payments'] ?? null;
        if (
            !array_key_exists('error', $response) || !is_array($payments)
            || count($payments) !== 1 || array_keys($payments) !== [0]
            || !is_array($payments[0])
        ) {
            return null;
        }

        $state = $payments[0]['state'] ?? null;
        $outcome = $response['outcome'] ?? null;
        $orderState = $response['state'] ?? null;
        if ($state === 'approved' && $outcome === 'success' && $orderState === 'terminal_success' && $response['error'] === null) {
            return 'approved';
        }
        if ($state === 'rejected' && $outcome === 'failure' && $orderState === 'terminal_failure' && is_array($response['error'])) {
            return 'rejected';
        }
        if ($state === 'cancelled' && $outcome === 'success' && $orderState === 'terminal_failure' && $response['error'] === null) {
            return 'cancelled';
        }
        return null;
    }

    /** @param mixed $value */
    private function isCanonicalAmount($value): bool
    {
        return is_string($value) && strlen($value) <= 64
            && preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1;
    }

    /** @return array{decision: string, reason: string, previous_status: string, next_status: string|null, effects: array<string>} */
    private function preserve(string $status, string $decision, string $reason): array
    {
        return [
            'decision'        => $decision,
            'reason'          => $reason,
            'previous_status' => $status,
            'next_status'     => $status,
            'effects'         => [],
        ];
    }
}
