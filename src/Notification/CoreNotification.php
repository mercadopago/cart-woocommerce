<?php

namespace MercadoPago\Woocommerce\Notification;

use Exception;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Helpers\Device;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class CoreNotification extends AbstractNotification
{
    /**
     * Get Notification Id
     *
     * @return string
     */
    public function getNotificationId()
    {
        $body = json_decode(file_get_contents('php://input'));

        // For both Core and Bifrost. Core sends a complete object, but Bifrost sends only a string with the notification id
        if (is_object($body)) {
            return $body->notification_id;
        }

        return $body;
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
            $processedStatus = $this->getProcessedStatus($order, $data);

            $this->logs->file->info(
                sprintf(
                    'Changing order status from %s to %s',
                    $oldOrderStatus,
                    $this->orderStatus->mapMpStatusToWoocommerceStatus(str_replace('_', '', $processedStatus))
                ),
                __CLASS__
            );

            $this->processStatus($processedStatus, $order, $data);
        } catch (Exception $e) {
            $this->setResponse(422, $e->getMessage());
            $this->logs->file->error($e->getMessage(), __CLASS__, $data);
        }
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
            $payment_ids = array();

            foreach ($data['payments_details'] as $payment) {
                $payment_ids[] = $payment['id'];

                $this->updateMeta(
                    $order,
                    'Mercado Pago - Payment ' . $payment['id'],
                    '[Date ' . Date::getNowDate('Y-m-d H:i:s') .
                        ']/[Amount ' . $payment['total_amount'] .
                        ']/[Payment Type ' . $payment['payment_type_id'] .
                        ']/[Payment Method ' . $payment['payment_method_id'] .
                        ']/[Paid ' . $payment['paid_amount'] .
                        ']/[Coupon ' . $payment['coupon_amount'] .
                        ']/[Refund ' . $data['total_refunded'] . ']'
                );

                $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - payment_type', $payment['payment_type_id']);

                if (strpos($payment['payment_type_id'], 'card') !== false) {
                    $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - installments', $payment['payment_method_info']['installments']);
                    $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - installment_amount', $payment['payment_method_info']['installment_amount']);
                    $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - transaction_amount', $payment['total_amount']);
                    $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - total_paid_amount', $payment['paid_amount']);
                    $this->updateMeta($order, 'Mercado Pago - ' . $payment['id'] . ' - card_last_four_digits', $payment['payment_method_info']['last_four_digits']);
                }
            }

            if (count($payment_ids) != 0) {
                $this->updateMeta($order, '_Mercado_Pago_Payment_IDs', implode(', ', $payment_ids));
            }
        }

        $order->save();

        return $status;
    }
}
