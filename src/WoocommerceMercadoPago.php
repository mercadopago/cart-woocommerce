<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Admin\Translations;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\OrderDetails;
use MercadoPago\Woocommerce\Hooks\Scripts;

if (!defined('ABSPATH')) {
    exit;
}

class WoocommerceMercadoPago
{
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
     * @var Notices
     */
    public $notices;

    /**
     * @var Gateway
     */
    public $gateway;

    /**
     * @var Scripts
     */
    public $scripts;

    /**
     * @var OrderDetails
     */
    public $orderDetails;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @var Translations
     */
    protected $translations;

    /**
     * @var WoocommerceMercadoPago
     */
    private static $instance = null;

    /**
     * WoocommerceMercadoPago constructor
     */
    private function __construct()
    {
        $this->defineConstants();
        $this->loadPluginTextDomain();
        $this->registerHooks();
    }

    /**
     * Get WoocommerceMercadoPago instance
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
     * Register hooks
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_filter('plugin_action_links_woocommerce-plugins-enablers/woocommerce-mercadopago.php', array($this, 'setPluginSettingsLink'));
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Init plugin
     *
     * @return void
     */
    public function init(): void
    {
        $this->setProperties();

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
            $this->notices->adminNoticeMissWoocoommerce();
        }
    }

    /**
     * Set plugin properties
     *
     * @return void
     */
    public function setProperties(): void
    {
        $this->translations = Translations::getInstance();
        $this->scripts      = Scripts::getInstance();
        $this->notices      = Notices::getInstance();
        $this->gateway      = Gateway::getInstance();
        $this->orderDetails = OrderDetails::getInstance();
        $this->settings     = Settings::getInstance();
    }

    /**
     * Set plugin configuration links
     *
     * @param array $links
     *
     * @return array
     */
    public function setPluginSettingsLink(array $links): array
    {
        $pluginLinks = array(
            '<a href="' . admin_url('admin.php?page=mercadopago-settings') . '">' . $this->translations->pluginSettings['set_plugin'] . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . $this->translations->pluginSettings['payment_method'] . '</a>',
            '<a target="_blank" href="' . Links::getLinks()['link_mp_developers'] . '">' . $this->translations->pluginSettings['plugin_manual'] . '</a>',
        );

        return array_merge($pluginLinks, $links);
    }

    /**
     * Show php version unsupported notice
     *
     * @return void
     */
    public function verifyPhpVersionNotice(): void
    {
        $this->notices->adminNoticeError($this->translations->notices['php_wrong_version'], false);
    }

    /**
     * Show curl missing notice
     *
     * @return void
     */
    public function verifyCurlNotice(): void
    {
        $this->notices->adminNoticeError($this->translations->notices['missing_curl'], false);
    }

    /**
     * Show gd missing notice
     *
     * @return void
     */
    public function verifyGdNotice(): void
    {
        $this->notices->adminNoticeWarning($this->translations->notices['missing_gd_extensions'], false);
    }

    /**
     * Define plugin constants
     *
     * @return void
     */
    private function defineConstants(): void
    {
        $this->define('MP_MIN_PHP', self::$mpMinPhp);
        $this->define('MP_VERSION', self::$mpVersion);
        $this->define('MP_PLATFORM_ID', self::$platformId);
    }

    /**
     * Define constants
     *
     * @param $name
     * @param $value
     *
     * @return void
     */
    private function define($name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
