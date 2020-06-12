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
        if (!empty($this->payment->settings['enabled']) && $this->payment->settings['enabled'] == 'yes') {
            add_action('wp_enqueue_scripts', array($this, 'add_checkout_scripts_ticket'));
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
     * Add Checkout Scripts
     */
    public function add_checkout_scripts_ticket()
    {
        if (is_checkout() && $this->payment->is_available() && !get_query_var('order-received')) {
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            wp_enqueue_script(
                'woocommerce-mercadopago-ticket-checkout',
                plugins_url('../../assets/js/ticket' . $suffix . '.js', plugin_dir_path(__FILE__)),
                array('jquery'),
                WC_WooMercadoPago_Constants::VERSION,
                true
            );

            wp_localize_script(
                'woocommerce-mercadopago-ticket-checkout',
                'wc_mercadopago_ticket_params',
                array(
                    'site_id'               => $this->payment->getOption('_site_id_v1'),
                    'coupon_mode'           => isset($this->payment->logged_user_email) ? $this->payment->coupon_mode : 'no',
                    'discount_action_url'   => $this->payment->discount_action_url,
                    'payer_email'           => $this->payment->logged_user_email,
                    'apply'                 => __('Apply', 'woocommerce-mercadopago'),
                    'remove'                => __('Remove', 'woocommerce-mercadopago'),
                    'coupon_empty'          => __('Please, inform your coupon code', 'woocommerce-mercadopago'),
                    'choose'                => __('To choose', 'woocommerce-mercadopago'),
                    'other_bank'            => __('Other bank', 'woocommerce-mercadopago'),
                    'discount_info1'        => __('You will save', 'woocommerce-mercadopago'),
                    'discount_info2'        => __('with discount of', 'woocommerce-mercadopago'),
                    'discount_info3'        => __('Total of your purchase:', 'woocommerce-mercadopago'),
                    'discount_info4'        => __('Total of your purchase with discount:', 'woocommerce-mercadopago'),
                    'discount_info5'        => __('*After payment approval', 'woocommerce-mercadopago'),
                    'discount_info6'        => __('Terms and conditions of use', 'woocommerce-mercadopago'),
                    'loading'               => plugins_url('../../assets/images/', plugin_dir_path(__FILE__)) . 'loading.gif',
                    'check'                 => plugins_url('../../assets/images/', plugin_dir_path(__FILE__)) . 'check.png',
                    'error'                 => plugins_url('../../assets/images/', plugin_dir_path(__FILE__)) . 'error.png'
                )
            );
        }
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
            __('Great, we processed your purchase order. Complete the payment with ticket so that we finish approving it.', 'woocommerce-mercadopago') .
            '</p>' .
            '<p><iframe src="' . $transaction_details . '" style="width:100%; height:1000px;"></iframe></p>' .
            '<a id="submit-payment" target="_blank" href="' . $transaction_details . '" class="button alt"' .
            ' style="font-size:1.25rem; width:75%; height:48px; line-height:24px; text-align:center;">' .
            __('Print ticket', 'woocommerce-mercadopago') .
            '</a> ';
        $added_text = '<p>' . $html . '</p>';
        echo $added_text;
    }
}
