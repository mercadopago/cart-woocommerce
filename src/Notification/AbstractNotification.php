<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Interfaces\NotificationInterface;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractNotification implements NotificationInterface
{
    /**
     * @var MercadoPagoGatewayInterface
     */
    public $gateway;

    /**
     * @var Logs
     */
    public $logs;

    /**
     * @var OrderStatus
     */
    public $orderStatus;

    /**
     * @var Seller
     */
    public $seller;

    /**
     * @var Store
     */
    public $store;

    /**
     * AbstractNotification constructor
     *
     * @param MercadoPagoGatewayInterface $gateway
     * @param Logs $logs
     * @param OrderStatus $orderStatus
     * @param Seller $seller
     * @param Store $store
     */
    public function __construct(
        MercadoPagoGatewayInterface $gateway,
        Logs $logs,
        OrderStatus $orderStatus,
        Seller $seller,
        Store $store
    ) {
        $this->gateway     = $gateway;
        $this->logs        = $logs;
        $this->orderStatus = $orderStatus;
        $this->seller      = $seller;
        $this->store       = $store;
    }

    /**
     * Handle Notification Request
     *
     * @param mixed $data
     *
     * @return void
     */
    public function handleReceivedNotification($data): void
    {
        $this->logs->file->info('Received data content', __CLASS__, $data);
    }

    /**
     * Process successful request
     *
     * @param mixed $data
     *
     * @return bool|\WC_Order|\WC_Order_Refund
     */
    public function handleSuccessfulRequest($data)
    {
        $this->logs->file->info('Starting to process update...', __CLASS__);

        $order_key = $data['external_reference'];

        if (empty($order_key)) {
            $message = 'external_reference not found';
            $this->logs->file->error($message, __CLASS__, $data);
            $this->setResponse(422, $message);
        }

        $invoice_prefix = get_option('_mp_store_identificator', 'WC-');
        $id             = (int) str_replace($invoice_prefix, '', $order_key);
        $order          = wc_get_order($id);

        if (!$order) {
            $message = 'Order is invalid';
            $this->logs->file->error($message, __CLASS__, $data);
            $this->setResponse(422, $message);
        }

        if ($order->get_id() !== $id) {
            $message = 'Order error';
            $this->logs->file->error($message, __CLASS__, $order);
            $this->setResponse(422, $message);
        }

        $this->logs->file->info('Updating metadata and status with data', __CLASS__, $data);

        return $order;
    }

    /**
     * Process order status
     *
     * @param string $processedStatus
     * @param \WC_Order $order
     * @param mixed $data
     *
     * @return void
     * @throws \Exception
     */
    public function processStatus(string $processedStatus, \WC_Order $order, $data): void
    {
        $this->orderStatus->processStatus($processedStatus, $data, $order, get_class($this->gateway));
    }

    /**
     * Update order meta
     *
     * @param \WC_Order $order
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function updateMeta(\WC_Order $order, string $key, $value): void
    {
            $order->update_meta_data($key, $value);
    }

    /**
     * Set response
     *
     * @param int $status
     * @param string $message
     *
     * @return void
     */
    public function setResponse(int $status, string $message): void
    {
        $response = [
            'status'  => $status,
            'message' => $message,
        ];

        wp_send_json($response, $status);
    }
}
