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

    public static function getInstance(): WoocommerceMercadoPago
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function woocommerceMercadoPagoLoadPluginTextDomain(): void
    {
        // TODO: add languages
    }

    public function registerHooks(): void
    {
        add_filter('plugin_action_links_' . WC_MERCADOPAGO_BASENAME, array($this, 'setPluginSettingsLink'));
        add_action('plugins_loaded', array($this, 'initPlugin'));
    }

    public function registerGateway($gateway): void
    {
        add_filter('woocommerce_payment_gateways', function ($methods) use ($gateway) {
            $methods[] = $gateway;
            return $methods;
        });
    }

    public function initPlugin(): void
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

    public function setPluginSettingsLink($links): array
    {
        $pluginLinks   = array();
        $pluginLinks[] = '<a href="' . admin_url('admin.php?page=mercadopago-settings') . '">Set plugin</a>';
        $pluginLinks[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">Payment method</a>';
        $pluginLinks[] = '<a target="_blank" href="https://developers.mercadopago.com">Plugin manual</a>';

        return array_merge($pluginLinks, $links);
    }

    public function verifyPhpVersionNotice(): void
    {
        $this->notices->adminNoticeError(
            '
                Mercado Pago payments for WooCommerce requires PHP version 7.2 or later.
                Please update your PHP version.
            ',
            false
        );
    }

    public function verifyCurlNotice(): void
    {
        $this->notices->adminNoticeError(
            'Mercado Pago Error: PHP Extension CURL is not installed.',
            false
        );
    }

    public function verifyGdNotice(): void
    {
        $this->notices->adminNoticeWarning(
            '
                Mercado Pago Error: PHP Extension GD is not installed.
                Installation of GD extension is required to send QR Code Pix by email.
            ',
            false
        );
    }

    private function defineConstants(): void
    {
        $this->define('MP_VERSION', self::$mp_version);
        $this->define('MP_MIN_PHP', self::$mp_min_php);
        $this->define('WC_MERCADOPAGO_BASENAME', 'woocommerce-plugins-enablers/woocommerce-mercadopago.php');
    }

    private function define($name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
