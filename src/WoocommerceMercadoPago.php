<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Admin\Translations;
use MercadoPago\Woocommerce\Hooks\GatewayHooks;

if (!defined('ABSPATH')) {
    exit;
}

class WoocommerceMercadoPago
{
    public Notices $notices;
    public GatewayHooks $gatewayHooks;
    public Settings $settings;
    public Translations $translations;

    public static string $mpVersion = '8.0.0';
    public static string $mpMinPhp = '7.2';
    public static int $priorityOnMenu = 90;

    private static ?WoocommerceMercadoPago $instance = null;

    private function __construct()
    {
        $this->notices = Notices::getInstance();
        $this->settings = Settings::getInstance();
        $this->translations = Translations::getInstance();
        $this->gatewayHooks = GatewayHooks::getInstance();

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
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function pluginRowMeta($links, $file): array
    {
        if ( WC_MERCADOPAGO_BASENAME === $file ) {
            $new_link   = array();
            $new_link[] = $links[0];
            $new_link[] = Translations::$genericSettings['by_mp'];

            return $new_link;
        }

        return (array) $links;
    }

    public function setPluginSettingsLink(array $links): array
    {
        $pluginLinks   = array(
            '<a href="' . admin_url('admin.php?page=mercadopago-settings') . '">Set plugin</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">Payment method</a>',
            '<a target="_blank" href="https://developers.mercadopago.com">Plugin manual</a>',
        );

        return array_merge($pluginLinks, $links);
    }

    public function init(): void
    {
        if (version_compare(PHP_VERSION, self::$mpMinPhp, '<')) {
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
            self::updatePluginVersion();
            add_filter('plugin_row_meta', array( $this, 'pluginRowMeta' ), 10, 2 );
            add_action( 'woocommerce_order_actions', array( 'OrderDetailsHooks', 'addOrderMetaBoxActions' ) );
        } else {
            $this->notices->adminNoticeMissWoocoommerce();
        }
    }

    public function updatePluginVersion(): void
    {
        $oldVersion = get_option( '_mp_version', '0' );
        if ( version_compare( self::$mpVersion, $oldVersion, '>' ) ) {
            do_action('mercadopago_plugin_updated');
            do_action('mercadopago_test_mode_update');

            update_option('_mp_version', self::$mpVersion, true);
        }
    }

    public function verifyPhpVersionNotice(): void
    {
        $this->notices->adminNoticeError(Translations::$notices['php_wrong_version'], false);
    }

    public function verifyCurlNotice(): void
    {
        $this->notices->adminNoticeError(Translations::$notices['missing_curl'], false);
    }

    public function verifyGdNotice(): void
    {
        $this->notices->adminNoticeWarning(Translations::$notices['missing_gd_extensions'], false);
    }

    private function defineConstants(): void
    {
        $this->define('MP_MIN_PHP', self::$mpMinPhp);
        $this->define('MP_VERSION', self::$mpVersion);
        $this->define('PRIORITY_ON_MENU', self::$priorityOnMenu);
        $this->define('WC_MERCADOPAGO_BASENAME', 'woocommerce-plugins-enablers/woocommerce-mercadopago.php');
    }

    private function define($name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
