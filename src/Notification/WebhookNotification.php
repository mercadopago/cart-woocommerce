<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookNotification extends AbstractNotification
{
    /**
     * @var Requester
     */
    public $requester;

    /**
     * WebhookNotification constructor
     *
     * @param MercadoPagoGatewayInterface $gateway
     * @param Logs $logs
     * @param OrderStatus $orderStatus
     * @param Seller $seller
     * @param Store $store
     * @param Requester $requester
     */
    public function __construct(
        MercadoPagoGatewayInterface $gateway,
        Logs $logs,
        OrderStatus $orderStatus,
        Seller $seller,
        Store $store,
        Requester $requester
    ) {
        parent::__construct($gateway, $logs, $orderStatus, $seller, $store);

        $this->requester = $requester;
    }

    /**
     * Handle Notification Request
     *
     * @param $data
     *
     * @return void
     * @throws \Exception
     */
    public function handleReceivedNotification($data): void
    {
        parent::handleReceivedNotification($data);

        if (!isset($data['data_id']) || !isset($data['type'])) {
            $message = 'data_id or type not set';
            $this->logs->file->error($message, __CLASS__, $data);

            if (!isset($data['id']) || !isset($data['topic'])) {
                $message = 'Mercado Pago request failure';
                $this->logs->file->error($message, __CLASS__, $data);
                $this->setResponse(422, $message);
            }
        }

        if ($data['type'] !== 'payment') {
            $message = 'Mercado Pago Invalid Requisition';
            $this->setResponse(422, $message);
        }

        $payment_id = preg_replace('/\D/', '', $data['data_id']);

        $headers  = ['Authorization: Bearer ' .  $this->seller->getCredentialsAccessToken()];
        $response = $this->requester->get("/v1/payments/$payment_id", $headers);

        if ($response->getStatus() !== 200) {
            $message = 'Error when processing received data';
            $this->logs->file->error($message, __CLASS__, (array) $response);
            $this->setResponse(422, $message);
        }

        $this->handleSuccessfulRequest($response->getData());
    }

    /**
     * Process success response
     *
     * @param mixed $data
     *
     * @return void
     */
    public function handleSuccessfulRequest($data): void
    {
        try {
            $order  = parent::handleSuccessfulRequest($data);
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
            $this->setResponse(200, 'Webhook Notification Successfully');
        } catch (\Exception $e) {
            $this->setResponse(422, $e->getMessage());
            $this->logs->file->error($e->getMessage(), __CLASS__);
        }
    }

    /**
     * Process status
     *
     * @param \WC_Order $order
     * @param mixed $data
     *
     * @return string
     */
    public function getProcessedStatus(\WC_Order $order, $data): string
    {
        $status        = $data['status'] ?? 'pending';
        $total_paid    = $data['transaction_details']['total_paid_amount'] ?? 0.00;
        $total_refund  = $data['transaction_amount_refunded'] ?? 0.00;
        $coupon_amount = $data['coupon_amount'] ?? 0.00;

        $this->updateMeta($order, '_used_gateway', get_class($this));

        if (!empty($data['payer']['email'])) {
            $this->updateMeta($order, 'Buyer email', $data['payer']['email']);
        }

        if (!empty($data['payment_type_id'])) {
            $this->updateMeta($order, 'Payment type', $data['payment_type_id']);
        }

        if (!empty($data['payment_method_id'])) {
            $this->updateMeta($order, 'Payment method', $data['payment_method_id']);
        }

        $this->updateMeta(
            $order,
            'Mercado Pago - Payment ' . $data['id'],
            '[Date ' . gmdate('Y-m-d H:i:s', strtotime($data['date_created'])) .
                ']/[Amount ' . $data['transaction_amount'] .
                ']/[Paid ' . $total_paid .
                ']/[Coupon ' . $coupon_amount .
                ']/[Refund ' . $total_refund . ']'
        );

        $this->updateMeta($order, '_Mercado_Pago_Payment_IDs', $data['id']);

        $order->save();

        return $status;
    }
}
