<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */
abstract class WC_WooMercadoPago_PreferenceAbstract extends WC_Payment_Gateway
{
    protected $order;
    protected $payment;
    protected $log;
    protected $checkout;
    protected $gateway_discount;
    protected $commission;
    protected $currency_ratio;
    protected $items;
    protected $order_total;
    protected $list_of_items;
    protected $preference;
    protected $selected_shipping;
    protected $ship_cost;
    protected $site_id;
    protected $site_data;
    protected $test_user_v1;
    protected $sandbox;
    protected $notification_class;
    protected $ex_payments;
    protected $installments;

    /**
     * WC_WooMercadoPago_PreferenceAbstract constructor.
     * @param $payment
     * @param $order
     * @param null $requestCheckout
     */
    public function __construct($payment, $order, $requestCheckout = null)
    {
        $this->payment = $payment;
        $this->log = $payment->log;
        $this->order = $order;
        $this->gateway_discount = $this->payment->gateway_discount;
        $this->commission = $this->payment->commission;
        $this->ex_payments = $this->payment->ex_payments;
        $this->installments = $this->payment->installments;
        $this->notification_class = get_class($this->payment);
        $this->sandbox = $this->payment->isTestUser();
        $this->test_user_v1 = get_option('_test_user_v1', '');
        $this->site_id = get_option('_site_id_v1', '');
        $this->site_data = WC_WooMercadoPago_Module::$country_configs;
        $this->order = $order;
        $this->checkout = $requestCheckout;
        $this->currency_ratio = $this->get_currency_conversion();
        $this->items = array();
        $this->order_total = 0;
        $this->list_of_items = array();
        $this->selected_shipping = $order->get_shipping_method();
        $this->ship_cost = $this->order->get_total_shipping() + $this->order->get_shipping_tax();

        if (sizeof($this->order->get_items()) > 0) {
            $this->items = $this->get_items_build_array();
        }

        //shipping is added to items
        $this->items = array_merge($this->items, $this->prepare_shipping());

        //fees is added to items
        if (0 < count($this->order->get_fees())) {
            $this->items = array_merge($this->items, $this->fees_cost_item());
        }
    }

    /**
     * @return float
     */
    protected function number_format_value($value)
    {
        return (float) number_format($value, 2, '.', '');
    }

    protected function prepare_shipping()
    {
        $result = [];

        if ($this->ship_cost > 0) {
            $shipCost = $this->ship_cost_item();
            $result[] = $shipCost;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function make_commum_preference()
    {
        $preference = array(
            'binary_mode' => $this->get_binary_mode($this->payment),
            'external_reference' => $this->get_external_reference($this->payment),
            'notification_url' => $this->get_notification_url(),
            'statement_descriptor' => $this->payment->getOption('mp_statement_descriptor', 'Mercado Pago'),
        );

        if (!$this->test_user_v1 && !$this->sandbox) {
            $preference['sponsor_id'] = $this->get_sponsor_id();
        }

        return $preference;
    }

    /**
     * @return int
     */
    public function get_currency_conversion()
    {
        return WC_WooMercadoPago_Helpers_CurrencyConverter::getInstance()->ratio($this->payment);
    }

    /**
     * @return mixed
     */
    public function get_email()
    {
        if (method_exists($this->order, 'get_id')) {
            return $this->order->get_billing_email();
        } else {
            return $this->order->billing_email;
        }
    }

    /**
     * @return array
     */
    public function get_payer_custom()
    {
        $payer_additional_info = array(
            'first_name' => (method_exists($this->order, 'get_id') ? html_entity_decode($this->order->get_billing_first_name()) : html_entity_decode($this->order->billing_first_name)),
            'last_name' => (method_exists($this->order, 'get_id') ? html_entity_decode($this->order->get_billing_last_name()) : html_entity_decode($this->order->billing_last_name)),
            //'registration_date' =>
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
    public function get_items_build_array()
    {
        $items = array();
        foreach ($this->order->get_items() as $item) {
            if ($item['qty']) {
                $product = new WC_product($item['product_id']);
                $product_title = method_exists($product, 'get_description') ? $product->get_name() : $product->post->post_title;
                $product_content = method_exists($product, 'get_description') ? $product->get_description() : $product->post->post_content;
                // Calculates line amount and discounts.
                $line_amount = $item['line_total'] + $item['line_tax'];
                $discount_by_gateway = (float) $line_amount * ($this->gateway_discount / 100);
                $commission_by_gateway = (float) $line_amount * ($this->commission / 100);
                $item_amount =  $this->calculate_price($line_amount - $discount_by_gateway + $commission_by_gateway);
                $this->order_total += $this->number_format_value($item_amount);

                // Add the item.
                array_push($this->list_of_items, $product_title . ' x ' . $item['qty']);
                array_push($items, array(
                    'id' => $item['product_id'],
                    'title' => html_entity_decode($product_title) . ' x ' . $item['qty'],
                    'description' => sanitize_file_name(html_entity_decode(
                        strlen($product_content) > 230 ?
                            substr($product_content, 0, 230) . '...' : $product_content
                    )),
                    'picture_url' => sizeof($this->order->get_items()) > 1 ?
                        plugins_url('assets/images/cart.png', plugin_dir_path(__FILE__)) : wp_get_attachment_url($product->get_image_id()),
                    'category_id' => get_option('_mp_category_id', 'others'),
                    'quantity' => 1,
                    'unit_price' => $this->number_format_value($item_amount),
                    'currency_id' => $this->site_data[$this->site_id]['currency']
                ));
            }
        }
        return $items;
    }

    /**
     * @return array
     */
    public function ship_cost_item()
    {
        $ship_cost = $this->calculate_price($this->ship_cost);
        $this->order_total += $this->number_format_value($ship_cost);

        return array(
            'title'       => method_exists($this->order, 'get_id') ? $this->order->get_shipping_method() : $this->order->shipping_method,
            'description' => __('Shipping service used by the store.', 'woocommerce-mercadopago'),
            'category_id' => get_option('_mp_category_id', 'others'),
            'quantity'    => 1,
            'unit_price'  => $this->number_format_value($ship_cost),
        );
    }

    /**
     * @return array
     */
    public function fees_cost_item()
    {
        $items = array();
        foreach ($this->order->get_fees() as $fee) {
            if ((float) $fee['total'] >= 0) {
                continue;
            }

            $final = ($fee['total'] + $fee['total_tax']) * $this->currency_ratio;

            $this->order_total += $this->number_format_value($final);
            array_push($items, array(
                'title'       => sanitize_file_name(html_entity_decode(
                    strlen($fee['name']) > 230 ?
                        substr($fee['name'], 0, 230) . '...' : $fee['name']
                )),
                'description' => sanitize_file_name(html_entity_decode(
                    strlen($fee['name']) > 230 ?
                        substr($fee['name'], 0, 230) . '...' : $fee['name']
                )),
                'category_id' => get_option('_mp_category_id', 'others'),
                'quantity'    => 1,
                'unit_price'  => $this->number_format_value($final)
            ));
        }
        return $items;
    }

    /**
     * @return array
     */
    public function shipments_receiver_address()
    {
        $shipments = array(
            'receiver_address' => array(
                'zip_code' => method_exists($this->order, 'get_id') ?
                    $this->order->get_shipping_postcode() : $this->order->shipping_postcode,
                //'street_number' =>
                'street_name' => html_entity_decode(
                    method_exists($this->order, 'get_id') ?
                        $this->order->get_shipping_address_1() . ' ' .
                        $this->order->get_shipping_address_2() . ' ' .
                        $this->order->get_shipping_city() . ' ' .
                        $this->order->get_shipping_state() . ' ' .
                        $this->order->get_shipping_country() : $this->order->shipping_address_1 . ' ' .
                        $this->order->shipping_address_2 . ' ' .
                        $this->order->shipping_city . ' ' .
                        $this->order->shipping_state . ' ' .
                        $this->order->shipping_country
                ),
                //'floor' =>
                'apartment' => method_exists($this->order, 'get_id') ?
                    $this->order->get_shipping_address_2() : $this->order->shipping_address_2
            )
        );
        return $shipments;
    }

    /**
     * @return mixed
     */
    public function get_notification_url()
    {
        if (!strrpos(get_site_url(), 'localhost')) {
            $notification_url = $this->payment->custom_domain;
            // Check if we have a custom URL.
            if (empty($notification_url) || filter_var($notification_url, FILTER_VALIDATE_URL) === FALSE) {
                return WC()->api_request_url($this->notification_class);
            } else {
                return WC_WooMercadoPago_Module::fix_url_ampersand(esc_url(
                    $notification_url . '/wc-api/' . $this->notification_class . '/'
                ));
            }
        }
    }

    /**
     * get binary_mode
     * @param class $payment
     * @return bool
     */
    public function get_binary_mode($payment = null)
    {
        $binary_mode = !is_null($payment) ? $payment->getOption('binary_mode', 'no') : 'no';

        if ($binary_mode != 'no') {
            return true;
        }

        return false;
    }

    /**
     *  Get Sponsor Id
     */
    public function get_sponsor_id()
    {
        return WC_WooMercadoPago_Module::get_sponsor_id();
    }

    /**
     * @return string
     */
    public function get_external_reference($payment = null)
    {
        $store_identificator = get_option('_mp_store_identificator', 'WC-');

        if (method_exists($this->order, 'get_id')) {
            return $store_identificator . $this->order->get_id();
        } else {
            return $store_identificator . $this->order->id;
        }
    }

    /**
     * @return array
     */
    public function get_preference()
    {
        $this->log->write_log('Created preference: ', 'Preference: ' . json_encode($this->preference, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $this->preference;
    }

    /**
     * @return float|int
     */
    public function get_transaction_amount()
    {
        return $this->number_format_value($this->order_total);
    }

    /**
     * @return array
     */
    public function add_discounts()
    {
        $item = array(
            'title' => __('Discount provided by store', 'woocommerce-mercadopago'),
            'description' => __('Discount provided by store', 'woocommerce-mercadopago'),
            'quantity' => 1,
            'category_id' => get_option('_mp_category_name', 'others'),
            'unit_price' => ($this->site_data[$this->site_id]['currency'] == 'COP' || $this->site_data[$this->site_id]['currency'] == 'CLP') ?
                -floor($this->checkout['discount'] * $this->currency_ratio) : -floor($this->checkout['discount'] * $this->currency_ratio * 100) / 100
        );
        return $item;
    }

    /**
     * Discount Campaign
     *
     * @return array
     */
    public function add_discounts_campaign()
    {
        return array(
            'campaign_id' => (int) $this->checkout['campaign_id'],
            'coupon_amount' => ($this->site_data[$this->site_id]['currency'] == 'COP' || $this->site_data[$this->site_id]['currency'] == 'CLP') ?
                floor($this->checkout['discount'] * $this->currency_ratio) : floor($this->checkout['discount'] * $this->currency_ratio * 100) / 100,
            'coupon_code' => strtoupper($this->checkout['coupon_code'])
        );
    }

    /**
     * @return array
     */
    public function get_internal_metadata()
    {
        $accessToken = get_option('_mp_access_token_prod', '');
        if (empty($accessToken)) {
            return null;
        }

        $test_mode = false;
        if ($this->payment->getOption('checkout_credential_prod', '') == 'no') {
            $test_mode = true;
        }

        $seller = explode('-', $accessToken);
        $w = WC_WooMercadoPago_Module::woocommerce_instance();
        $internal_metadata = array(
            "platform" => WC_WooMercadoPago_Constants::PLATAFORM_ID,
            "platform_version" => $w->version,
            "module_version" => WC_WooMercadoPago_Constants::VERSION,
            "site_id" => get_option('_site_id_v1'),
            "sponsor_id" => $this->get_sponsor_id(),
            "collector" => end($seller),
            "test_mode" => $test_mode,
            "details" => ""
        );

        return $internal_metadata;
    }

    /**
     * @param $amount
     * @return float
     */
    private function calculate_price($amount)
    {
        if ($this->site_data[$this->site_id]['currency'] == 'COP' || $this->site_data[$this->site_id]['currency'] == 'CLP') {
            return floor($amount * $this->currency_ratio);
        }
        return floor($amount * $this->currency_ratio * 100) / 100;
    }
}
