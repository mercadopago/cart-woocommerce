<?php

namespace MercadoPago\Woocommerce\Order;

use Exception;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Numbers;
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

    private const PAYMENTS_ENDPOINT = '/v1/payments/';
    private const NOTIFICATION_ENDPOINT = '/v1/asgard/notification/';

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
        if (!$this->isNotification($data)) {
            return;
        }

        try {
            $paymentsData = $this->getAllPaymentsData($order);
            $refundStatus = $this->getRefundedStatusDetail($paymentsData);

            if ($refundStatus['description'] === 'partially_refunded') {
                if ($this->refundAlreadyProcessed($order, $paymentsData)) {
                    $this->logs->file->info('Mercado Pago: Refund already processed, skipping...', __CLASS__);
                    return;
                }

                $refundAmount = 0;
                $refundRatio = (float) ($order->get_meta(OrderMetadata::CURRENCY_RATIO) ?? 1);

                if (isset($data['current_refund']['id'])) {
                    $refundId = $data['current_refund']['id'];

                    $refund = array_filter($data['refunds_notifying'], function ($item) use ($refundId) {
                        return isset($item['id']) && $item['id'] == $refundId;
                    });
                    $refund = reset($refund);
                    if ($refund === false) {
                        throw new Exception('Refund not found for the given refund ID: ' . $refundId);
                    }
                    $refundAmount = floatval($refund['amount'] / $refundRatio);
                } else {
                    $refundAmount = $this->getUnprocessedRefundAmount($order, $paymentsData);
                    if ($refundAmount <= 0) {
                        $this->logs->file->info('Mercado Pago: No unprocessed refund amount found, skipping...', __CLASS__);
                        return;
                    }
                }

                wc_create_refund(array(
                    'amount'   => $refundAmount,
                    'reason'   => $this->translations['refunded'],
                    'order_id' => $order->get_id(),
                ));
                $order->add_order_note('Mercado Pago: ' . $this->translations['partial_refunded'] . Numbers::format($refundAmount));

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

    /**
     * Check if refund was already processed in WooCommerce
     *
     * @param WC_Order $order
     * @param array $paymentsData - Array of payment data from getAllPaymentsData()
     *
     * @return bool
     */
    private function refundAlreadyProcessed(WC_Order $order, array $paymentsData): bool
    {
        $totalRefundedWC = (float) $order->get_total_refunded();
        $totalRefundedMP = $this->calculateTotalRefunded($paymentsData, $order);

        return $totalRefundedMP <= $totalRefundedWC;
    }

    /**
     * Get amount of unprocessed refunds
     *
     * @param WC_Order $order
     * @param array $paymentsData - Array of payment data from getAllPaymentsData()
     *
     * @return float with currency ratio applied
     */
    private function getUnprocessedRefundAmount(WC_Order $order, array $paymentsData): float
    {
        $totalRefundedMP = $this->calculateTotalRefunded($paymentsData, $order);
        $totalRefundedWC = (float) $order->get_total_refunded();
        $convertedTotalRefundedMP = $order->get_meta(OrderMetadata::CURRENCY_RATIO)
            ? "(" . Numbers::format($totalRefundedMP * $order->get_meta(OrderMetadata::CURRENCY_RATIO)) . ")"
            : "";

        $unprocessedAmount = $totalRefundedMP - $totalRefundedWC;

        $this->logs->file->info(
            sprintf(
                'Mercado Pago: Refund comparison - MP: %s%s, WC: %s, Unprocessed: %s',
                Numbers::format($totalRefundedMP),
                $convertedTotalRefundedMP,
                Numbers::format($totalRefundedWC),
                Numbers::format($unprocessedAmount)
            ),
            __CLASS__
        );

        return max(0, $unprocessedAmount);
    }

    /**
     * Get all payments data from order
     *
     * @param WC_Order $order
     *
     * @return array
     */
    public function getAllPaymentsData(WC_Order $order): array
    {
        $result = [];
        $paymentsIds = array_filter(array_map(
            'trim',
            explode(',', $this->orderMetadata->getPaymentsIdMeta($order))
        ));

        $headers = ['Authorization: Bearer ' . $this->seller->getCredentialsAccessToken()];

        foreach ($paymentsIds as $paymentId) {
            $response = $this->requester->get(self::PAYMENTS_ENDPOINT . $paymentId, $headers);
            if ($response->getStatus() != 200 && $response->getStatus() != 201) {
                throw new Exception(json_encode($response->getData()));
            }

            $result[] = $response->getData();
        }

        return $result;
    }

    /**
     * Calculate total refunded from payments data array
     *
     * @param array $paymentsData - Array of payment data from getAllPaymentsData()
     *
     * @return float with currency ratio applied
     */
    private function calculateTotalRefunded(array $paymentsData, WC_Order $order): float
    {
        $totalRefunded = 0;

        foreach ($paymentsData as $payment) {
            $totalRefunded += (float) ($payment['transaction_amount_refunded'] ?? 0);
        }

        $currencyRatio = $order->get_meta(OrderMetadata::CURRENCY_RATIO);
        $currencyRatio = (!empty($currencyRatio) && is_numeric($currencyRatio)) ? (float) $currencyRatio : 1;

        return $totalRefunded / $currencyRatio;
    }

    /**
     * Get the last merchant's payment notification data or last payment's notification data
     *
     * @param WC_Order $order
     * @return array
     * @throws Exception
     */
    public function getLastNotification(WC_Order $order): array
    {
        $result = [];

        $paymentsIds = array_filter(array_map(
            'trim',
            explode(',', $this->orderMetadata->getPaymentsIdMeta($order))
        ));

        $headers = ['Authorization: Bearer ' . $this->seller->getCredentialsAccessToken()];

        try {
            $lastPaymentId = end($paymentsIds);
            $notificationId = $this->getNotificationId($lastPaymentId, $headers);
            $response = $this->requester->get(self::NOTIFICATION_ENDPOINT . $notificationId["id"], $headers);

            if ($response->getStatus() != 200 && $response->getStatus() != 201) {
                throw new Exception(json_encode($response->getData()));
            }

            $result[] = $response->getData();

            return $result;
        } catch (Exception $e) {
            $this->logs->file->error('Mercado Pago: Error getting last notification: ' . $e->getMessage(), __CLASS__);
            return [];
        }
    }

    /**
     * Get notification id from payment or merchant order ID
     *
     * @param string $paymentId
     * @param array $headers
     *
     * @return array
     */
    public function getNotificationId(string $paymentId, array $headers): array
    {
        $response = $this->requester->get(self::PAYMENTS_ENDPOINT . $paymentId, $headers);

        if ($response->getStatus() != 200 && $response->getStatus() != 201) {
            throw new Exception(json_encode($response->getData()));
        }

        $paymentData = $response->getData();

        if (!isset($paymentData["notification_url"]) || empty($paymentData["notification_url"])) {
            throw new Exception("Notification URL not found for payment ID: $paymentId");
        }

        if (strpos($paymentData["notification_url"], "merchant-order-notification") !== false) {
            $orderId = $paymentData["order"]["id"];
            return ["id" => "M-$orderId", "type" => "merchant_order"];
        }

        return ["id" => "P-$paymentId", "type" => "payment"];
    }
}
