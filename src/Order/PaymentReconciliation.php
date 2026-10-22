<?php

namespace MercadoPago\Woocommerce\Order;

use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use Throwable;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentReconciliation
{
    private const MERCHANT_ORDERS_SEARCH_ENDPOINT = '/merchant_orders/search?preference_id=';

    private const PAYMENTS_ENDPOINT = '/v1/payments/';

    private const KNOWN_PAYMENT_STATUSES = [
        'approved',
        'authorized',
        'cancelled',
        'charged_back',
        'in_mediation',
        'in_process',
        'pending',
        'refunded',
        'rejected',
    ];

    private const TERMINAL_NON_APPROVED_STATUSES = [
        'cancelled',
        'charged_back',
        'refunded',
        'rejected',
    ];

    private const CANCELABLE_PAYMENT_STATUSES = [
        'authorized',
        'in_process',
        'pending',
    ];

    private OrderMetadata $orderMetadata;

    private OrderStatus $orderStatus;

    private Requester $requester;

    private Logs $logs;

    public function __construct(
        OrderMetadata $orderMetadata,
        OrderStatus $orderStatus,
        Requester $requester,
        Logs $logs
    ) {
        $this->orderMetadata = $orderMetadata;
        $this->orderStatus = $orderStatus;
        $this->requester = $requester;
        $this->logs = $logs;
    }

    /**
     * Reconcile all known payment attempts before WooCommerce cancels an unpaid order.
     *
     * @param WC_Order $order
     * @param string $expectedExternalReference
     * @param string $usedGateway
     *
     * @return bool True when complete preference searches find no payment, or every correlated payment is terminal and not approved.
     */
    public function canCancelOrder(
        WC_Order $order,
        string $expectedExternalReference,
        string $usedGateway
    ): bool {
        try {
            $localPaymentIds = $this->orderMetadata->getPaymentIds($order);
            $preferenceIds = $this->orderMetadata->getPreferenceIds($order);

            if (empty($localPaymentIds) && empty($preferenceIds)) {
                return false;
            }

            $headers = ['Authorization: Bearer ' . $this->orderStatus->getAccessTokenForOrder($order)];
            $paymentIds = $localPaymentIds;

            foreach ($preferenceIds as $preferenceId) {
                $preferencePaymentIds = $this->findPaymentIdsByPreference(
                    $preferenceId,
                    $expectedExternalReference,
                    $headers
                );
                $paymentIds = array_merge($paymentIds, $preferencePaymentIds);
            }

            $paymentIds = array_values(array_unique($paymentIds));
            if (empty($paymentIds)) {
                // At least one persisted preference was searched completely and no payment exists yet.
                return true;
            }

            $payments = [];
            foreach ($paymentIds as $paymentId) {
                $payments[] = $this->getCorrelatedPayment(
                    $paymentId,
                    $expectedExternalReference,
                    $headers
                );
            }

            if ($this->processApprovedPaymentIfPresent($order, $payments, $usedGateway)) {
                return false;
            }

            $discoveredPaymentIds = array_diff($paymentIds, $localPaymentIds);
            if (!empty($discoveredPaymentIds) && !$this->orderMetadata->replacePaymentIds($order, $paymentIds)) {
                throw new \RuntimeException('Unable to persist discovered payment identifiers');
            }

            foreach ($payments as $index => $payment) {
                if (!in_array($payment['status'], self::CANCELABLE_PAYMENT_STATUSES, true)) {
                    continue;
                }

                $payments[$index] = $this->cancelCorrelatedPayment(
                    (string) $payment['id'],
                    $expectedExternalReference,
                    $headers
                );

                if ($this->processApprovedPaymentIfPresent($order, $payments, $usedGateway)) {
                    return false;
                }
            }

            return $this->allPaymentsAreTerminalAndNotApproved($payments);
        } catch (Throwable $exception) {
            $this->logs->file->error(
                'Mercado Pago: Payment reconciliation before cancellation was inconclusive: '
                . get_class($exception),
                __CLASS__
            );

            return false;
        }
    }

    /**
     * @return string[]
     */
    private function findPaymentIdsByPreference(
        string $preferenceId,
        string $expectedExternalReference,
        array $headers
    ): array {
        $response = $this->requester->get(
            self::MERCHANT_ORDERS_SEARCH_ENDPOINT . rawurlencode($preferenceId),
            $headers
        );

        if ($response->getStatus() !== 200) {
            throw new \RuntimeException('Merchant order search failed');
        }

        $data = (array) $response->getData();
        if (
            !array_key_exists('elements', $data)
            || !array_key_exists('total', $data)
        ) {
            throw new \RuntimeException('Invalid merchant order search response');
        }

        $total = $data['total'];
        if (
            (!is_int($total) && (!is_string($total) || !ctype_digit($total)))
            || filter_var($total, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false
        ) {
            throw new \RuntimeException('Invalid merchant order search response');
        }

        $total = (int) $total;
        if ($data['elements'] === null && $total === 0) {
            $data['elements'] = [];
        }

        if (!is_array($data['elements'])) {
            throw new \RuntimeException('Invalid merchant order search response');
        }

        if ($total !== count($data['elements'])) {
            throw new \RuntimeException('Incomplete merchant order search response');
        }

        $paymentIds = [];
        foreach ($data['elements'] as $merchantOrder) {
            if (!is_array($merchantOrder) && !is_object($merchantOrder)) {
                throw new \RuntimeException('Invalid merchant order');
            }

            $merchantOrder = (array) $merchantOrder;
            if (
                (string) ($merchantOrder['preference_id'] ?? '') !== $preferenceId
                || (string) ($merchantOrder['external_reference'] ?? '') !== $expectedExternalReference
            ) {
                throw new \RuntimeException('Merchant order correlation failed');
            }

            if (!isset($merchantOrder['payments']) || !is_array($merchantOrder['payments'])) {
                throw new \RuntimeException('Invalid merchant order payments');
            }

            foreach ($merchantOrder['payments'] as $payment) {
                if (!is_array($payment) && !is_object($payment)) {
                    throw new \RuntimeException('Invalid merchant order payment');
                }

                $paymentId = (string) (((array) $payment)['id'] ?? '');
                if (!ctype_digit($paymentId)) {
                    throw new \RuntimeException('Invalid merchant order payment identifier');
                }

                $paymentIds[$paymentId] = $paymentId;
            }
        }

        return array_values($paymentIds);
    }

    private function getCorrelatedPayment(
        string $paymentId,
        string $expectedExternalReference,
        array $headers
    ): array {
        if (!ctype_digit($paymentId)) {
            throw new \RuntimeException('Invalid payment identifier');
        }

        $response = $this->requester->get(self::PAYMENTS_ENDPOINT . $paymentId, $headers);
        if ($response->getStatus() !== 200) {
            throw new \RuntimeException('Payment lookup failed');
        }

        return $this->validateCorrelatedPayment(
            (array) $response->getData(),
            $paymentId,
            $expectedExternalReference
        );
    }

    private function cancelCorrelatedPayment(
        string $paymentId,
        string $expectedExternalReference,
        array $headers
    ): array {
        if (!ctype_digit($paymentId)) {
            throw new \RuntimeException('Invalid payment identifier');
        }

        $response = $this->requester->put(
            self::PAYMENTS_ENDPOINT . $paymentId,
            $headers,
            ['status' => 'cancelled']
        );
        if (!in_array($response->getStatus(), [200, 201], true)) {
            throw new \RuntimeException('Payment cancellation failed');
        }

        return $this->validateCorrelatedPayment(
            (array) $response->getData(),
            $paymentId,
            $expectedExternalReference
        );
    }

    private function validateCorrelatedPayment(
        array $payment,
        string $paymentId,
        string $expectedExternalReference
    ): array {
        $status = (string) ($payment['status'] ?? '');
        if (
            (string) ($payment['id'] ?? '') !== $paymentId
            || (string) ($payment['external_reference'] ?? '') !== $expectedExternalReference
            || !in_array($status, self::KNOWN_PAYMENT_STATUSES, true)
        ) {
            throw new \RuntimeException('Payment correlation failed');
        }

        if ($status === 'approved' && !$this->hasApprovedPaymentAccountingData($payment)) {
            throw new \RuntimeException('Approved payment response is incomplete');
        }

        return $payment;
    }

    private function processApprovedPaymentIfPresent(
        WC_Order $order,
        array $payments,
        string $usedGateway
    ): bool {
        $approvedPayment = $this->findApprovedPayment($payments);
        if ($approvedPayment === null) {
            return false;
        }

        if (!$this->orderMetadata->updateOrderCustomFieldsAfterReconciliation($order, $payments)) {
            throw new \RuntimeException('Unable to persist reconciled payment metadata');
        }

        $this->orderStatus->processStatus('approved', $approvedPayment, $order, $usedGateway);

        return true;
    }

    private function hasApprovedPaymentAccountingData(array $payment): bool
    {
        $transactionDetails = $payment['transaction_details'] ?? null;
        if (is_object($transactionDetails)) {
            $transactionDetails = (array) $transactionDetails;
        }

        return is_numeric($payment['transaction_amount'] ?? null)
            && is_array($transactionDetails)
            && is_numeric($transactionDetails['total_paid_amount'] ?? null)
            && is_string($payment['payment_type_id'] ?? null)
            && is_string($payment['payment_method_id'] ?? null)
            && is_numeric($payment['transaction_amount_refunded'] ?? null);
    }

    private function findApprovedPayment(array $payments): ?array
    {
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') === 'approved') {
                return $payment;
            }
        }

        return null;
    }

    private function allPaymentsAreTerminalAndNotApproved(array $payments): bool
    {
        foreach ($payments as $payment) {
            if (!in_array($payment['status'], self::TERMINAL_NON_APPROVED_STATUSES, true)) {
                return false;
            }
        }

        return true;
    }
}
