<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_Notification_IPN
 */
class WC_WooMercadoPago_Notification_IPN extends WC_WooMercadoPago_Notification_Abstract
{
    /**
     * WC_WooMercadoPago_Notification_IPN constructor.
     * @param $payment
     */
    public function __construct($payment)
    {
        parent::__construct($payment);
    }

    /**
     *  IPN
     */
    public function check_ipn_response()
    {
        parent::check_ipn_response();
        $data = $_GET;

        if (isset($data['data_id']) && isset($data['type'])) {
            status_header(200, "OK");
        }

        if (!isset($data['id']) || !isset($data['topic'])) {
            $this->log->write_log(__FUNCTION__, 'No ID or TOPIC param in Request IPN.');
            $this->setResponse(422, null, __('No ID or TOPIC param in Request IPN', 'woocommerce-mercadopago'));
        }

        if ($data['topic'] == 'payment' || $data['topic'] != 'merchant_order') {
            $this->log->write_log(__FUNCTION__, 'Type of topic IPN invalid, need to be merchant_order');
            $this->setResponse(422, null, __('Type of topic IPN invalid, need to be merchant_order', 'woocommerce-mercadopago'));
        }

        $access_token = array('access_token' => $this->mp->get_access_token());
        if ($data['topic'] == 'merchant_order') {
            $ipn_info = $this->mp->get('/merchant_orders/' . $data['id'], $access_token, false);

            if (is_wp_error($ipn_info) || ($ipn_info['status'] != 200 && $ipn_info['status'] != 201)) {
                $this->log->write_log(__FUNCTION__, ' IPN merchant_order not found ' . json_encode($ipn_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->setResponse(422, null, __('IPN merchant_order not found', 'woocommerce-mercadopago'));
            }

            $payments = $ipn_info['response']['payments'];
            if (sizeof($payments) < 1) {
                $this->log->write_log(__FUNCTION__, 'Not found Payments into Merchant_Order');
                $this->setResponse(422, null, __('Not found Payments into Merchant_Order', 'woocommerce-mercadopago'));
            }

            $ipn_info['response']['ipn_type'] = 'merchant_order';
            do_action('valid_mercadopago_ipn_request', $ipn_info['response']);
            $this->setResponse(200, "OK", "Notification IPN Successfull");
        }
    }

    /**
     * @param $data
     * @return bool|void|WC_Order|WC_Order_Refund
     * @throws WC_Data_Exception
     */
	public function successful_request($data)
	{
		try {
			$order = parent::successful_request($data);
			$processed_status = $this->process_status_mp_business($data, $order);
			$this->log->write_log(__FUNCTION__, 'Changing order status to: ' . parent::get_wc_status_for_mp_status(str_replace('_', '', $processed_status)));
			$this->proccessStatus($processed_status, $data, $order);
		} catch (Exception $e) {
            $this->setResponse(422,null, $e->getMessage());
			$this->log->write_log(__FUNCTION__, $e->getMessage());
		}
	}

    /**
     * @param $data
     * @param $order
     * @return string
     */
    public function process_status_mp_business($data, $order)
    {
        $status = 'pending';
        $payments = $data['payments'];
        if (sizeof($payments) == 1) {
            // If we have only one payment, just set status as its status
            $status = $payments[0]['status'];
        } elseif (sizeof($payments) > 1) {
            // However, if we have multiple payments, the overall payment have some rules...
            $total_paid = 0.00;
            $total_refund = 0.00;
            $total = $data['shipping_cost'] + $data['total_amount'];
            // Grab some information...
            foreach ($data['payments'] as $payment) {
                if ($payment['status'] === 'approved') {
                    // Get the total paid amount, considering only approved incomings.
                    $total_paid += (float)$payment['total_paid_amount'];
                } elseif ($payment['status'] === 'refunded') {
                    // Get the total refounded amount.
                    $total_refund += (float)$payment['amount_refunded'];
                }
            }
            if ($total_paid >= $total) {
                $status = 'approved';
            } elseif ($total_refund >= $total) {
                $status = 'refunded';
            } else {
                $status = 'pending';
            }
        }
        // WooCommerce 3.0 or later.
        if (method_exists($order, 'update_meta_data')) {
            // Updates the type of gateway.
            $order->update_meta_data('_used_gateway', 'WC_WooMercadoPago_BasicGateway');
            if (!empty($data['payer']['email'])) {
                $order->update_meta_data(__('Buyer email', 'woocommerce-mercadopago'), $data['payer']['email']);
            }
            if (!empty($data['payment_type_id'])) {
                $order->update_meta_data(__('Payment method', 'woocommerce-mercadopago'), $data['payment_type_id']);
            }
            if (!empty($data['payments'])) {
                $payment_ids = array();
                foreach ($data['payments'] as $payment) {
                    $payment_ids[] = $payment['id'];
                    $order->update_meta_data('Mercado Pago - Payment ' . $payment['id'],
                        '[Date ' . date('Y-m-d H:i:s', strtotime($payment['date_created'])) .
                        ']/[Amount ' . $payment['transaction_amount'] .
                        ']/[Paid ' . $payment['total_paid_amount'] .
                        ']/[Refund ' . $payment['amount_refunded'] . ']'
                    );
                }
                if (sizeof($payment_ids) > 0) {
                    $order->update_meta_data('_Mercado_Pago_Payment_IDs', implode(', ', $payment_ids));
                }
            }
            $order->save();
        } else {
            // Updates the type of gateway.
            update_post_meta($order->id, '_used_gateway', 'WC_WooMercadoPago_BasicGateway');
            if (!empty($data['payer']['email'])) {
                update_post_meta($order->id, __('Buyer email', 'woocommerce-mercadopago'), $data['payer']['email']);
            }
            if (!empty($data['payment_type_id'])) {
                update_post_meta($order->id, __('Payment method', 'woocommerce-mercadopago'), $data['payment_type_id']);
            }
            if (!empty($data['payments'])) {
                $payment_ids = array();
                foreach ($data['payments'] as $payment) {
                    $payment_ids[] = $payment['id'];
                    update_post_meta(
                        $order->id,
                        'Mercado Pago - Payment ' . $payment['id'],
                        '[Date ' . date('Y-m-d H:i:s', strtotime($payment['date_created'])) .
                        ']/[Amount ' . $payment['transaction_amount'] .
                        ']/[Paid ' . $payment['total_paid_amount'] .
                        ']/[Refund ' . $payment['amount_refunded'] . ']'
                    );
                }
                if (sizeof($payment_ids) > 0) {
                    update_post_meta($order->id, '_Mercado_Pago_Payment_IDs', implode(', ', $payment_ids));
                }
            }
        }
        return $status;
    }
}
