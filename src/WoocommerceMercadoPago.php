<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Admin\Translations;
use MercadoPago\Woocommerce\Hooks\GatewayHooks;
use MercadoPago\Woocommerce\Hooks\OrderDetailsHooks;

if (!defined('ABSPATH')) {
    exit;
}

class WoocommerceMercadoPago
{
    const MP_MIN_PHP = '7.2';
    const MP_VERSION = '8.0.0';
    const MP_PRIORITY_ON_MENU = 90;
    const WC_MERCADOPAGO_BASENAME = 'woocommerce-plugins-enablers/woocommerce-mercadopago.php';

    /**
     * @var Notices
     */
    public Notices $notices;

    /**
     * @var GatewayHooks
     */
    public GatewayHooks $gatewayHooks;

    /**
     * @var Settings
     */
    public Settings $settings;

    /**
     * @var Translations
     */
    public Translations $translations;

    /**
     * @var OrderDetailsHooks
     */
    public OrderDetailsHooks $orderDetailsHooks;

    /**
     * @var ?WoocommerceMercadoPago
     */
    private static ?WoocommerceMercadoPago $instance = null;

    /**
     * WoocommerceMercadoPago constructor
     */
    private function __construct()
    {
        $this->notices           = Notices::getInstance();
        $this->settings          = Settings::getInstance();
        $this->translations      = Translations::getInstance();
        $this->gatewayHooks      = GatewayHooks::getInstance();
        $this->orderDetailsHooks = OrderDetailsHooks::getInstance();

        $this->loadPluginTextDomain();
        $this->registerHooks();
    }

    /**
     * Get a WoocommerceMercadoPago instance
     *
     * @return WoocommerceMercadoPago
     */
    public static function getInstance(): WoocommerceMercadoPago
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load plugin text domain
     *
     * @return void
     */
    public function loadPluginTextDomain(): void
    {
        $textDomain = 'woocommerce-mercadopago';

        $locale = apply_filters('plugin_locale', get_locale(), $textDomain);

        $originalLanguageFile = dirname(__FILE__) . '/../i18n/languages/' . $locale . '.mo';

        unload_textdomain($textDomain);
        load_textdomain($textDomain, $originalLanguageFile);
    }

    /**
     * Register hooks call
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_filter('plugin_action_links_' . self::WC_MERCADOPAGO_BASENAME, array($this->settings, 'setPluginSettingsLink'));
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Init WoocommerceMercadoPago
     *
     * @return void
     */
    public function init(): void
    {
        if (version_compare(PHP_VERSION, self::MP_MIN_PHP, '<')) {
            $this->missingPhpVersionNotice();
            return;
        }

        if (!in_array('curl', get_loaded_extensions(), true)) {
            $this->missingCurlNotice();
            return;
        }

        if (!in_array('gd', get_loaded_extensions(), true)) {
            $this->missingGdNotice();
        }

        if (class_exists('WC_Payment_Gateway')) {
            add_action( 'woocommerce_order_actions', array( $this->orderDetailsHooks, 'addOrderMetaBoxActions' ) );
        } else {
            $this->notices->adminNoticeMissWoocoommerce();
        }
    }

    /**
     * Show php version unsupported notice
     *
     * @return void
     */
    public function missingPhpVersionNotice(): void
    {
        $this->notices->adminNoticeError($this->translations->notices['php_wrong_version'], false);
    }

    /**
     * Show curl missing notice
     *
     * @return void
     */
    public function missingCurlNotice(): void
    {
        $this->notices->adminNoticeError($this->translations->notices['missing_curl'], false);
    }

    /**
     * Show gd missing notice
     *
     * @return void
     */
    public function missingGdNotice(): void
    {
        $this->notices->adminNoticeWarning($this->translations->notices['missing_gd_extensions'], false);
    }
}
