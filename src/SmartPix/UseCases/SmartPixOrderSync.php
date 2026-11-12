<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use RuntimeException;
use Throwable;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationIdentifiers;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixPaidStatus;

if (!defined('ABSPATH')) {
    exit;
}

/** Admin/cron reconciliation. Entrypoints own nonce/capability checks; never creates a payment. */
class SmartPixOrderSync
{
    private OrderMetadata $metadata;

    /** @var callable(WC_Order): array<string, mixed> */
    private $query;

    /** @var callable(int, string, array<string, mixed>, string, string): array<string, mixed> */
    private $apply;

    /** Display-only cache, scoped to this request; never used to decide native effects. @var array<int, array> */
    private array $display = [];

    public function __construct(OrderMetadata $metadata, callable $query, callable $apply)
    {
        $this->metadata = $metadata;
        $this->query = $query;
        $this->apply = $apply;
    }

    /** @return list<array<string, string>> */
    public function paymentData(WC_Order $order): array
    {
        try {
            $id = $order->get_id();
            if (!isset($this->display[$id])) {
                $this->display[$id] = $this->project($this->read($order));
            }
            return $this->display[$id];
        } catch (Throwable $failure) {
            throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
        }
    }

    public function synchronize(WC_Order $order): void
    {
        try {
            unset($this->display[$order->get_id()]);
            $result = $this->read($order);
            $key = $this->metadata->getIdempotencyKey($order);
            if (!is_string($key) || $key === '') {
                throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
            }
            if ($result['payments'][0]['state'] === 'processing') {
                // A pending read is a successful sync, not an unsupported terminal effect.
                $this->display[$order->get_id()] = $this->project($result);
                return;
            }
            if ($this->hasPartialRefundFacts($result)) {
                // Display reads stay read-only. Only explicit sync imports an external remainder.
                $order->get_data_store()->read($order);
                $order->read_meta_data(true);
                if (!$this->isValidPartialRefund($order, $result)) {
                    throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
                }
                $context = $result['refund_context'] ?? $result['order_context'];
                if (SmartPixMoney::normalize((string) $order->get_total_refunded()) === $context['refunded_amount']) {
                    $this->display[$order->get_id()] = $this->project($result);
                    return;
                }
                $result['refund_context'] = $context;
                unset($result['order_context']);
            }
            if ($result['payments'][0]['state'] === 'refunded' && !isset($result['refund_context'])) {
                // WooCommerce owns the existing native refund. Refresh its display, never recreate it.
                $order->get_data_store()->read($order);
                $order->read_meta_data(true);
                if (!$this->isConfirmedFullRefund($order, $result)) {
                    throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
                }
                $this->display[$order->get_id()] = $this->project($result);
                return;
            }
            $applied = ($this->apply)($order->get_id(), $key, $result, 'internal', 'server');
            if (!in_array($applied['outcome'] ?? null, ['applied', 'no_change'], true)) {
                throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
            }
            // The applicator uses fresh native objects. Do not leave cron/admin callers stale.
            $order->get_data_store()->read($order);
            $order->read_meta_data(true);
            if (
                $this->hasPartialRefundFacts($result)
                && (!$this->isValidPartialRefund($order, $result)
                    || SmartPixMoney::normalize((string) $order->get_total_refunded()) !== $result['refund_context']['refunded_amount'])
            ) {
                throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
            }
            $this->display[$order->get_id()] = $this->project($result);
        } catch (Throwable $failure) {
            throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
        }
    }

    /** @return array<string, mixed> */
    private function read(WC_Order $order): array
    {
        $order->read_meta_data(true);
        $reference = $this->metadata->getPpOrderId($order);
        if (
            $order->get_id() <= 0 || $order->get_payment_method('edit') !== PixGateway::ID
            || $order->get_currency('edit') !== 'BRL'
            || $this->metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            || !SmartPixCoreNotificationIdentifiers::isNotificationId($reference)
        ) {
            throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
        }
        $result = ($this->query)($order);
        $payments = $result['payments'] ?? null;
        $amount = SmartPixMoney::normalize($order->get_total('edit'));
        $providerRefunded = is_array($payments) && (($payments[0]['state'] ?? null) === 'refunded');
        if (
            $providerRefunded && $this->hasNativePartialRefund($order)
            && isset($result['order_context']) && !isset($result['refund_context'])
        ) {
            // The partial-refund read keeps the raw snapshot; a full provider refund is applied
            // through the same refund context as the ordinary read (the native remainder is created).
            $result['refund_context'] = $result['order_context'];
            unset($result['order_context']);
        }
        if (
            ($result['order_reference'] ?? null) !== $reference
            || (($result['outcome'] ?? null) !== 'success' && ($result['state'] ?? null) !== 'terminal_failure')
            || !is_array($payments) || array_keys($payments) !== [0]
            || !is_string($payments[0]['reference'] ?? null)
            || preg_match('/^PPPAY[A-Za-z0-9]+$/D', $payments[0]['reference']) !== 1
            || $amount === null || $amount === '0.00'
            || ($payments[0]['amount'] ?? null) !== ['value' => $amount, 'currency' => 'BRL']
            || !in_array($payments[0]['state'] ?? null, ['processing', 'approved', 'rejected', 'cancelled', 'refunded'], true)
            || (!$providerRefunded && $this->hasNativePartialRefund($order) && !$this->isValidPartialRefund($order, $result))
            || ($this->hasPartialRefundFacts($result) && !$this->isValidPartialRefund($order, $result))
            || ($payments[0]['state'] === 'refunded' && (!$this->isConfirmedProviderRefund($order, $result)
                || (!isset($result['refund_context']) && !$this->isConfirmedFullRefund($order, $result))))
        ) {
            throw new RuntimeException('Smart Pix synchronization could not be confirmed.');
        }
        return $result;
    }

    /** A verified provider partial may exceed the native balance; only sync imports the difference. */
    private function isValidPartialRefund(WC_Order $order, array $result): bool
    {
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        $native = SmartPixMoney::normalize((string) $order->get_total_refunded());
        $context = $result['refund_context'] ?? $result['order_context'] ?? null;
        $target = is_array($context) ? SmartPixMoney::normalize($context['refunded_amount'] ?? null) : null;
        return SmartPixPaidStatus::isPaid($order->get_status('edit'), $order->is_paid())
            && ($result['outcome'] ?? null) === 'success'
            && ($result['state'] ?? null) === 'terminal_success'
            && ($result['payments'][0]['state'] ?? null) === 'approved'
            && ($result['observed']['order_status'] ?? null) === 'processed'
            && ($result['observed']['order_status_detail'] ?? null) === 'partially_refunded'
            && ($result['observed']['payment_status'] ?? null) === 'processed'
            && ($result['observed']['payment_status_detail'] ?? null) === 'partially_refunded'
            && is_array($context)
            && ($context['contract_version'] ?? null) === SmartPixMutationProcessor::SNAPSHOT_CONTRACT
            && $total !== null && $total !== '0.00'
            && ($context['amount'] ?? null) === ['value' => $total, 'currency' => 'BRL']
            && $native !== null && $target !== null && $target !== '0.00'
            && $target === $context['refunded_amount']
            && SmartPixMoney::compare($target, $total) < 0
            && SmartPixMoney::compare($native, $target) <= 0
            && ($result['payments'][0]['reference'] ?? null) === $this->metadata->getPpPaymentId($order);
    }

    private function hasPartialRefundFacts(array $result): bool
    {
        return ($result['observed']['order_status_detail'] ?? null) === 'partially_refunded'
            || ($result['observed']['payment_status_detail'] ?? null) === 'partially_refunded'
            || (isset($result['refund_context']['refunded_amount'])
                && $result['refund_context']['refunded_amount'] !== '0.00'
                && ($result['payments'][0]['state'] ?? null) !== 'refunded')
            || (isset($result['order_context']['refunded_amount'])
                && $result['order_context']['refunded_amount'] !== '0.00'
                && ($result['payments'][0]['state'] ?? null) !== 'refunded');
    }

    private function hasNativePartialRefund(WC_Order $order): bool
    {
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        $refunded = SmartPixMoney::normalize((string) $order->get_total_refunded());
        return $total !== null && $refunded !== null && $refunded !== '0.00'
            && SmartPixMoney::compare($refunded, $total) < 0;
    }

    /** Only acknowledges an existing full native refund confirmed by authenticated Orders history. */
    private function isConfirmedFullRefund(WC_Order $order, array $result): bool
    {
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        return $order->get_status('edit') === 'refunded'
            && $total !== null && $total !== '0.00'
            && SmartPixMoney::normalize((string) $order->get_total_refunded()) === $total
            && $this->isConfirmedProviderRefund($order, $result);
    }

    private function isConfirmedProviderRefund(WC_Order $order, array $result): bool
    {
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        $context = $result['refund_context'] ?? $result['order_context'] ?? [];
        return $total !== null && $total !== '0.00'
            && ($context['contract_version'] ?? null) === SmartPixMutationProcessor::SNAPSHOT_CONTRACT
            && ($context['amount'] ?? null) === ['value' => $total, 'currency' => 'BRL']
            && ($context['refunded_amount'] ?? null) === $total
            && ($result['payments'][0]['reference'] ?? null) === $this->metadata->getPpPaymentId($order);
    }

    /** @param array<string, mixed> $result @return list<array<string, string>> */
    private function project(array $result): array
    {
        $statuses = [
            'processing' => ['pending', 'pending_waiting_payment'],
            'approved' => ['approved', 'accredited'],
            'rejected' => ['rejected', 'generic'],
            'cancelled' => ['canceled', 'canceled'],
            'refunded' => ['refunded', 'refunded'],
        ];
        [$status, $detail] = $statuses[$result['payments'][0]['state']];
        if ($status === 'approved' && $this->hasPartialRefundFacts($result)) {
            $detail = 'partially_refunded';
        }
        return [[
            'id' => $result['payments'][0]['reference'],
            'payment_type_id' => 'bank_transfer',
            'status' => $status,
            'status_detail' => $detail,
        ]];
    }
}
