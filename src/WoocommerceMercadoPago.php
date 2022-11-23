<?php

namespace MercadoPago\Woocommerce;

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
    }

    /**
     * Set plugin configuration links
     *
     * @return void
     */
    public function setPluginSettingsLink()
    {
        $links = $this->dependencies->links->getLinks();

        $pluginLinks = [
            [
                'text'   => $this->dependencies->translations->plugin['set_plugin'],
                'href'   => $links['admin_settings_page'],
                'target' => $this->dependencies->admin::HREF_TARGET_DEFAULT,
            ],
            [
                'text'   => $this->dependencies->translations->plugin['payment_method'],
                'href'   => $links['admin_gateways_list'],
                'target' => $this->dependencies->admin::HREF_TARGET_DEFAULT,
            ],
            [
                'text'   => $this->dependencies->translations->plugin['plugin_manual'],
                'href'   => $links['docs_integration_introduction'],
                'target' => $this->dependencies->admin::HREF_TARGET_BLANK,
            ],
        ];

        $this->dependencies->admin->registerPluginActionLinks(self::PLUGIN_NAME, $pluginLinks);
    }

    /**
     * Show php version unsupported notice
     *
     * @return void
     */
    public function verifyPhpVersionNotice(): void
    {
        $this->dependencies->notices->adminNoticeError($this->dependencies->translations->notices['php_wrong_version'], false);
    }

    /**
     * Show curl missing notice
     *
     * @return void
     */
    public function verifyCurlNotice(): void
    {
        $this->dependencies->notices->adminNoticeError($this->dependencies->translations->notices['missing_curl'], false);
    }

    /**
     * Show gd missing notice
     *
     * @return void
     */
    public function verifyGdNotice(): void
    {
        $this->dependencies->notices->adminNoticeWarning($this->dependencies->translations->notices['missing_gd_extensions'], false);
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
