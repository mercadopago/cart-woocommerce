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
        $this->preference['date_of_expiration'] = $this->get_date_of_expiration();
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
        $this->preference['external_reference'] = $this->get_external_reference();
        $this->preference['additional_info']['items'] = $this->items;
        $this->preference['additional_info']['payer'] = $this->get_payer_custom();
        $this->preference['additional_info']['shipments'] = $this->shipments_receiver_address();
      
        $internal_metadata = parent::get_internal_metadata();
        $internal_metadata = $this->get_internal_metadata_ticket($internal_metadata);
        $this->preference['metadata'] = $internal_metadata;
      
        $this->preference['additional_info']['payer'] = $this->get_payer_custom();
    }

    /**
     * get_date_of_expiration
     *
     * @return string date
     */
    public function get_date_of_expiration()
    {
        $date_expiration = $this->get_option('date_expiration', '');
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
     * @return bool
     */
    public function get_binary_mode()
    {
        return true;
    }
  
    /**
     * @return array
     */
    public function get_internal_metadata_ticket($internal_metadata)
    {
        $internal_metadata["checkout"] = "custom";
        $internal_metadata["checkout_type"] = "ticket";
      
        return $internal_metadata;
    }
}