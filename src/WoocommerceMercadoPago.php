<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Admin\Notices;

if (!defined('ABSPATH')) {
    exit;
}

class WoocommerceMercadoPago
{
    /**
     * @var Notices
     */
    public $notices;

    /**
     * @var string
     */
    public static $mp_version = '8.0.0';

    /**
     * @var string
     */
    public static $mp_min_php = '7.2';

    /**
     * @var WoocommerceMercadoPago
     */
    protected static $instance = null;

    public function __construct()
    {
        $this->notices = Notices::getInstance();
        
        $this->defineConstants();
        $this->woocommerceMercadoPagoLoadPluginTextDomain();
        $this->registerHooks();
    }

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function woocommerceMercadoPagoLoadPluginTextDomain()
    {
        // TODO: add languages
    }

    public function registerHooks()
    {
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }

    public function initPlugin()
    {
        if (version_compare(PHP_VERSION, self::$mp_min_php, '<')) {
            $this->verifyPhpVersionNotice();
            return;
        }

        if (!in_array('curl', get_loaded_extensions(), true)) {
            $this->verifyCurlNotice();
            return;
        }

        if (!in_array('gd', get_loaded_extensions(), true)) {
            $this->verifyGdNotice();
        }

        if (!class_exists('WC_Payment_Gateway')) {
            $this->notices->adminNoticeMissWoocoommerce();
        }
    }

    public function verifyPhpVersionNotice()
    {
        $this->notices->adminNoticeError(
            '
                Mercado Pago payments for WooCommerce requires PHP version 7.2 or later. 
                Please update your PHP version.
            ',
            false
        );
    }

    public function verifyCurlNotice()
    {
        $this->notices->adminNoticeError(
            'Mercado Pago Error: PHP Extension CURL is not installed.',
            false
        );
    }

    public function verifyGdNotice()
    {
        $this->notices->adminNoticeWarning(
            '
                Mercado Pago Error: PHP Extension GD is not installed. 
                Installation of GD extension is required to send QR Code Pix by email.
            ',
            false
        );
    }

    private function defineConstants()
    {
        $this->define('PRODUCT_ID_DESKTOP', 'BT7OF5FEOO6G01NJK3QG');
        $this->define('PRODUCT_ID_MOBILE', 'BT7OFH09QS3001K5A0H0');
        $this->define('PLATFORM_ID', 'bo2hnr2ic4p001kbgpt0');
        $this->define('MP_VERSION', self::$mp_version);
        $this->define('MP_MIN_PHP', self::$mp_min_php);
        $this->define('API_MP_BASE_URL', 'https://api.mercadopago.com');
        $this->define('DATE_EXPIRATION', 3);

        $this->define('PAYMENT_GATEWAYS', [
            'WC_WooMercadoPago_Basic_Gateway',
            'WC_WooMercadoPago_Custom_Gateway',
            'WC_WooMercadoPago_Ticket_Gateway',
            'WC_WooMercadoPago_Pix_Gateway',
        ]);

        $this->define('GATEWAYS_IDS', [
            'woo-mercado-pago-basic',
            'woo-mercado-pago-custom',
            'woo-mercado-pago-ticket',
            'woo-mercado-pago-pix',
        ]);
    }

    private function define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
