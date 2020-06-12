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

        $this->preference['payment_methods'] = $this->get_payment_methods($this->ex_payments, $this->installments);
        $this->preference['auto_return'] = $this->auto_return();

        $internal_metadata = parent::get_internal_metadata();
        $merge_array = array_merge($internal_metadata, $this->get_internal_metadata_basic());
        $this->preference['metadata'] = $merge_array;
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
                'number' => (method_exists($this->order, 'get_id') ? $this->order->get_billing_phone() : $this->order->billing_phone),
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
        $success_url = $this->payment->getOption('success_url', '');
        $failure_url = $this->payment->getOption('failure_url', '');
        $pending_url = $this->payment->getOption('pending_url', '');
        $back_urls = array(
            'success' => empty($success_url) ?
                WC_WooMercadoPago_Module::fix_url_ampersand(
                    esc_url($this->get_return_url($this->order))
                ) : $success_url,
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
     * @return array
     */
    public function get_internal_metadata_basic()
    {
        $internal_metadata = array(
            "checkout" => "smart",
            "checkout_type" => $this->payment->getOption('method', 'redirect'),
        );

        return $internal_metadata;
    }
}
