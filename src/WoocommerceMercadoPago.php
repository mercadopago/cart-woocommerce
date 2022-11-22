<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\PP\Sdk\HttpClient\HttpClient;
use MercadoPago\PP\Sdk\HttpClient\Requester\CurlRequester;
use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Admin\Translations;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Checkout;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Hooks\Product;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Logs\LogLevels;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Logs\Transports\File;
use MercadoPago\Woocommerce\Logs\Transports\Remote;

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
     * @var Logs
     */
    public $logs;

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
     * @var Notices
     */
    public $notices;

    /**
     * @var Gateway
     */
    public $gateway;

    /**
     * @var Product
     */
    public $product;

    /**
     * @var Scripts
     */
    public $scripts;

    /**
     * @var Order
     */
    public $order;

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
    protected $settings;

    /**
     * @var Translations
     */
    protected $translations;

    /**
     * @var Options
     */
    public $options;

    /**
     * @var Requester
     */
    public $requester;

    /**
     * @var Links
     */
    public $links;

    /**
     * @var Country
     */
    public $country;

    /**
     * @var Endpoints
     */
    public $endpoints;

    /**
     * @var Url
     */
    public $url;

    /**
     * @var Strings
     */
    public $strings;

    /**
     * @var Cache
     */
    public $cache;

    /**
     * @var File
     */
    public $file;

    /**
     * @var LogLevels
     */
    public $logLevels;

    /**
     * @var Remote
     */
    public $remote;

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
        $curlRequester       = new CurlRequester();
        $httpClient          = new HttpClient(Requester::BASEURL_MP, $curlRequester);
        $this->requester     = new Requester($httpClient);
        $this->cache         = new Cache();
        $this->options       = new Options();
        $this->seller        = new Seller($this->cache, $this->options, $this->requester);
        $this->country       = new Country($this->seller);
        $this->links         = new Links($this->country);
        $this->strings       = new Strings();
        $this->url           = new Url($this->strings);
        $this->store         = new Store($this->options);
        $this->logLevels     = new LogLevels();
        $this->file          = new File($this->logLevels, $this->store);
        $this->remote        = new Remote($this->logLevels, $this->store);
        $this->logs          = new Logs($this->file, $this->remote, $this->store);
        $this->scripts       = new Scripts($this->url);
        $this->translations  = new Translations($this->links);
        $this->notices       = new Notices($this->scripts, $this->translations, $this->url);
        $this->admin         = new Admin();
        $this->checkout      = new Checkout($this->scripts);
        $this->endpoints     = new Endpoints();
        $this->gateway       = new Gateway($this->options);
        $this->order         = new Order();
        $this->plugin        = new Plugin();
        $this->product       = new Product();
        $this->settings      = new Settings($this->admin, $this->endpoints, $this->links, $this->plugin, $this->scripts, $this->seller, $this->store, $this->translations, $this->url);
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
