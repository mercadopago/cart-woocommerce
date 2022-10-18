<?php

namespace MercadoPago\CartWoocommerce;

use MercadoPago\CartWoocommerce\Notices\WordpressNotices;

class WoocommerceMercadoPago
{
    public $version = '8.0.0';

    protected static $instance = null;

    public function __construct()
    {
        $this->woocommerce_mercadopago_load_plugin_textdomain();
        $this->register_hooks();
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function woocommerce_mercadopago_load_plugin_textdomain()
    {
        // TODO: add languages
    }

    public function register_hooks()
    {
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }

    public function init_plugin()
    {
        if (version_compare(PHP_VERSION, '7.2', '<')) {
            $this->verify_php_version_notice();
            return;
        }

        if (!in_array('curl', get_loaded_extensions(), true)) {
            $this->verify_curl_notice();
            return;
        }

        if (!in_array('gd', get_loaded_extensions(), true)) {
            $this->verify_gd_notice();
        }
    }

    public function verify_php_version_notice()
    {
        WordpressNotices::admin_notice_error(
            '
                Mercado Pago payments for WooCommerce requires PHP version 7.2 or later. 
                Please update your PHP version.
            ',
            false
        );
    }

    public function verify_curl_notice()
    {
        WordpressNotices::admin_notice_warning(
            'Mercado Pago Error: PHP Extension CURL is not installed.',
            false
        );
    }

    public function verify_gd_notice()
    {
        WordpressNotices::admin_notice_warning(
            '
                Mercado Pago Error: PHP Extension GD is not installed. 
                Installation of GD extension is required to send QR Code Pix by email.
            ',
            false
        );
    }
}
