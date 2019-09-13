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
            header('HTTP/1.1 200 OK');
        }

        if (!isset($data['id']) || !isset($data['topic'])) {
            $this->log->write_log(__FUNCTION__, 'request failure, received ipn call with no data.');
            wp_die(__('The Mercado Pago request has failed', 'woocommerce-mercadopago'),'', array( 'response' => 422 ));
        }

        if ($data['topic'] == 'payment' || $data['topic'] != 'merchant_order') {
            $this->log->write_log(__FUNCTION__, 'request failure, invalid topic.');
            wp_die(__('The Mercado Pago request has failed', 'woocommerce-mercadopago'),'', array( 'response' => 422 ));
        }

        $access_token = array('access_token' => $this->mp->get_access_token());
        if ($data['topic'] == 'merchant_order') {
            $ipn_info = $this->mp->get('/merchant_orders/' . $data['id'], $access_token, false);

            if (is_wp_error($ipn_info) || ($ipn_info['status'] != 200 && $ipn_info['status'] != 201)) {
                $this->log->write_log(__FUNCTION__, 'got status not equal 200: ' . json_encode($ipn_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            $payments = $ipn_info['response']['payments'];
            if (sizeof($payments) >= 1) {
                $ipn_info['response']['ipn_type'] = 'merchant_order';
                do_action('valid_mercadopago_ipn_request', $ipn_info['response']);
            } else {
                $this->log->write_log(__FUNCTION__, 'order received but has no payment.');
            }
            header('HTTP/1.1 200 OK');
        }
    }

    /**
     * @param $data
     * @return bool|void|WC_Order|WC_Order_Refund
     * @throws WC_Data_Exception
     */
    public function successful_request($data)
    {
        $order = parent::successful_request($data);
        $processed_status = $this->process_status_mp_business($data, $order);
        $this->log->write_log(__FUNCTION__, 'Changing order status to: ' . parent::get_wc_status_for_mp_status(str_replace('_', '', $processed_status)));
        $this->proccessStatus($processed_status, $data, $order);
        $this->check_mercado_envios($data);
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

    /**
     * @param $merchant_order
     * @throws WC_Data_Exception
     */
    public function check_mercado_envios($merchant_order)
    {
        $order_key = $merchant_order['external_reference'];
        if (!empty($order_key)) {
            $invoice_prefix = get_option('_mp_store_identificator', 'WC-');
            $order_id = (int)str_replace($invoice_prefix, '', $order_key);
            $order = wc_get_order($order_id);
            if (count($merchant_order['shipments']) > 0) {
                foreach ($merchant_order['shipments'] as $shipment) {

                    $shipment_id = $shipment['id'];
                    $shipment_name = $shipment['shipping_option']['name'];
                    $shipment_cost = $shipment['shipping_option']['cost'];
                    $shipping_method_id = $shipment['shipping_option']['shipping_method_id'];

                    $shipping_meta = $order->get_items('shipping');
                    $order_item_shipping_id = null;
                    $method_id = null;
                    foreach ($shipping_meta as $key => $shipping) {
                        $order_item_shipping_id = $key;
                        $method_id = $shipping['method_id'];
                    }
                    $free_shipping_text = '';
                    $free_shipping_status = 'no';
                    if ($shipment_cost == 0) {
                        $free_shipping_status = 'yes';
                        $free_shipping_text = ' (' . __('Envío Gratuito', 'woocommerce') . ')';
                    }
                    // WooCommerce 3.0 or later.
                    if (method_exists($order, 'get_id')) {
                        $shipping_item = $order->get_item($order_item_shipping_id);
                        $shipping_item->set_order_id($order->get_id());
                        // Update shipping cost and method title.
                        $shipping_item->set_props(array(
                            'method_title' => 'Mercado Envios - ' . $shipment_name . $free_shipping_text,
                            'method_id' => $method_id,
                            'total' => wc_format_decimal($shipment_cost),
                        ));
                        $shipping_item->save();
                        $order->calculate_shipping();
                    } else {
                        $order->update_shipping($order_item_shipping_id, array(
                            'method_title' => 'Mercado Envios - ' . $shipment_name . $free_shipping_text,
                            'method_id' => $method_id,
                            'cost' => wc_format_decimal($shipment_cost)
                        ));
                    }
                    $order->set_total(wc_format_decimal($shipment_cost), 'shipping');
                    $order->set_total(
                        wc_format_decimal($order->get_subtotal())
                        + wc_format_decimal($order->get_total_shipping())
                        + wc_format_decimal($order->get_total_tax())
                        - wc_format_decimal($order->get_total_discount())
                    );
                    // Update additional info.
                    wc_update_order_item_meta($order_item_shipping_id, 'shipping_method_id', $shipping_method_id);
                    wc_update_order_item_meta($order_item_shipping_id, 'free_shipping', $free_shipping_status);
                    $access_token = $this->mp->get_access_token();
                    $request = array(
                        'uri' => '/shipments/' . $shipment_id,
                        'params' => array(
                            'access_token' => $access_token
                        )
                    );

                    $email = (wp_get_current_user()->ID != 0) ? wp_get_current_user()->user_email : null;
                    MeliRestClient::set_email($email);
                    $shipments_data = MeliRestClient::get($request);

                    switch ($shipments_data['response']['substatus']) {
                        case 'ready_to_print':
                            $substatus_description = __('Label ready to print', 'woocommerce-mercadopago');
                            break;
                        case 'printed':
                            $substatus_description = __('Label ready to print', 'woocommerce-mercadopago');
                            break;
                        case 'stale':
                            $substatus_description = __('Failed', 'woocommerce-mercadopago');
                            break;
                        case 'delayed':
                            $substatus_description = __('Delayed Shipping', 'woocommerce-mercadopago');
                            break;
                        case 'receiver_absent':
                            $substatus_description = __('Recipient absent for shipment', 'woocommerce-mercadopago');
                            break;
                        case 'returning_to_sender':
                            $substatus_description = __('Returning to the sender', 'woocommerce-mercadopago');
                            break;
                        case 'claimed_me':
                            $substatus_description = __('The buyer initiated a discussion and request a chargeback.', 'woocommerce-mercadopago');
                            break;
                        default:
                            $substatus_description = $shipments_data['response']['substatus'];
                            break;
                    }
                    if ($substatus_description == '') {
                        $substatus_description = $shipments_data['response']['status'];
                    }
                    $order->add_order_note('Mercado Envios: ' . $substatus_description);
                    $this->log->write_log(__FUNCTION__, 'Mercado Envios - shipments_data : ' . json_encode($shipments_data, JSON_PRETTY_PRINT));
                    update_post_meta($order_id, '_mercadoenvios_tracking_number', $shipments_data['response']['tracking_number']);
                    update_post_meta($order_id, '_mercadoenvios_shipment_id', $shipment_id);
                    update_post_meta($order_id, '_mercadoenvios_status', $shipments_data['response']['status']);
                    update_post_meta($order_id, '_mercadoenvios_substatus', $shipments_data['response']['substatus']);
                    $tracking_id = $shipments_data['response']['tracking_number'];
                    if (isset($order->billing_email) && isset($tracking_id)) {
                        $list_of_items = array();
                        $items = $order->get_items();
                        foreach ($items as $item) {
                            $product = new WC_product($item['product_id']);
                            if (method_exists($product, 'get_description')) {
                                $product_title = $product->get_name();
                            } else {
                                $product_title = $product->post->post_title;
                            }
                            array_push($list_of_items, $product_title . ' x ' . $item['qty']);
                        }
                        wp_mail(
                            $order->billing_email,
                            __('Order', 'woocommerce-mercadopago') . ' ' . $order_id . ' - ' . __('Mercado Envios Tracking ID', 'woocommerce-mercadopago'),
                            __('Hello,', 'woocommerce-mercadopago') . "\r\n\r\n" .
                            __('Your order', 'woocommerce-mercadopago') . ' ' . ' [ ' . implode(', ', $list_of_items) . ' ] ' .
                            __('made in', 'woocommerce-mercadopago') . ' ' . get_site_url() . ' ' .
                            __('He used the Shipping Market as his means of shipping.', 'woocommerce-mercadopago') . "\r\n" .
                            __('You can track it with the following tracking code:', 'woocommerce-mercadopago') . ' ' . $tracking_id . ".\r\n\r\n" .
                            __('Greetings.', 'woocommerce-mercadopago')
                        );
                    }
                }
            }
        }
    }


}
