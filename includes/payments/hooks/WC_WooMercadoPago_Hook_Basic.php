<?php

/**
 * Class WC_WooMercadoPago_Hook_Basic
 */
class WC_WooMercadoPago_Hook_Basic extends WC_WooMercadoPago_Hook_Abstract
{
    /**
     * WC_WooMercadoPago_Hook_Basic constructor.
     * @param $payment
     */
    public function __construct($payment)
    {
        parent::__construct($payment);
    }

    /**
     * @param bool $is_instance
     */
    public function loadHooks($is_instance = false)
    {
        parent::loadHooks();
        if (!empty($this->payment->settings['enabled']) && $this->payment->settings['enabled'] == 'yes') {
            add_action('woocommerce_after_checkout_form', array($this, 'add_mp_settings_script_basic'));
            add_action('woocommerce_thankyou', array($this, 'update_mp_settings_script_basic'));
        }

        add_action('woocommerce_receipt_' . $this->payment->id,
            function ($order) {
                echo $this->render_order_form($order);
            }
        );

        add_action(
            'wp_head',
            function () {
                if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
                    $page_id = wc_get_page_id('checkout');
                } else {
                    $page_id = woocommerce_get_page_id('checkout');
                }
                if (is_page($page_id)) {
                    echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>' . PHP_EOL;
                }
            }
        );
    }

    /**
     * @param $order_id
     * @return string
     */
    public function render_order_form($order_id)
    {
        $order = wc_get_order($order_id);
        $url = $this->payment->create_preference($order);

        if ('modal' == $this->payment->method && $url) {
            $this->payment->log->write_log(__FUNCTION__, 'rendering Mercado Pago lightbox (modal window).');
            $html = '<style type="text/css">
            #MP-Checkout-dialog #MP-Checkout-IFrame { bottom: 0px !important; top:50%!important; margin-top: -280px !important; height: 590px !important; }
            </style>';
            $html .= '<script type="text/javascript" src="https://secure.mlstatic.com/mptools/render.js"></script>
					<script type="text/javascript">
						(function() { $MPC.openCheckout({ url: "' . esc_url($url) . '", mode: "modal" }); })();
					</script>';
            $html .= '<a id="submit-payment" href="' . esc_url($url) . '" name="MP-Checkout" class="button alt" mp-mode="modal">' .
                __('Pay with Mercado Pago', 'woocommerce-mercadopago') .
                '</a> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' .
                __('Cancel &amp; Clear Cart', 'woocommerce-mercadopago') .
                '</a>';
            return $html;
        } else {
            $this->payment->log->write_log(__FUNCTION__, 'unable to build Mercado Pago checkout URL.');
            $html = '<p>' .
                __('There was an error processing your payment. Please try again or contact us for Assistance.', 'woocommerce-mercadopago') .
                '</p>' .
                '<a class="button" href="' . esc_url($order->get_checkout_payment_url()) . '">' .
                __('Click to try again', 'woocommerce-mercadopago') .
                '</a>
			';
            return $html;
        }
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
     * Scripts to basic
     */
    public function add_mp_settings_script_basic()
    {
        parent::add_mp_settings_script();
    }

    /**
     * @param $order_id
     */
    public function update_mp_settings_script_basic($order_id)
    {
        parent::update_mp_settings_script($order_id);
    }

    /**
     *  Discount not apply
     */
    public function add_discount()
    {
        return;
    }

}