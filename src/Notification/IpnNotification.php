<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\PP\Sdk\Common\AbstractCollection;
use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class IpnNotification extends AbstractNotification
{
    /**
     * @var Requester
     */
    public $requester;

    /**
     * IpnNotification constructor
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

        if (!isset($data['id']) || ! isset($data['topic'])) {
            $message = 'No ID or TOPIC param in Request IPN';
            $this->logs->file->error($message, __CLASS__, $data);
            $this->setResponse(422, $message);
        }

        if ($data['topic'] !== 'merchant_order') {
            $message = 'Discarded notification. This notification is already processed as webhook-payment';
            $this->setResponse(200, $message);
        }

        $merchantOrderId = preg_replace('/\D/', '', $data['id']);

        $headers  = ['Authorization: Bearer ' . $this->seller->getCredentialsAccessToken()];
        $response = $this->requester->get('/merchant_orders/' . $merchantOrderId, $headers);

        if ($response->getStatus() !== 200) {
            $message = 'IPN merchant order not found';
            $this->logs->file->error($message, __CLASS__, (array) $response->getData());
            $this->setResponse(422, $message);
        }

        $payments = $response->getData()['payments'];

        if (count($payments) == 0) {
            $message = 'Not found payments into merchant order';
            $this->logs->file->error($message, __CLASS__, $data);
            $this->setResponse(422, $message);
        }

        $response->getData()['ipn_type'] = 'merchant_order';

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
            $this->setResponse(200, 'Notification IPN Successfully');
        } catch (\Exception $e) {
            $this->setResponse(422, $e->getMessage());
            $this->logs->file->error($e->getMessage(), __CLASS__, $data);
        }
    }

    /**
     * Process status
     *
     * @param \WC_Order $order
     * @param $data
     *
     * @return string
     * @throws \Exception
     */
    public function getProcessedStatus(\WC_Order $order, $data): string
    {
        $status   = 'pending';
        $payments = $data['payments'];

        if (is_array($payments)) {
            $total       = (float) $data['shipping_cost'] + (float) $data['total_amount'];
            $totalPaid   = 0.00;
            $totalRefund = 0.00;

            foreach ($data['payments'] as $payment) {
                $coupon = $this->getPaymentInfo($payment['id']);

                if ($coupon > 0) {
                    $totalPaid += (float) $coupon;
                }

                if ($payment['status'] === 'approved') {
                    $totalPaid += (float) $payment['total_paid_amount'];
                } elseif ($payment['status'] === 'refunded') {
                    $totalRefund += (float) $payment['amount_refunded'];
                }
            }

            if ($totalPaid >= $total) {
                $status = 'approved';
            }

            if ($totalRefund >= $total) {
                $status = 'refunded';
            }
        }

        $this->updateMeta($order, '_used_gateway', 'WC_WooMercadoPago_Basic_Gateway');

        if (!empty($data['payer']['email'])) {
            $this->updateMeta($order, 'Buyer email', $data['payer']['email']);
        }

        if (!empty($data['payment_type_id'])) {
            $this->updateMeta($order, 'Payment type', $data['payment_type_id']);
        }

        if (!empty($data['payment_method_id'])) {
            $this->updateMeta($order, 'Payment method', $data['payment_method_id']);
        }

        if (!empty($data['payments'])) {
            $paymentIds = [];

            foreach ($data['payments'] as $payment) {
                $coupon     = $this->getPaymentInfo($payment['id'])['coupon_amount'];
                $paymentIds[] = $payment['id'];

                $this->updateMeta(
                    $order,
                    'Mercado Pago - Payment ' . $payment['id'],
                    '[Date ' . gmdate('Y-m-d H:i:s', strtotime($payment['date_created'])) .
                        ']/[Amount ' . $payment['transaction_amount'] .
                        ']/[Paid '   . $payment['total_paid_amount'] .
                        ']/[Coupon ' . $coupon .
                        ']/[Refund ' . $payment['amount_refunded'] . ']'
                );
            }

            if (count($paymentIds) != 0) {
                $this->updateMeta($order, '_Mercado_Pago_Payment_IDs', implode(', ', $paymentIds));
            }
        }

        $order->save();

        return $status;
    }

    /**
     * Get merchant order payment info
     *
     * @param string $id
     *
     * @return AbstractCollection|AbstractEntity|object|null
     * @throws \Exception
     */
    public function getPaymentInfo(string $id)
    {
        $headers  = ['Authorization: Bearer ' . $this->seller->getCredentialsAccessToken()];
        $response = $this->requester->get("/v1/payments/$id", $headers);

        return $response->getData();
    }
}
