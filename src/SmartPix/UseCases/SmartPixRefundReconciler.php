<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use RuntimeException;
use Throwable;
use WC_Order;
use WC_Order_Refund;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixPaidStatus;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRefundContext;

if (!defined('ABSPATH')) {
    exit;
}

/** Reconciles provider refunds into native refunds. Never sends provider mutations. */
final class SmartPixRefundReconciler
{
    public const PARTIAL_IMPORT_META = '_mp_smart_pix_partial_refund_import';

    private OrderMetadata $metadata;
    private Seller $seller;
    private SmartPixOperationRepository $operations;

    public function __construct(OrderMetadata $metadata, Seller $seller, ?SmartPixOperationRepository $operations = null)
    {
        $this->metadata = $metadata;
        $this->seller = $seller;
        $this->operations = $operations ?? new SmartPixOperationRepository();
    }

    /** Read-only acknowledgement of an already matching native partial refund. */
    public function confirmPartial(WC_Order $order, string $key, array $response): array
    {
        try {
            $context = $this->partialContext($order, $key, $response);
            if (SmartPixMoney::normalize((string) $order->get_total_refunded()) !== $context['amount']) {
                throw new RuntimeException('refund_context_conflict');
            }
            return $this->result('no_change', 'refund_already_applied', $order->get_status('edit'));
        } catch (Throwable $failure) {
            return $this->result('uncertain', 'refund_reconciliation_unavailable');
        }
    }

    /** The shared operation journal owns partial imports, separately from payment approval ownership. */
    public function applyPartial(WC_Order $order, string $key, array $response): array
    {
        $reservation = null;
        $started = false;
        try {
            $context = $this->partialContext($order, $key, $response);
            try {
                // A previous import may have created its marked refund and then failed verification.
                $this->operations->reconcileUncertainPartialImport($order, $context);
            } catch (Throwable $recoveryFailure) {
                // Recovery is best-effort: the reservation below still fails closed on an unresolved journal.
            }
            $this->assertSettledNativeRefunds($order, $key, $context['total']);
            if (SmartPixMoney::normalize((string) $order->get_total_refunded()) === $context['amount']) {
                return $this->result('no_change', 'refund_already_applied', $order->get_status('edit'));
            }
            // The cumulative provider balance identifies this import, not a new financial refund.
            $reservation = $this->operations->reserve($order, 'provider_partial_refund_' . $context['amount'], $context);
            if ($reservation['dispatch'] !== 'yes') {
                return $this->result('uncertain', 'refund_operation_unresolved');
            }
            $order = $this->reload($order);
            if ($this->partialContext($order, $key, $response) !== $context) {
                throw new RuntimeException('refund_context_conflict');
            }
            $this->assertSettledNativeRefunds($order, $key, $context['total']);
            $native = SmartPixMoney::normalize((string) $order->get_total_refunded());
            $remaining = $native === null ? null : SmartPixMoney::subtract($context['amount'], $native);
            if ($remaining === null) {
                throw new RuntimeException('refund_context_conflict');
            }
            if ($remaining === '0.00') {
                $this->operations->finish($order, $reservation, 'succeeded');
                return $this->result('no_change', 'refund_already_applied', $order->get_status('edit'));
            }
            $started = true;
            /** @var mixed $refund Native hooks can return unexpected values. */
            $refund = wc_create_refund([
                'order_id' => $order->get_id(), 'amount' => $remaining,
                'reason' => __('Mercado Pago: payment refunded.', 'woocommerce-mercadopago'),
                'refund_payment' => false, 'restock_items' => false,
            ]);
            if ($refund instanceof \WP_Error) {
                // WooCommerce reports a failed creation; no native effect may be claimed.
                $started = false;
                throw new RuntimeException('native_refund_not_created');
            }
            if (
                !$refund instanceof WC_Order_Refund || $refund->get_id() <= 0
                || $refund->get_parent_id('edit') !== $order->get_id()
                || substr((string) $refund->get_total('edit'), 0, 1) !== '-'
                || SmartPixMoney::normalize(substr((string) $refund->get_total('edit'), 1)) !== $remaining
            ) {
                throw new RuntimeException('native_refund_not_verified');
            }
            // Distinguish these local-only imports from an admin's provisional refund object.
            $marker = ['attempt' => hash('sha256', $key), 'target' => $context['amount']];
            $refund->update_meta_data(self::PARTIAL_IMPORT_META, $marker);
            $refund->save_meta_data();
            $refund->read_meta_data(true);
            if ($refund->get_meta(self::PARTIAL_IMPORT_META, true, 'edit') !== $marker) {
                throw new RuntimeException('native_refund_not_verified');
            }
            $order = $this->reload($order);
            $this->assertSettledNativeRefunds($order, $key, $context['total']);
            if ($this->confirmPartial($order, $key, $response)['outcome'] !== 'no_change') {
                throw new RuntimeException('native_refund_not_verified');
            }
            $this->operations->finish($order, $reservation, 'succeeded');
            $reservation = null;
            $order->add_order_note(sprintf(
                __('Mercado Pago: partial refund of %s.', 'woocommerce-mercadopago'),
                $remaining . ' BRL'
            ));
            return $this->result('applied', 'native_refund_verified', $order->get_status('edit'));
        } catch (Throwable $failure) {
            if ($reservation !== null && $reservation['dispatch'] === 'yes') {
                try {
                    $this->operations->finish($order, $reservation, $started ? 'uncertain' : 'read_failed');
                } catch (Throwable $journalFailure) {
                    // An unresolved durable reservation denies concurrent/repeated native effects.
                }
            }
            return $this->result('uncertain', $started ? 'native_refund_not_verified' : 'refund_reconciliation_unavailable');
        }
    }

    /** WC_Order caches get_refunds(); a datastore read alone does not invalidate that list. */
    private function reload(WC_Order $order): WC_Order
    {
        $fresh = wc_get_order($order->get_id());
        if (!$fresh instanceof WC_Order || $fresh->get_id() !== $order->get_id()) {
            throw new RuntimeException('native_order_unavailable');
        }
        $fresh->get_data_store()->read($fresh);
        $fresh->read_meta_data(true);
        return $fresh;
    }

    /** Reject in-flight gateway refunds: WooCommerce saves them before the gateway is called. */
    private function assertSettledNativeRefunds(WC_Order $order, string $key, string $total): void
    {
        $native = SmartPixMoney::normalize((string) $order->get_total_refunded());
        $sum = '0.00';
        foreach ($order->get_refunds() as $refund) {
            if (
                !$refund instanceof WC_Order_Refund || $refund->get_id() <= 0
                || $refund->get_parent_id('edit') !== $order->get_id()
            ) {
                throw new RuntimeException('native_refund_unresolved');
            }
            $value = (string) $refund->get_total('edit');
            $amount = substr($value, 0, 1) === '-' ? SmartPixMoney::normalize(substr($value, 1)) : null;
            $sum = $amount === null ? null : SmartPixMoney::add($sum, $amount);
            if ($sum === null || $amount === '0.00') {
                throw new RuntimeException('native_refund_unresolved');
            }
            if ($refund->get_refunded_payment('edit') === true) {
                continue;
            }
            $marker = $refund->get_meta(self::PARTIAL_IMPORT_META, true, 'edit');
            // A manual refund (no gateway call) is final once saved. Only a gateway refund stamped
            // by SmartPixRefundContext may still be deleted, so only that one blocks the import.
            if (!is_array($marker) && !SmartPixRefundContext::isInFlight($refund)) {
                continue;
            }
            $target = is_array($marker) ? SmartPixMoney::normalize($marker['target'] ?? null) : null;
            if (
                !is_array($marker) || ($marker['attempt'] ?? null) !== hash('sha256', $key)
                || $target === null || $target !== $marker['target'] || $native === null
                || SmartPixMoney::compare($target, $amount) < 0 || SmartPixMoney::compare($target, $total) >= 0
                || SmartPixMoney::compare($target, $native) > 0
            ) {
                throw new RuntimeException('native_refund_unresolved');
            }
        }
        if ($sum !== $native) {
            throw new RuntimeException('native_refund_unresolved');
        }
    }

    private function partialContext(WC_Order $order, string $key, array $response): array
    {
        $snapshot = $response['refund_context'] ?? null;
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        $native = SmartPixMoney::normalize((string) $order->get_total_refunded());
        $target = is_array($snapshot) ? SmartPixMoney::normalize($snapshot['refunded_amount'] ?? null) : null;
        $seller = $this->seller->getCustIdFromAT();
        $reference = $this->metadata->getPpOrderId($order);
        $attempt = get_option('_mp_store_identificator', 'WC-') . $order->get_id();
        if (
            $order->get_id() <= 0 || $order->get_payment_method('edit') !== PixGateway::ID
            || $order->get_currency('edit') !== 'BRL' || $this->seller->getSiteId() !== 'MLB'
            || !is_string($seller) || $seller === ''
            || $this->metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            || $key === '' || $this->metadata->getIdempotencyKey($order) !== $key
            || !is_string($reference) || $reference === '' || ($response['order_reference'] ?? null) !== $reference
            || !is_array($snapshot) || ($snapshot['contract_version'] ?? null) !== SmartPixMutationProcessor::SNAPSHOT_CONTRACT
            || ($snapshot['seller_reference'] ?? null) !== $seller || ($snapshot['attempt_reference'] ?? null) !== $attempt
            || ($response['outcome'] ?? null) !== 'success' || ($response['state'] ?? null) !== 'terminal_success'
            || !array_key_exists('error', $response) || $response['error'] !== null
            || !is_array($response['payments'] ?? null) || array_keys($response['payments']) !== [0]
            || ($response['payments'][0]['state'] ?? null) !== 'approved'
            || ($response['observed']['order_status'] ?? null) !== 'processed'
            || ($response['observed']['payment_status'] ?? null) !== 'processed'
            || ($response['observed']['order_status_detail'] ?? null) !== 'partially_refunded'
            || ($response['observed']['payment_status_detail'] ?? null) !== 'partially_refunded'
            || $total === null || $total === '0.00' || ($snapshot['amount'] ?? null) !== ['value' => $total, 'currency' => 'BRL']
            || $native === null || $target === null || $target === '0.00' || $target !== $snapshot['refunded_amount']
            || SmartPixMoney::compare($target, $total) >= 0 || SmartPixMoney::compare($native, $target) > 0
            || ($response['payments'][0]['reference'] ?? null) !== $this->metadata->getPpPaymentId($order)
            || ($response['payments'][0]['amount'] ?? null) !== ['value' => $total, 'currency' => 'BRL']
            || !SmartPixPaidStatus::isPaid($order->get_status('edit'), $order->is_paid())
            || $order->get_date_paid('edit') === null
            || ($native !== '0.00' && $order->get_refunds() === [])
        ) {
            throw new RuntimeException('refund_context_conflict');
        }
        return ['operation' => 'native_partial_refund', 'attempt_key' => $key, 'order_reference' => $reference,
            'attempt_reference' => $attempt, 'seller' => $seller, 'total' => $total, 'amount' => $target, 'currency' => 'BRL'];
    }

    /** Caller holds native effect ownership; beforeEffect admits execution exactly once. */
    public function apply(WC_Order $order, string $key, array $response, callable $beforeEffect): array
    {
        $reservation = null;
        $started = false;
        try {
            $context = $this->context($order, $key, $response);
            if ($this->confirmed($order, $context['total'])) {
                return $this->result('no_change', 'refund_already_applied', 'refunded');
            }
            // A gateway refund still in progress is counted as refunded but may yet be deleted.
            $this->assertNoRefundInFlight($order);
            try {
                // The provider may reach the full refund before an interrupted partial import is retried.
                $this->operations->reconcileUncertainPartialImport($order, $context);
            } catch (Throwable $recoveryFailure) {
                // Recovery is best-effort: the check below still fails closed on an unresolved journal.
            }
            $this->operations->reconcileConfirmedFullRefund($order, $context);
            $reservation = $this->operations->reserve($order, 'provider_full_refund', $context);
            if ($reservation['dispatch'] !== 'yes') {
                return $this->result('uncertain', 'refund_operation_unresolved');
            }
            // An admin refund may have begun between the query and reservation.
            $order->get_data_store()->read($order);
            $order->read_meta_data(true);
            if ($this->context($order, $key, $response) !== $context) {
                throw new RuntimeException('refund_context_conflict');
            }
            $this->assertNoRefundInFlight($order);
            $local = SmartPixMoney::normalize((string) $order->get_total_refunded());
            $remaining = $local === null ? null : SmartPixMoney::subtract($context['total'], $local);
            if ($remaining === null || $remaining === '0.00') {
                throw new RuntimeException('refund_context_conflict');
            }
            if (!$beforeEffect()) {
                throw new RuntimeException('ownership_start_not_verified');
            }
            $started = true;
            $refund = wc_create_refund([
                'order_id' => $order->get_id(), 'amount' => $remaining,
                'reason' => __('Mercado Pago: payment refunded.', 'woocommerce-mercadopago'),
                'refund_payment' => false, 'restock_items' => false,
            ]);
            if (
                !$refund instanceof WC_Order_Refund || $refund->get_id() <= 0
                || $refund->get_parent_id('edit') !== $order->get_id()
                || SmartPixMoney::normalize(substr((string) $refund->get_total('edit'), 1)) !== $remaining
                || substr((string) $refund->get_total('edit'), 0, 1) !== '-'
            ) {
                throw new RuntimeException('native_refund_not_verified');
            }
            $order->get_data_store()->read($order);
            $order->read_meta_data(true);
            if ($this->context($order, $key, $response) !== $context || !$this->confirmed($order, $context['total'])) {
                throw new RuntimeException('native_refund_not_verified');
            }
            $this->operations->finish($order, $reservation, 'succeeded');
            return $this->result('applied', 'native_refund_verified', 'refunded');
        } catch (Throwable $failure) {
            if ($reservation !== null && $reservation['dispatch'] === 'yes') {
                try {
                    $this->operations->finish($order, $reservation, $started ? 'uncertain' : 'read_failed');
                } catch (Throwable $journalFailure) {
                    // An unresolved durable reservation prevents another financial operation.
                }
            }
            return $this->result('uncertain', $started ? 'native_refund_not_verified' : 'refund_reconciliation_unavailable');
        }
    }

    private function context(WC_Order $order, string $key, array $response): array
    {
        $snapshot = $response['refund_context'] ?? null;
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        $reference = $this->metadata->getPpOrderId($order);
        $seller = $this->seller->getCustIdFromAT();
        $attempt = get_option('_mp_store_identificator', 'WC-') . $order->get_id();
        if (
            $order->get_payment_method('edit') !== PixGateway::ID || $order->get_currency('edit') !== 'BRL'
            || $this->seller->getSiteId() !== 'MLB' || !is_string($seller) || $seller === ''
            || $this->metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            || $key === '' || $this->metadata->getIdempotencyKey($order) !== $key
            || !is_string($reference) || $reference === '' || ($response['order_reference'] ?? null) !== $reference
            || !is_array($snapshot) || ($snapshot['contract_version'] ?? null) !== SmartPixMutationProcessor::SNAPSHOT_CONTRACT
            || ($snapshot['seller_reference'] ?? null) !== $seller || ($snapshot['attempt_reference'] ?? null) !== $attempt
            || $total === null || $total === '0.00' || ($snapshot['amount'] ?? null) !== ['value' => $total, 'currency' => 'BRL']
            || ($snapshot['refunded_amount'] ?? null) !== $total
            || ($response['outcome'] ?? null) !== 'success' || ($response['state'] ?? null) !== 'terminal_success'
            || !array_key_exists('error', $response) || $response['error'] !== null
            || !is_array($response['payments'] ?? null) || array_keys($response['payments']) !== [0]
            || ($response['payments'][0]['state'] ?? null) !== 'refunded'
            || ($response['payments'][0]['amount'] ?? null) !== ['value' => $total, 'currency' => 'BRL']
            || ($response['payments'][0]['reference'] ?? null) !== $this->metadata->getPpPaymentId($order)
            || (!in_array($order->get_status('edit'), ['pending', 'on-hold', 'processing', 'completed', 'refunded'], true)
                && !SmartPixPaidStatus::isPaid($order->get_status('edit'), $order->is_paid()))
        ) {
            throw new RuntimeException('refund_context_conflict');
        }
        return [
            'operation' => 'refund', 'attempt_key' => $key, 'order_reference' => $reference,
            'attempt_reference' => $attempt, 'seller' => $seller,
            'total' => $total, 'amount' => $total, 'currency' => 'BRL',
        ];
    }

    /**
     * Queries refunds directly: get_refunds() is cached per request and could miss one saved
     * concurrently. Refusing before the native effect keeps the reservation retryable.
     */
    private function assertNoRefundInFlight(WC_Order $order): void
    {
        /** @var mixed $refunds The stub types every result as WC_Order, not WC_Order_Refund. */
        $refunds = wc_get_orders([
            'type' => 'shop_order_refund', 'parent' => $order->get_id(), 'limit' => -1,
        ]);
        if (!is_array($refunds)) {
            throw new RuntimeException('refund_read_failed');
        }
        foreach ($refunds as $refund) {
            if (!$refund instanceof WC_Order_Refund || SmartPixRefundContext::isInFlight($refund)) {
                throw new RuntimeException('refund_in_flight');
            }
        }
    }

    /** Reads CRUD facts that wc_create_refund and data-store refresh change in this request.
     * @phpstan-impure
     */
    private function confirmed(WC_Order $order, string $total): bool
    {
        return $order->get_status('edit') === 'refunded'
            && SmartPixMoney::normalize((string) $order->get_total_refunded()) === $total;
    }

    private function result(string $outcome, string $reason, ?string $status = null): array
    {
        return ['outcome' => $outcome, 'reason' => $reason, 'status' => $status];
    }
}
