<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Admin\MetadataSettings;
use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Order\Metadata;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Checkout;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Meta;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Hooks\Product;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Hooks\Template;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

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
    private const PRODUCT_ID_DESKTOP = 'BT7OF5FEOO6G01NJK3QG';

    /**
     * @const
     */
    private const PRODUCT_ID_MOBILE  = 'BT7OFH09QS3001K5A0H0';

    /**
     * @const
     */
    private const PLATFORM_NAME = 'woocommerce';

    /**
     * @const
     */
    private const TICKET_TIME_EXPIRATION = 3;

    /**
     * @const
     */
    private const PLUGIN_NAME = 'woocommerce-plugins-enablers/woocommerce-mercadopago.php';

    /**
     * @var \WooCommerce
     */
    public $woocommerce;

    /**
     * @var Cache
     */
    public $cache;

    /**
     * @var Strings
     */
    public $strings;

    /**
     * @var Admin
     */
    public $admin;

    /**
     * @var Endpoints
     */
    public $endpoints;

    /**
     * @var Options
     */
    public $options;

    /**
     * @var Meta
     */
    public $meta;

    /**
     * @var Plugin
     */
    public $plugin;

    /**
     * @var Product
     */
    public $product;

    /**
     * @var Template
     */
    public $template;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var Requester
     */
    public $requester;

    /**
     * @var Seller
     */
    public $seller;

    /**
     * @var Country
     */
    public $country;

    /**
     * @var Links
     */
    public $links;

    /**
     * @var Url
     */
    public $url;

    /**
     * @var PaymentMethods
     */
    public $paymentMethods;

    /**
     * @var Store
     */
    public $store;

    /**
     * @var Metadata
     */
    public $metadata;

    /**
     * @var Scripts
     */
    public $scripts;

    /**
     * @var Checkout
     */
    public $checkout;

    /**
     * @var Gateway
     */
    public $gateway;

    /**
     * @var Logs
     */
    public $logs;

    /**
     * @var Nonce
     */
    public $nonce;

    /**
     * @var OrderStatus
     */
    public $orderStatus;

    /**
     * @var CurrentUser
     */
    public $currentUser;

    /**
     * @var Notices
     */
    public $notices;

    /**
     * @var Currency
     */
    public $currency;

    /**
     * @var Settings
     */
    public $settings;

    /**
     * @var MetadataSettings
     */
    public $metadataSettings;

    /**
     * @var AdminTranslations
     */
    public $adminTranslations;

    /**
     * @var StoreTranslations
     */
    public $storeTranslations;

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
        add_action('wp_loaded', [$this, 'init']);
    }

    /**
     * Register gateways
     *
     * @return void
     */
    public function registerGateways(): void
    {
        $this->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\BasicGateway');
        $this->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\CreditsGateway');
        $this->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\CustomGateway');
        $this->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\TicketGateway');
        $this->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\PixGateway');
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
            $this->notices->adminNoticeMissWoocoommerce();
        }

        $this->registerGateways();
    }

    /**
     * Set plugin properties
     *
     * @return void
     */
    public function setProperties(): void
    {
        $dependencies = new Dependencies();

        // Globals
        $this->woocommerce       = $dependencies->woocommerce;

        // Configs
        $this->seller            = $dependencies->seller;
        $this->store             = $dependencies->store;
        $this->metadata          = $dependencies->metadata;

        // Helpers
        $this->cache             = $dependencies->cache;
        $this->country           = $dependencies->country;
        $this->currency          = $dependencies->currency;
        $this->currentUser       = $dependencies->currentUser;
        $this->links             = $dependencies->links;
        $this->requester         = $dependencies->requester;
        $this->strings           = $dependencies->strings;
        $this->url               = $dependencies->url;
        $this->paymentMethods    = $dependencies->paymentMethods;
        $this->nonce             = $dependencies->nonce;
        $this->orderStatus       = $dependencies->orderStatus;

        // Hooks
        $this->admin             = $dependencies->admin;
        $this->checkout          = $dependencies->checkout;
        $this->endpoints         = $dependencies->endpoints;
        $this->gateway           = $dependencies->gateway;
        $this->options           = $dependencies->options;
        $this->meta              = $dependencies->meta;
        $this->order             = $dependencies->order;
        $this->plugin            = $dependencies->plugin;
        $this->product           = $dependencies->product;
        $this->scripts           = $dependencies->scripts;
        $this->template          = $dependencies->template;

        // General
        $this->logs              = $dependencies->logs;
        $this->notices           = $dependencies->notices;
        $this->metadataSettings  = $dependencies->metadataSettings;

        // Exclusive
        $this->settings          = $dependencies->settings;

        // Translations
        $this->adminTranslations = $dependencies->adminTranslations;
        $this->storeTranslations = $dependencies->storeTranslations;
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
                'text'   => $this->adminTranslations->plugin['set_plugin'],
                'href'   => $links['admin_settings_page'],
                'target' => $this->admin::HREF_TARGET_DEFAULT,
            ],
            [
                'text'   => $this->adminTranslations->plugin['payment_method'],
                'href'   => $links['admin_gateways_list'],
                'target' => $this->admin::HREF_TARGET_DEFAULT,
            ],
            [
                'text'   => $this->adminTranslations->plugin['plugin_manual'],
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
        $this->notices->adminNoticeError($this->adminTranslations->notices['php_wrong_version'], false);
    }

    /**
     * Show curl missing notice
     *
     * @return void
     */
    public function verifyCurlNotice(): void
    {
        $this->notices->adminNoticeError($this->adminTranslations->notices['missing_curl'], false);
    }

    /**
     * Show gd missing notice
     *
     * @return void
     */
    public function verifyGdNotice(): void
    {
        $this->notices->adminNoticeWarning($this->adminTranslations->notices['missing_gd_extensions'], false);
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
        $this->define('MP_PRODUCT_ID_DESKTOP', self::PRODUCT_ID_DESKTOP);
        $this->define('MP_PRODUCT_ID_MOBILE', self::PRODUCT_ID_MOBILE);
        $this->define('MP_TICKET_DATE_EXPIRATION', self::TICKET_TIME_EXPIRATION);
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
