<?php

namespace MercadoPago\Woocommerce\Notification;

use Exception;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class CoreNotification extends AbstractNotification
{
    protected WoocommerceMercadoPago $mercadopago;

    private const REFUND_ORIGIN_PANEL = 'painel_woocommerce';
    private const REFUND_METRIC_SUCCESS_MP = 'mp_refund_success';
    private const REFUND_METRIC_ERROR_MP = 'mp_refund_error';
    private const REFUND_ORIGIN_MP = 'origin_mercadopago';

    /**
     * Get Notification Id
     *
     * @return string
     */
    public function getNotificationId()
    {
        $body = json_decode($this->getInput());

        // For both Core and Bifrost. Core sends a complete object, but Bifrost sends only a string with the notification id
        if (is_object($body)) {
            return $body->notification_id;
        }

        return $body;
    }

    /**
     * Get input from php://input
     *
     * @return string
     */
    protected function getInput(): string
    {
        return file_get_contents('php://input');
    }

    /**
     * Validate if ID is in the format P-12345 or M-12345 (or any number of digits)
     *
     * @return string
     */
    public function validateNotificationId($notification_id)
    {
        return preg_match('/^[PM]-\\d+$/', $notification_id) === 1;
    }

    /**
     * Get SDK instance
     */
    public function getSdkInstance(): Sdk
    {
        $platformId   = MP_PLATFORM_ID;
        $productId    = Device::getDeviceProductId();
        $integratorId = $this->store->getIntegratorId();
        $accessToken  = $this->seller->getCredentialsAccessToken();

        return new Sdk($accessToken, $platformId, $productId, $integratorId);
    }

    /**
     * Handle Notification Request
     *
     * @param $data
     *
     * @return void
     */
    public function handleReceivedNotification($data): void
    {
        parent::handleReceivedNotification($data);

        $sdkNotification = $this->getSdkInstance()->getNotificationInstance();
        $notification_id = $this->getNotificationId();

        if (!$this->validateNotificationId($notification_id)) {
            $message = 'Invalid notification id';
            $this->logs->file->error($message, __CLASS__, $data);
            $this->setResponse(400, $message);
            return;
        }

        try {
            $notificationEntity = $sdkNotification->read([
                'id' => $notification_id
            ]);

            $this->handleSuccessfulRequest($notificationEntity->toArray());
        } catch (Exception $e) {
            $this->logs->file->error($e->getMessage(), __CLASS__, $data);
            $this->setResponse(500, $e->getMessage());
        }
    }

    /**
     * Process success response
     *
     * @param mixed $data
     *
     * @return void
     */
    public function handleSuccessfulRequest($data)
    {
        try {
            $order           = parent::handleSuccessfulRequest($data);
            $oldOrderStatus  = $order->get_status();

            if ($this->isRefundNotification($data)) {
                $this->handleRefundNotification($order, $oldOrderStatus, $data);
                return;
            }

            $processedStatus = $this->getProcessedStatus($order, $data);
            $this->logStatusChange($oldOrderStatus, $processedStatus);
            $this->processStatus($processedStatus, $order, $data);
        } catch (Exception $e) {
            $this->setResponse(422, $e->getMessage());
            $this->logs->file->error($e->getMessage(), __CLASS__, $data);
        }
    }

    /**
     * Check if notification is for refund
     *
     * @param mixed $data
     *
     * @return bool
     */
    private function isRefundNotification($data): bool
    {
        return isset($data['refunds_notifying']) && is_array($data['refunds_notifying']);
    }

    /**
     * Handle refund notification
     *
     * @param WC_Order $order
     * @param string $oldOrderStatus
     * @param mixed $data
     *
     * @return void
     */
    private function handleRefundNotification(WC_Order $order, string $oldOrderStatus, $data): void
    {
        foreach ($data['refunds_notifying'] as $refund) {
            $data['current_refund'] = [];
            $refundId = $refund['id'] ?? null;

            if (!$this->isValidRefund($refund, $refundId, $data)) {
                continue;
            }

            foreach ($data['payments_details'] as $payment) {
                if (isset($payment['refunds'][$refundId])) {
                    $currentRefund = $payment['refunds'][$refundId];
                    break;
                }
            }

            $data['current_refund'] = array_merge($currentRefund, $refund);

            if ($this->shouldProcessRefund($currentRefund)) {
                $processedStatus = $this->getProcessedStatus($order, $data);
                $this->logStatusChange($oldOrderStatus, $processedStatus);
                $this->processStatus($processedStatus, $order, $data);

                $this->sendRefundSuccessMetric();
            } else {
                if (!empty($data['payments_details'])) {
                    $this->updatePaymentDetails($order, $data);
                    $order->save();
                }
            }
        }
    }

    /**
     * Validate refund data
     *
     * @param array $refund
     * @param string|null $refundId
     * @param mixed $data
     *
     * @return bool
     */
    private function isValidRefund($refund, $refundId, $data): bool
    {
        if (!$refundId) {
            $this->logs->file->error('Refund ID not found in notification', __CLASS__, $data);
            $this->sendRefundErrorMetric('validation_failed', 'Refund ID not found in notification');
            return false;
        }

        if (!isset($refund['amount']) || empty($refund['amount']) || $refund['amount'] <= 0.00) {
            $this->logs->file->error('Invalid refund amount: must be greater than 0', __CLASS__, $refund);
            $this->sendRefundErrorMetric('validation_failed', 'Invalid refund amount: must be greater than 0');
            return false;
        }

        if (!$this->isValidPaymentsDetailsStructure($data)) {
            $this->logs->file->error('Invalid payments_details structure in notification', __CLASS__, $data);
            $this->sendRefundErrorMetric('validation_failed', 'Invalid payments_details structure in notification');
            return false;
        }

        return true;
    }

    /**
     * Check if refund should be processed
     *
     * @param array|null $currentRefund
     *
     * @return bool
     */
    private function shouldProcessRefund($currentRefund): bool
    {
        return $currentRefund &&
               (!isset($currentRefund['metadata']['origin']) ||
                $currentRefund['metadata']['origin'] !== self::REFUND_ORIGIN_PANEL);
    }

    /**
     * Validate payments_details structure
     *
     * @param array $data
     *
     * @return bool
     */
    private function isValidPaymentsDetailsStructure(array $data): bool
    {
        return isset($data['payments_details']) &&
               is_array($data['payments_details']) &&
               !empty($data['payments_details']) &&
               isset($data['payments_details'][0]['refunds']) &&
               is_array($data['payments_details'][0]['refunds']);
    }

    /**
     * Process status
     *
     * @param WC_Order $order
     * @param mixed $data
     *
     * @return string
     */
    public function getProcessedStatus(WC_Order $order, $data): string
    {
        $status = $data['status'];

        if (!empty($data['payer']['email'])) {
            $this->updateMeta($order, 'Buyer email', $data['payer']['email']);
        }

        if (!empty($data['payments_details'])) {
            $this->updatePaymentDetails($order, $data);
        }

        $order->save();

        return $status;
    }

    /**
     * Update payment details metadata for order
     *
     * @param WC_Order $order
     * @param array $data
     *
     * @return void
     */
    public function updatePaymentDetails(WC_Order $order, array $data): void
    {
        $payment_ids = [];

        foreach ($data['payments_details'] as $payment) {
            $payment_ids[] = $payment['id'];

            $field = $order->get_meta(PaymentMetadata::getPaymentMetaKey($payment['id']));
            $paymentData = PaymentMetadata::extractPaymentDataFromMeta($field);

            $refundedAmount = $paymentData->refund ?? 0;

            if (isset($data['current_refund']) && isset($payment['refunds'][$data['current_refund']['id']])) {
                $refundedAmount += $data['current_refund']['amount'];
            }

            $this->updateMeta($order, PaymentMetadata::getPaymentMetaKey($payment['id']), PaymentMetadata::formatPaymentMetadata($payment, $refundedAmount));

            if (strpos($payment['payment_type_id'], 'card') !== false) {
                $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - installments', $payment['payment_method_info']['installments']);
                $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - installment_amount', $payment['payment_method_info']['installment_amount']);
                $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - transaction_amount', $payment['total_amount']);
                $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - total_paid_amount', $payment['paid_amount']);
                $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - card_last_four_digits', $payment['payment_method_info']['last_four_digits']);
            }
        }

        if (!isset($data['refunds_notifying'])) {
            $this->updateMeta($order, PaymentMetadata::PAYMENT_IDS_META_KEY, PaymentMetadata::joinPaymentIds($payment_ids));
        }
    }

    /**
    * Log status change
    *
    * @param string $oldStatus
    * @param string $newStatus
    *
    * @return void
    */
    private function logStatusChange(string $oldStatus, string $newStatus): void
    {
        $this->logs->file->info(
            sprintf(
                'Changing order status from %s to %s',
                $oldStatus,
                $this->orderStatus->mapMpStatusToWoocommerceStatus(str_replace('_', '', $newStatus))
            ),
            __CLASS__
        );
    }

    /**
     * Send refund success metric to Datadog
     */
    private function sendRefundSuccessMetric(): void
    {
        Datadog::getInstance()->sendEvent(self::REFUND_METRIC_SUCCESS_MP, 'refund_success', self::REFUND_ORIGIN_MP);
    }

    /**
     * Send refund error metric to Datadog
     *
     * @param string $errorCode
     * @param string $errorMessage
     */
    private function sendRefundErrorMetric(string $errorCode, string $errorMessage): void
    {
        Datadog::getInstance()->sendEvent(self::REFUND_METRIC_ERROR_MP, $errorCode, $errorMessage);
    }
}
