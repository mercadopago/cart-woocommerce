<?php
/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

class WC_WooMercadoPago_PreferenceTicket extends WC_WooMercadoPago_PreferenceAbstract
{

    /**
     * WC_WooMercadoPago_PreferenceTicket constructor.
     * @param $payment
     * @param $order
     * @param $ticket_checkout
     */
    public function __construct($payment, $order, $ticket_checkout)
    {
        parent::__construct($payment, $order, $ticket_checkout);
        $this->preference = $this->make_commum_preference();
        $this->preference['date_of_expiration'] = $this->get_date_of_expiration($payment);
        $this->preference['transaction_amount'] = $this->get_transaction_amount();
        $this->preference['description'] = implode(', ', $this->list_of_items);
        $this->preference['payment_method_id'] = $this->checkout['paymentMethodId'];
        $this->preference['payer']['email'] = $this->get_email();

        if ($this->site_data[$this->site_id]['currency'] == 'BRL') {
            $this->preference['payer']['first_name'] = $this->checkout['firstname'];
            $this->preference['payer']['last_name'] = strlen($this->checkout['docNumber']) == 14 ? $this->checkout['lastname'] : $this->checkout['firstname'];
            $this->preference['payer']['identification']['type'] = strlen($this->checkout['docNumber']) == 14 ? 'CPF' : 'CNPJ';
            $this->preference['payer']['identification']['number'] = $this->checkout['docNumber'];
            $this->preference['payer']['address']['street_name'] = $this->checkout['address'];
            $this->preference['payer']['address']['street_number'] = $this->checkout['number'];
            $this->preference['payer']['address']['neighborhood'] = $this->checkout['city'];
            $this->preference['payer']['address']['city'] = $this->checkout['city'];
            $this->preference['payer']['address']['federal_unit'] = $this->checkout['state'];
            $this->preference['payer']['address']['zip_code'] = $this->checkout['zipcode'];
        }

        if ($this->site_data[$this->site_id]['currency'] == 'UYU') {
            $this->preference['payer']['identification']['type'] = $ticket_checkout['docType'];
            $this->preference['payer']['identification']['number'] = $ticket_checkout['docNumber'];
        }
        
        if($ticket_checkout['paymentMethodId'] == 'webpay'){
            $this->preference['callback_url'] = get_site_url();
            $this->preference['transaction_details']['financial_institution'] = "1234";
            $this->preference['additional_info']['ip_address'] = "127.0.0.1";
            $this->preference['payer']['identification']['type'] = "RUT";
            $this->preference['payer']['identification']['number'] = "0";
            $this->preference['payer']['entity_type'] = "individual";
        }

        $this->preference['external_reference'] = $this->get_external_reference();
        $this->preference['additional_info']['items'] = $this->items;
        $this->preference['additional_info']['payer'] = $this->get_payer_custom();
        $this->preference['additional_info']['shipments'] = $this->shipments_receiver_address();
        $this->preference['additional_info']['payer'] = $this->get_payer_custom();

        if (
            isset($this->checkout['discount']) && !empty($this->checkout['discount']) &&
            isset($this->checkout['coupon_code']) && !empty($this->checkout['coupon_code']) &&
            $this->checkout['discount'] > 0 && WC()->session->chosen_payment_method == 'woo-mercado-pago-ticket'
        ) {
            $this->preference['additional_info']['items'][] = $this->add_discounts();
            $this->preference = array_merge($this->preference , $this->add_discounts_campaign());
        }

        $internal_metadata = parent::get_internal_metadata();
		$merge_array = array_merge($internal_metadata, $this->get_internal_metadata_ticket());
        $this->preference['metadata'] = $merge_array;
    }

    /**
     * get_date_of_expiration
     * @param WC_WooMercadoPago_TicketGateway $payment
     * @return string date
     */
    public function get_date_of_expiration(WC_WooMercadoPago_TicketGateway $payment = null)
    {
        $date_expiration = !is_null($payment)
            ? $payment->getOption('date_expiration')
            : $this->get_option('date_expiration', '');

        if($date_expiration != ""){
            return date('Y-m-d\TH:i:s.000O', strtotime('+' . $date_expiration . ' days'));
        }
    }

    /**
     * @return array
     */
    public function get_items_build_array()
    {
        $items = parent::get_items_build_array();
        foreach ($items as $key => $item) {
            if (isset($item['currency_id'])) {
                unset($items[$key]['currency_id']);
            }
        }

        return $items;
    }

    /**
     * @return array
     */
    public function get_internal_metadata_ticket()
    {
        $internal_metadata = array(
            "checkout" => "custom",
            "checkout_type" => "ticket",
        );

        return $internal_metadata;
    }
}
