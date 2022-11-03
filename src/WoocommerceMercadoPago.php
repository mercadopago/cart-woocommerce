<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Admin\Translations;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Scripts;

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
     * @var Gateway
     */
    public $gateway;

    /**
     * @var Scripts
     */
    public $scripts;

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

    private function __construct()
    {
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
        $textDomain           = 'woocommerce-mercadopago';
        $locale               = apply_filters('plugin_locale', get_locale(), $textDomain);
        $originalLanguageFile = dirname(__FILE__) . '/../i18n/languages/' . $locale . '.mo';

        unload_textdomain($textDomain);
        load_textdomain($textDomain, $originalLanguageFile);
    }

    public function registerHooks(): void
    {
        add_filter('plugin_action_links_woocommerce-plugins-enablers/woocommerce-mercadopago.php', array($this, 'setPluginSettingsLink'));
        add_action('plugins_loaded', array($this, 'initPlugin'));
    }

    public function initPlugin(): void
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
        $this->define('MP_PLATFORM_ID', self::$platformId);
    }

    private function define($name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
