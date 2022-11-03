<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Admin\Translations;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Hooks\OrderDetailsHooks;

if (!defined('ABSPATH')) {
    exit;
}

class WoocommerceMercadoPago
{
    /**
     * @var Notices
     */
    public Notices $notices;

    /**
     * @var Gateway
     */
    public $gateway;

    /**
     * @var Scripts
     */
    public $scripts;

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
     * @var string
     */
    public static $mpVersion = '8.0.0';

    /**
     * @var string
     */
    public static $mpMinPhp = '7.2';

    /**
     * @var string
     */
    public static $platformId = 'bo2hnr2ic4p001kbgpt0';

    /**
     * @var ?WoocommerceMercadoPago
     */
    private static ?WoocommerceMercadoPago $instance = null;

    private function __construct()
    {
        $this->defineConstants();
        $this->woocommerceMercadoPagoLoadPluginTextDomain();
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
        $textDomain           = 'woocommerce-mercadopago';
        $locale               = apply_filters('plugin_locale', get_locale(), $textDomain);
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
        add_filter('plugin_action_links_woocommerce-plugins-enablers/woocommerce-mercadopago.php', array($this, 'setPluginSettingsLink'));
        add_action('plugins_loaded', array($this, 'initPlugin'));
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

        $this->setDependencies();
    }

    public function setDependencies(): void
    {
        $this->notices      = Notices::getInstance();
        $this->gateway      = Gateway::getInstance();
        $this->scripts      = Scripts::getInstance();
        $this->settings     = Settings::getInstance();
        $this->translations = Translations::getInstance();
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
        $this->define('MP_PLATFORM_ID', self::$platformId);
    }

    private function define($name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
