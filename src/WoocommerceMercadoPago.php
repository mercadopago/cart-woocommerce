<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Admin\Translations;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Checkout;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Hooks\Product;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class WoocommerceMercadoPago
{
    /**
     * @const
     */
    private const PLUGIN_VERSION = '7.0.0';

    /**
     * @const
     */
    private const PLUGIN_MIN_PHP = '7.2';

    /**
     * @const
     */
    private const PLATFORM_ID = 'bo2hnr2ic4p001kbgpt0';

    /**
     * @const
     */
    private const PLATFORM_NAME = 'woocommerce';

    /**
     * @const
     */
    private const PLUGIN_NAME = 'woocommerce-plugins-enablers/woocommerce-mercadopago.php';

    /**
     * @var Dependencies
     */
    public $dependencies;

    /**
     * @var Logs
     */
    public $logs;

    /**
     * @var Translations
     */
    public $translations;

    /**
     * @var Admin
     */
    public $admin;

    /**
     * @var Checkout
     */
    public $checkout;

    /**
     * @var Plugin
     */
    public $plugin;

    /**
     * @var Scripts
     */
    public $scripts;

    /**
     * @var Notices
     */
    public $notices;

    /**
     * @var Gateway
     */
    public $gateway;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var Product
     */
    public $product;

    /**
     * @var Store
     */
    public $store;

    /**
     * @var Seller
     */
    public $seller;

    /**
     * @var Settings
     */
    public $settings;

    /**
     * @var Links
     */
    public $links;

    /**
     * WoocommerceMercadoPago constructor
     */
    public function __construct()
    {
        $this->defineConstants();
        $this->loadPluginTextDomain();
        $this->registerHooks();
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
        $originalLanguageFile = dirname(__FILE__) . '/../i18n/languages/woocommerce-mercadopago-' . $locale . '.mo';

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
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Init plugin
     *
     * @return void
     */
    public function init(): void
    {
        $this->setProperties();
        $this->setPluginSettingsLink();

        if (version_compare(PHP_VERSION, self::PLUGIN_MIN_PHP, '<')) {
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
            $this->dependencies->notices->adminNoticeMissWoocoommerce();
        }
    }

    /**
     * Set plugin properties
     *
     * @return void
     */
    public function setProperties(): void
    {
        $this->dependencies = new Dependencies();

        $this->logs         = $this->dependencies->getLogs();
        $this->translations = $this->dependencies->getTranslations();
        $this->admin        = $this->dependencies->getAdmin();
        $this->checkout     = $this->dependencies->getCheckout();
        $this->plugin       = $this->dependencies->getPlugin();
        $this->scripts      = $this->dependencies->getScripts();
        $this->notices      = $this->dependencies->getNotices();
        $this->gateway      = $this->dependencies->getGateway();
        $this->order        = $this->dependencies->getOrder();
        $this->product      = $this->dependencies->getProduct();
        $this->store        = $this->dependencies->getStore();
        $this->seller       = $this->dependencies->getSeller();
        $this->settings     = $this->dependencies->getSettings();
        $this->links        = $this->dependencies->getLinks();
    }

    /**
     * Set plugin configuration links
     *
     * @return void
     */
    public function setPluginSettingsLink()
    {
        $links = $this->links->getLinks();

        $pluginLinks = [
            [
                'text'   => $this->translations->plugin['set_plugin'],
                'href'   => $links['admin_settings_page'],
                'target' => $this->admin::HREF_TARGET_DEFAULT,
            ],
            [
                'text'   => $this->translations->plugin['payment_method'],
                'href'   => $links['admin_gateways_list'],
                'target' => $this->admin::HREF_TARGET_DEFAULT,
            ],
            [
                'text'   => $this->translations->plugin['plugin_manual'],
                'href'   => $links['docs_integration_introduction'],
                'target' => $this->admin::HREF_TARGET_BLANK,
            ],
        ];

        $this->admin->registerPluginActionLinks(self::PLUGIN_NAME, $pluginLinks);
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
        $this->define('MP_MIN_PHP', self::PLUGIN_MIN_PHP);
        $this->define('MP_VERSION', self::PLUGIN_VERSION);
        $this->define('MP_PLATFORM_ID', self::PLATFORM_ID);
        $this->define('MP_PLATFORM_NAME', self::PLATFORM_NAME);
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
