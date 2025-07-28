<?php

namespace MercadoPago\Woocommerce\Order;

use Exception;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class OrderStatus
{
    private array $translations;

    private array $commonMessages;

    private OrderMetadata $orderMetadata;

    private Seller $seller;

    private Requester $requester;

    private Logs $logs;

    /**
     * Order constructor
     */
    public function __construct(
        StoreTranslations $storeTranslations,
        OrderMetadata $orderMetadata,
        Seller $seller,
        Requester $requester,
        Logs $logs
    ) {
        $this->translations   = $storeTranslations->orderStatus;
        $this->commonMessages = $storeTranslations->commonMessages;
        $this->orderMetadata  = $orderMetadata;
        $this->seller         = $seller;
        $this->requester      = $requester;
        $this->logs           = $logs;
    }

    /**
     * Set order status from/to
     *
     * @param WC_Order $order
     * @param string $fromStatus
     * @param string $toStatus
     *
     * @return void
     */
    public function setOrderStatus(WC_Order $order, string $fromStatus, string $toStatus): void
    {
        if ($order->get_status() === $fromStatus) {
            $order->set_status($toStatus);
            $order->save();
        }
    }

    /**
     * Get order status message
     *
     * @param string $statusDetail
     *
     * @return string
     */
    public function getOrderStatusMessage(string $statusDetail): string
    {
        if (isset($this->commonMessages['cho_' . $statusDetail])) {
            return $this->commonMessages['cho_' . $statusDetail];
        }
        return $this->commonMessages['cho_default'];
    }

    /**
     * Process order status
     *
     * @param string $processedStatus
     * @param array $data
     * @param WC_Order $order
     * @param string $usedGateway
     *
     * @return void
     * @throws Exception
     */
    public function processStatus(string $processedStatus, array $data, WC_Order $order, string $usedGateway): void
    {
        switch ($processedStatus) {
            case 'approved':
                $this->approvedFlow($data, $order);
                break;
            case 'pending':
                $this->pendingFlow($data, $order, $usedGateway);
                break;
            case 'in_process':
                $this->inProcessFlow($data, $order);
                break;
            case 'rejected':
                $this->rejectedFlow($data, $order);
                break;
            case 'refunded':
                $this->refundedFlow($data, $order);
                break;
            case 'cancelled':
                $this->cancelledFlow($data, $order);
                break;
            case 'in_mediation':
                $this->inMediationFlow($order);
                break;
            case 'charged_back':
                $this->chargedBackFlow($order);
                break;
            default:
                throw new Exception('Process Status - Invalid Status: ' . $processedStatus);
        }
    }

    /**
     * Rule of approved payment
     *
     * @param array $data
     * @param WC_Order $order
     *
     * @return void
     */
    private function approvedFlow(array $data, WC_Order $order): void
    {
        if (isset($data['status_detail']) && $data['status_detail'] === 'partially_refunded') {
            return;
        }

        $status = $order->get_status();

        if ($status === 'pending' || $status === 'on-hold' || $status === 'failed') {
            $order->add_order_note('Mercado Pago: ' . $this->translations['payment_approved']);
            $order->add_order_note('Mercado Pago: ' . $this->translations['payment_approved'], 1);

            $payment_completed_status = apply_filters(
                'woocommerce_payment_complete_order_status',
                $order->needs_processing() ? 'processing' : 'completed',
                $order->get_id(),
                $order
            );

            if (method_exists($order, 'get_status') && $order->get_status() !== 'completed') {
                $order->payment_complete();
                if ($payment_completed_status !== 'completed') {
                    $order->update_status(self::mapMpStatusToWoocommerceStatus('approved'));
                }
            }
        }
    }

    /**
     * Rule of pending
     *
     * @param array $data
     * @param WC_Order $order
     * @param string $usedGateway
     *
     * @return void
     */
    private function pendingFlow(array $data, WC_Order $order, string $usedGateway): void
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(self::mapMpStatusToWoocommerceStatus('pending'));

            switch ($usedGateway) {
                case 'MercadoPago\Woocommerce\Gateways\PixGateway':
                    $notes = $order->get_customer_order_notes();

                    if (count($notes) > 1) {
                        break;
                    }

                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending_pix']);
                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending_pix'], 1);
                    break;

                case 'MercadoPago\Woocommerce\Gateways\TicketGateway':
                    $notes = $order->get_customer_order_notes();

                    if (count($notes) > 1) {
                        break;
                    }

                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending_ticket']);
                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending_ticket'], 1);
                    break;

                default:
                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending']);
                    break;
            }
        } else {
            $this->validateOrderNoteType($data, $order, 'pending');
        }
    }

    /**
     * Rule of In Process
     *
     * @param array $data
     * @param WC_Order $order
     *
     * @return void
     */
    private function inProcessFlow(array $data, WC_Order $order): void
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::mapMpStatusToWoocommerceStatus('inprocess'),
                'Mercado Pago: ' . $this->translations['in_process']
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'in_process');
        }
    }

    /**
     * Rule of Rejected
     *
     * @param array $data
     * @param WC_Order $order
     *
     * @return void
     */
    private function rejectedFlow(array $data, WC_Order $order): void
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::mapMpStatusToWoocommerceStatus('rejected'),
                'Mercado Pago: ' . $this->translations['rejected']
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'rejected');
        }
    }

    /**
     * Rule of Refunded
     *
     * @param array $data
     * @param WC_Order $order
     *
     * @return void
     * @throws Exception
     */
    private function refundedFlow(array $data, WC_Order $order): void
    {
        // Validation for notification, the sync button does not yet query the notification API
        if (!$this->isNotification($data)) {
            return;
        }

        try {
            if ($this->isPartialRefund($data, $order)) {
                $refundId = $data['current_refund']['id'];

                // Find refund by ID in refunds_notifying array
                $refund = array_filter($data['refunds_notifying'], function ($item) use ($refundId) {
                    return isset($item['id']) && $item['id'] == $refundId;
                });
                $refund = reset($refund);
                if ($refund === false) {
                    throw new Exception('Refund not found for the given refund ID: ' . $refundId);
                }
                $refundAmount = floatval($refund['amount']);

                wc_create_refund(array(
                    'amount'   => $refundAmount,
                    'reason'   => $this->translations['refunded'],
                    'order_id' => $order->get_id(),
                ));
                $order->add_order_note('Mercado Pago: ' . $this->translations['partial_refunded'] . $refundAmount);
                return;
            }
        } catch (Exception $e) {
            $this->logs->file->error('Mercado Pago: Error processing refund validation: ' . $e->getMessage(), __CLASS__);
            return;
        }

        $order->update_status(
            self::mapMpStatusToWoocommerceStatus('refunded'),
            'Mercado Pago: ' . $this->translations['refunded']
        );
    }

    /**
     * Rule of Cancelled
     *
     * @param array $data
     * @param WC_Order $order
     *
     * @return void
     */
    private function cancelledFlow(array $data, WC_Order $order): void
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::mapMpStatusToWoocommerceStatus('cancelled'),
                'Mercado Pago: ' . $this->translations['cancelled']
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'cancelled');
        }
    }

    /**
     * Rule of In mediation
     *
     * @param WC_Order $order
     *
     * @return void
     */
    private function inMediationFlow(WC_Order $order): void
    {
        $order->update_status(self::mapMpStatusToWoocommerceStatus('inmediation'));
        $order->add_order_note('Mercado Pago: ' . $this->translations['in_mediation']);
    }

    /**
     * Rule of Charged back
     *
     * @param WC_Order $order
     *
     * @return void
     */
    private function chargedBackFlow(WC_Order $order): void
    {
        $order->update_status(self::mapMpStatusToWoocommerceStatus('chargedback'));
        $order->add_order_note('Mercado Pago: ' . $this->translations['charged_back']);
    }

    /**
     * Mercado Pago status
     *
     * @param string $mpStatus
     *
     * @return string
     */
    public static function mapMpStatusToWoocommerceStatus(string $mpStatus): string
    {
        $statusMap = array(
            'pending'     => 'pending',
            'approved'    => 'processing',
            'inprocess'   => 'on_hold',
            'inmediation' => 'on_hold',
            'rejected'    => 'failed',
            'cancelled'   => 'cancelled',
            'refunded'    => 'refunded',
            'chargedback' => 'refunded',
        );

        $status = $statusMap[$mpStatus];

        return str_replace('_', '-', $status);
    }

    /**
     * Can update order status?
     *
     * @param WC_Order $order
     *
     * @return bool
     */
    protected function canUpdateOrderStatus(WC_Order $order): bool
    {
        return method_exists($order, 'get_status') &&
            $order->get_status() !== 'completed' &&
            $order->get_status() !== 'processing';
    }

    /**
     * Validate Order Note by Type
     *
     * @param array $data
     * @param WC_Order $order
     * @param string $status
     *
     * @return void
     */
    protected function validateOrderNoteType(array $data, WC_Order $order, string $status): void
    {
        $paymentId = $data['id'];

        if (isset($data['ipn_type']) && $data['ipn_type'] === 'merchant_order') {
            $payments = array();

            foreach ($data['payments'] as $payment) {
                $payments[] = $payment['id'];
            }

            $paymentId = implode(',', $payments);
        }

        $order->add_order_note("Mercado Pago: {$this->translations['validate_order_1']} $paymentId {$this->translations['validate_order_1']} $status");
    }

    /**
     * Check if refund is partial
     *
     * @param array $data
     *
     * @return bool
     */
    private function isPartialRefund(array $data, WC_Order $order): bool
    {
        if ($this->isMerchantOrder($data)) {
            $paymentsData = $this->getPaymentsData($order);
            $refundedStatusDetail = $this->getRefundedStatusDetail($paymentsData);

            return $refundedStatusDetail['description'] === 'partially_refunded';
        }

        return $order->get_total() !== $data['transaction_amount_refunded'] && $data['transaction_amount_refunded'] !== 0;
    }

    /**
     * Check if the data is a notification
     *
     * @param array $data
     *
     * @return bool
     */
    private function isNotification(array $data): bool
    {
        return isset($data['notification_id']);
    }

    /**
     * Check if the data is a merchant order
     *
     * @param array $data
     *
     * @return bool
     */
    private function isMerchantOrder(array $data): bool
    {
        return isset($data['transaction_type']) && $data['transaction_type'] === 'merchant_order';
    }

    /**
     * Get the data of the payments
     *
     * @param WC_Order $order
     *
     * @return array
     */
    public function getPaymentsData(WC_Order $order): array
    {
        $result = [];

        $paymentsIds = array_filter(array_map(
            'trim',
            explode(',', $this->orderMetadata->getPaymentsIdMeta($order))
        ));

        $headers = ['Authorization: Bearer ' . $this->seller->getCredentialsAccessToken()];

        foreach ($paymentsIds as $paymentId) {
            $response = $this->requester->get("/v1/payments/$paymentId", $headers);
            if ($response->getStatus() != 200 && $response->getStatus() != 201) {
                throw new Exception(json_encode($response->getData()));
            }

            $result[] = $response->getData();
        }

        return $result;
    }

    /**
     * Get the status detail of the refunded payments for consolidated status
     *
     * @param array $paymentsData
     *
     * @return array
     */
    public function getRefundedStatusDetail(array $paymentsData): array
    {
        foreach ($paymentsData as $payment) {
            if (in_array('approved', [$payment['status'], $payment['status_detail']])) {
                return [
                    'title' => 'approved',
                    'description' => 'partially_refunded',
                ];
            }
        }

        return [
            'title' => 'refunded',
            'description' => 'refunded',
        ];
    }
}
