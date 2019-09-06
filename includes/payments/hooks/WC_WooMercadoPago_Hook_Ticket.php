<?php

/**
 * Class WC_WooMercadoPago_Hook_Ticket
 */
class WC_WooMercadoPago_Hook_Ticket extends WC_WooMercadoPago_Hook_Abstract
{
    /**
     * WC_WooMercadoPago_Hook_Ticket constructor.
     * @param $payment
     */
    public function __construct($payment)
    {
        parent::__construct($payment);
    }

    /**
     * Load Hooks
     */
    public function loadHooks()
    {
        parent::loadHooks();
        add_action('wp_enqueue_scripts', array($this, 'add_checkout_scripts'));
        if (!empty($this->payment->settings['enabled']) && $this->payment->settings['enabled'] == 'yes') {
            add_action('woocommerce_after_checkout_form', array($this, 'add_mp_settings_script_ticket'));
            add_action('woocommerce_thankyou_' . $this->payment->id, array($this, 'update_mp_settings_script_ticket'));
        }
    }

    /**
     *  Add Discount
     */
    public function add_discount()
    {
        if (!isset($_POST['mercadopago_ticket'])) {
            return;
        }
        if (is_admin() && !defined('DOING_AJAX') || is_cart()) {
            return;
        }
        $ticket_checkout = $_POST['mercadopago_ticket'];
        parent::add_discount_abst($ticket_checkout);
    }

    /**
     * @return bool
     * @throws WC_WooMercadoPago_Exception
     */
    public function custom_process_admin_options()
    {
        $updateOptions = parent::custom_process_admin_options();
        return $updateOptions;
    }

    /**
     * MP Settings Ticket
     */
    public function add_mp_settings_script_ticket()
    {
        parent::add_mp_settings_script();
    }

    /**
     * @param $order_id
     */
    public function update_mp_settings_script_ticket($order_id)
    {
        parent::update_mp_settings_script($order_id);
        $order = wc_get_order($order_id);
        $transaction_details = (method_exists($order, 'get_meta')) ? $order->get_meta('_transaction_details_ticket') : get_post_meta($order->get_id(), '_transaction_details_ticket', true);

        if (empty($transaction_details)) {
            return;
        }

        $html = '<p>' .
            __('Thanks for your order. Please pay the ticket to have your order approved.', 'woocommerce-mercadopago') .
            '</p>' .
            '<p><iframe src="' . $transaction_details . '" style="width:100%; height:1000px;"></iframe></p>' .
            '<a id="submit-payment" target="_blank" href="' . $transaction_details . '" class="button alt"' .
            ' style="font-size:1.25rem; width:75%; height:48px; line-height:24px; text-align:center;">' .
            __('Print the ticket', 'woocommerce-mercadopago') .
            '</a> ';
        $added_text = '<p>' . $html . '</p>';
        echo $added_text;
    }
}