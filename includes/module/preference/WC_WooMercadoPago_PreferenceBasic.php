<?php
/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

class WC_WooMercadoPago_PreferenceBasic extends WC_WooMercadoPago_PreferenceAbstract
{
    /**
     * WC_WooMercadoPago_PreferenceBasic constructor.
     * @param $payment
     * @param $order
     */
    public function __construct($payment, $order)
    {
        parent::__construct($payment, $order);
        $this->preference = $this->make_commum_preference();
        $this->preference['items'] = $this->items;
        $this->preference['payer'] = $this->get_payer_basic();
        $this->preference['back_urls'] = $this->get_back_urls();
        $this->preference['shipments'] = $this->shipments_receiver_address();

        if (strpos($this->selected_shipping, 'Mercado Envios') !== 0 && $this->ship_cost > 0) {
            $this->preference['items'][] = $this->ship_cost_item();
        }

        if (strpos($this->selected_shipping, 'Mercado Envios') === 0 && $this->ship_cost > 0) {
            $this->shipment_info();
        }

        $this->preference['payment_methods'] = $this->get_payment_methods($this->ex_payments, $this->installments);
        $this->preference['auto_return'] = $this->auto_return();

        $internal_metadata = parent::get_internal_metadata();
        $internal_metadata = $this->get_internal_metadata_basic($internal_metadata);
        $this->preference['metadata'] = $internal_metadata;

    }

    /**
     * @return array
     */
    public function get_payer_basic()
    {
        $payer_additional_info = array(
            'name' => (method_exists($this->order, 'get_id') ? html_entity_decode($this->order->get_billing_first_name()) : html_entity_decode($this->order->billing_first_name)),
            'surname' => (method_exists($this->order, 'get_id') ? html_entity_decode($this->order->get_billing_last_name()) : html_entity_decode($this->order->billing_last_name)),
            'email' => $this->order->get_billing_email(),
            'phone' => array(
                //'area_code' =>
                'number' => (method_exists($this->order, 'get_id') ? $this->order->get_billing_phone() : $this->order->billing_phone)
            ),
            'address' => array(
                'zip_code' => (method_exists($this->order, 'get_id') ? $this->order->get_billing_postcode() : $this->order->billing_postcode),
                //'street_number' =>
                'street_name' => html_entity_decode(
                    method_exists($this->order, 'get_id') ?
                        $this->order->get_billing_address_1() . ' / ' .
                        $this->order->get_billing_city() . ' ' .
                        $this->order->get_billing_state() . ' ' .
                        $this->order->get_billing_country() : $this->order->billing_address_1 . ' / ' .
                        $this->order->billing_city . ' ' .
                        $this->order->billing_state . ' ' .
                        $this->order->billing_country
                )
            )
        );
        return $payer_additional_info;
    }

    /**
     * @return array
     */
    public function get_back_urls()
    {
        $success_url = get_option('success_url', '');
        $failure_url = get_option('failure_url', '');
        $pending_url = get_option('pending_url', '');
        $back_urls = array(
            'success' => empty($success_url) ?
                WC_WooMercadoPago_Module::fix_url_ampersand(
                    esc_url($this->get_return_url($this->order))
                ) : $this->success_url,
            'failure' => empty($failure_url) ?
                WC_WooMercadoPago_Module::fix_url_ampersand(
                    esc_url($this->order->get_cancel_order_url())
                ) : $failure_url,
            'pending' => empty($pending_url) ?
                WC_WooMercadoPago_Module::fix_url_ampersand(
                    esc_url($this->get_return_url($this->order))
                ) : $pending_url
        );
        return $back_urls;
    }

    /**
     * @param $ex_payments
     * @param $installments
     * @return array
     */
    public function get_payment_methods($ex_payments, $installments)
    {
        $excluded_payment_methods = array();
        if (is_array($ex_payments) && count($ex_payments) != 0) {
            foreach ($ex_payments as $excluded) {
                array_push($excluded_payment_methods, array(
                    'id' => $excluded
                ));
            }
        }
        $payment_methods = array(
            'installments' => (int)$installments,
            'default_installments' => 1,
            'excluded_payment_methods' => $excluded_payment_methods
        );
        return $payment_methods;
    }

    /**
     * @return string|void
     */
    public function auto_return()
    {
        $auto_return = get_option('auto_return', 'yes');
        if ('yes' == $auto_return) {
            return 'approved';
        }
        return;
    }

    /**
     * Shipment Info
     */
    public function shipment_info()
    {
        $this->preference['shipments']['mode'] = 'me2';
        foreach ($this->order->get_shipping_methods() as $shipping) {
            $this->preference['shipments']['dimensions'] = $shipping['dimensions'];
            $this->preference['shipments']['default_shipping_method'] = (int)$shipping['shipping_method_id'];
            $this->preference['shipments']['free_methods'] = array();
            // Get shipping method id.
            $prepare_method_id = explode(':', $shipping['method_id']);
            // Get instance_id.
            $shipping_id = $prepare_method_id[count($prepare_method_id) - 1];
            // TODO: Refactor to Get zone by instance_id.
            $shipping_zone = WC_Shipping_Zones::get_zone_by('instance_id', $shipping_id);
            // Get all shipping and filter by free_shipping (Mercado Envios).
            foreach ($shipping_zone->get_shipping_methods() as $key => $shipping_object) {
                // Check is a free method.
                if ($shipping_object->get_option('free_shipping') == 'yes') {
                    // Get shipping method id (Mercado Envios).
                    $shipping_method_id = $shipping_object->get_shipping_method_id($this->site_data['site_id']);
                    $this->preference['shipments']['free_methods'][] = array('id' => (int)$shipping_method_id);
                }
            }
        }
    }
  
    /**
     * @return array
     */
    public function get_internal_metadata_basic()
    {
        $internal_metadata = array(
            "checkout" => "smart",
            "checkout_type" => "",
        );
      
        return $internal_metadata;
    }  
}