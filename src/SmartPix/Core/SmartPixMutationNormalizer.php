<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\SmartPixDiagnosticException;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor;

if (!defined('ABSPATH')) {
    exit;
}

/** Core P&P 1.14.0-test-4 Orders/refunds. GET is scoped to the authenticated collector by the API. */
final class SmartPixMutationNormalizer
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

    /** @param array<string, mixed> $data @param array<string, mixed> $request @return array<string, mixed> */
    public function normalize(array $data, array $request, string $authenticatedSeller): array
    {
        $this->diagnosticMessage = '';
        if (($data['status'] ?? null) === 'failed') {
            return $this->normalizeRejection($data, $request, $authenticatedSeller);
        }
        $total = SmartPixMoney::normalize($data['total_amount'] ?? null);
        $paid = SmartPixMoney::normalize($data['total_paid_amount'] ?? null);
        $payments = $data['payments'] ?? null;
        if (
            $this->invalid($authenticatedSeller === '', 'authenticated seller is missing')
            || $this->invalid(($data['id'] ?? null) !== $request['order_reference'], 'order.id does not match the linked order')
            || $this->invalid(($data['external_reference'] ?? null) !== $request['attempt_reference'], 'order.external_reference does not match the attempt')
            || $this->invalid(($data['site_id'] ?? null) !== 'MLB', 'order.site_id must be MLB')
            || $this->invalid(($data['currency'] ?? null) !== 'BRL', 'order.currency must be BRL')
            || $this->invalid(($data['type'] ?? null) !== 'online', 'order.type must be online')
            || $this->invalid($total === null, 'order.total_amount is not a valid decimal string')
            || $this->invalid($total === '0.00', 'order.total_amount must be positive')
            || $this->invalid($paid === null, 'order.total_paid_amount is not a valid decimal string')
            || $this->invalid(!is_array($payments), 'order.payments must be an array')
            || $this->invalid(array_keys($payments) !== [0], 'mutation snapshot requires exactly one payment')
            || $this->invalid(!is_array($payments[0]), 'payment entry must be an object')
        ) {
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        $payment = $payments[0];
        $paymentPaid = SmartPixMoney::normalize($payment['paid_amount'] ?? null);
        // Confirmed Smart Transfer expiration can retain the original aggregate total.
        // Only this exact single-payment contract may use the unpaid payment as authority.
        $expiredAggregate = ($data['status'] ?? null) === 'expired'
            && ($data['status_detail'] ?? null) === 'expired'
            && $paymentPaid === '0.00' && $paid === $total;
        if (
            $this->invalid(!$this->reference($payment['id'] ?? null), 'payment.id is missing or invalid')
            || $this->invalid(($payment['payment_method']['id'] ?? null) !== 'smart_transfer', 'payment.payment_method.id must be smart_transfer')
            || $this->invalid(($payment['payment_method']['type'] ?? null) !== 'smart_transfer', 'payment.payment_method.type must be smart_transfer')
            || $this->invalid(SmartPixMoney::normalize($payment['amount'] ?? null) !== $total, 'payment.amount does not match order.total_amount')
            || $this->invalid(($paymentPaid !== $paid && !$expiredAggregate), 'payment.paid_amount conflicts with order.total_paid_amount')
            || $this->invalid(($payment['status'] ?? null) !== ($data['status'] ?? null), 'payment.status does not match order.status')
            || $this->invalid(($payment['status_detail'] ?? null) !== ($data['status_detail'] ?? null), 'payment.status_detail does not match order.status_detail')
        ) {
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        $refunded = $this->refunded(array_key_exists('refunds', $payment) ? $payment['refunds'] : [], $payment['id']);
        if ($this->invalid(SmartPixMoney::compare($refunded, $total) > 0, 'sum of refunds exceeds order.total_amount')) {
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        $status = $data['status'];
        $detail = $data['status_detail'] ?? '';
        if (
            $this->invalid(!is_string($detail), 'order.status_detail must be a string')
            || $this->invalid(preg_match('/^[a-z0-9_]{1,64}$/D', $detail) !== 1, 'order.status_detail has an invalid format')
        ) {
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        if (
            $status === 'processed' && $paid === $total && (
            ($detail === 'accredited' && $refunded === '0.00')
            || ($detail === 'partially_refunded' && $refunded !== '0.00' && SmartPixMoney::compare($refunded, $total) < 0)
            )
        ) {
            $state = 'approved';
        } elseif ($status === 'refunded' && $refunded === $total && $paid === $total) {
            $state = 'refunded';
        } elseif (
            (($status === 'processing' && $detail === 'in_process')
                || ($status === 'action_required' && $detail === 'waiting_transfer'))
            && $paid === '0.00' && $refunded === '0.00'
        ) {
            $state = 'processing';
        } elseif (
            ($status === 'canceled' || ($status === 'expired' && $detail === 'expired'))
            && $paymentPaid === '0.00' && $refunded === '0.00'
        ) {
            $state = 'cancelled';
        } else {
            $this->diagnosticMessage = 'unsupported mutation state/paid/refunded combination: ' . SmartPixDiagnostic::statusFacts($data);
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        return [
            'outcome' => 'success',
            'state' => $state === 'processing' ? 'processing' : ($state === 'cancelled' ? 'terminal_failure' : 'terminal_success'),
            'order_reference' => $data['id'], 'eligibility' => null, 'authorization' => null,
            'payments' => [['reference' => $payment['id'], 'state' => $state, 'amount' => ['value' => $total, 'currency' => 'BRL']]],
            'error' => null,
            // Like the other Order reads, the payment id and its layer reference let a notification
            // complete the references of a PPORD linked without them, even once it expired.
            'observed' => [
                'order_status' => $status, 'order_status_detail' => $detail,
                'payment_status' => $payment['status'], 'payment_status_detail' => $payment['status_detail'],
                'total_failed' => null,
                'payment_id' => $payment['id'], 'payment_reference' => SmartPixOrderNormalizer::layerReference($payment),
            ],
            'order_context' => [
                'contract_version' => SmartPixMutationProcessor::SNAPSHOT_CONTRACT,
                'seller_reference' => $authenticatedSeller, 'attempt_reference' => $data['external_reference'],
                'amount' => ['value' => $total, 'currency' => 'BRL'], 'refunded_amount' => $refunded,
            ],
        ];
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $request @return array<string, mixed> */
    private function normalizeRejection(array $data, array $request, string $authenticatedSeller): array
    {
        if ($this->invalid($authenticatedSeller === '', 'authenticated seller is missing')) {
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        $normalizer = new SmartPixOrderNormalizer();
        $result = $normalizer->normalize($data, $request['order_reference'], null, $request['attempt_reference']);
        if (
            ($result['state'] ?? null) !== 'terminal_failure'
            || SmartPixOrderNormalizer::rejectionStatusDetail($result['error'] ?? null) === null
        ) {
            $this->diagnosticMessage = $normalizer->diagnosticMessage();
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        $result['order_context'] = [
            'contract_version' => SmartPixMutationProcessor::SNAPSHOT_CONTRACT,
            'seller_reference' => $authenticatedSeller,
            'attempt_reference' => $request['attempt_reference'],
            'amount' => $result['payments'][0]['amount'],
            'refunded_amount' => '0.00',
        ];
        return $result;
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $before */
    public function confirmsRefund(array $data, array $before, string $amount): bool
    {
        $this->diagnosticMessage = '';
        $payments = $data['payments'] ?? null;
        if (
            $this->invalid(($data['pp_order_id'] ?? null) !== $before['order_reference'], 'refund receipt.pp_order_id does not match the linked order')
            || $this->invalid(SmartPixMoney::normalize($data['total_amount'] ?? null) !== $amount, 'refund receipt.total_amount does not match the requested refund')
            || $this->invalid(!is_array($payments), 'refund receipt.payments must be an array')
            || $this->invalid(array_keys($payments) !== [0], 'refund receipt requires exactly one payment')
            || $this->invalid(!is_array($payments[0]), 'refund receipt payment must be an object')
        ) {
            return false;
        }
        $payment = $payments[0];
        return !(
            $this->invalid(($payment['pp_payment_id'] ?? null) !== $before['payments'][0]['reference'], 'refund receipt.pp_payment_id does not match the verified payment')
            || $this->invalid(($payment['status'] ?? null) !== 'processed', 'refund receipt payment.status must be processed')
            || $this->invalid(SmartPixMoney::normalize($payment['amount'] ?? null) !== $amount, 'refund receipt payment.amount does not match the requested refund')
            || $this->invalid(!$this->reference($payment['reference']['refund_order_id'] ?? null), 'refund receipt.refund_order_id is missing or invalid')
        );
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $receipt */
    public function confirmsRefundHistory(array $data, array $receipt, string $amount): bool
    {
        $this->diagnosticMessage = 'refund receipt.refund_order_id was not found in the refreshed refund history';
        $reference = $receipt['payments'][0]['reference']['refund_order_id'] ?? null;
        foreach ($data['payments'][0]['refunds'] ?? [] as $refund) {
            if (is_array($refund) && ($refund['references']['refund_order_id'] ?? null) === $reference) {
                $this->diagnosticMessage = ($refund['status'] ?? null) !== 'processed'
                    ? 'matching refund history entry.status must be processed'
                    : 'matching refund history entry.amount does not match the requested refund';
            }
            if (
                is_array($refund) && ($refund['references']['refund_order_id'] ?? null) === $reference
                && ($refund['status'] ?? null) === 'processed'
                && SmartPixMoney::normalize($refund['amount'] ?? null) === $amount
            ) {
                $this->diagnosticMessage = '';
                return true;
            }
        }
        return false;
    }

    /** @param mixed $refunds */
    private function refunded($refunds, string $paymentId): string
    {
        if (
            $this->invalid(!is_array($refunds), 'payment.refunds must be an array')
            || $this->invalid(count($refunds) > 1000, 'payment.refunds exceeds the 1000-refund limit')
            || $this->invalid(($refunds !== [] && array_keys($refunds) !== range(0, count($refunds) - 1)), 'payment.refunds must be a sequential list')
        ) {
            throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
        }
        $sum = '0.00';
        $seen = [];
        foreach ($refunds as $refund) {
            $amount = is_array($refund) ? SmartPixMoney::normalize($refund['amount'] ?? null) : null;
            $reference = $refund['references']['refund_order_id'] ?? null;
            if (
                $this->invalid($amount === null || $amount === '0.00', 'refund history entry.amount must be a positive decimal')
                || $this->invalid(!$this->reference($reference), 'refund history entry.references.refund_order_id is missing or invalid')
                || $this->invalid(($refund['transaction_id'] ?? null) !== $paymentId, 'refund history entry.transaction_id does not match the original payment')
                || $this->invalid(isset($seen[$reference]), 'refund history contains a duplicate refund_order_id')
                || $this->invalid(($refund['status'] ?? null) !== 'processed', 'refund history entry.status must be processed')
            ) {
                // Pending/failed refunds need explicit reconciliation before another financial operation.
                throw new SmartPixDiagnosticException('invalid_response', $this->diagnosticMessage);
            }
            $seen[$reference] = true;
            $sum = SmartPixMoney::add($sum, $amount);
        }
        return $sum;
    }

    /** @param mixed $value */
    private function reference($value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $value) === 1;
    }
}
