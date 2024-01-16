<?php

namespace MercadoPago\Woocommerce;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Blocks\BasicBlock;
use MercadoPago\Woocommerce\Blocks\CustomBlock;
use MercadoPago\Woocommerce\Blocks\CreditsBlock;
use MercadoPago\Woocommerce\Blocks\PixBlock;
use MercadoPago\Woocommerce\Blocks\TicketBlock;
use MercadoPago\Woocommerce\Configs\Metadata;
use MercadoPago\Woocommerce\Order\OrderBilling;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Order\OrderShipping;
use MercadoPago\Woocommerce\Order\OrderStatus;
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
    private const PLUGIN_VERSION = '7.1.1';

    /**
     * @const
     */
    private const PLUGIN_MIN_PHP = '7.4';

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
    private const PLUGIN_NAME = 'woocommerce-mercadopago/woocommerce-mercadopago.php';

    /**
     * @var \WooCommerce
     */
    public $woocommerce;

    /**
     * @var Hooks
     */
    public $hooks;

    /**
     * @var Helpers
     */
    public $helpers;

    /**
     * @var Settings
     */
    public $settings;

    /**
     * @var Metadata
     */
    public $metadataConfig;

    /**
     * @var Seller
     */
    public $sellerConfig;

    /**
     * @var Store
     */
    public $storeConfig;

    /**
     * @var Logs
     */
    public $logs;

    /**
     * @var OrderBilling
     */
    public $orderBilling;

    /**
     * @var OrderMetadata
     */
    public $orderMetadata;

    /**
     * @var OrderShipping
     */
    public $orderShipping;

    /**
     * @var OrderStatus
     */
    public $orderStatus;

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
        add_action('plugins_loaded', [$this, 'init']);
        add_filter('query_vars', function ($vars) {
            $vars[] = 'wallet_button';
            return $vars;
        });
    }

    /**
     * Register gateways
     *
     * @return void
     */
    public function registerGateways(): void
    {
        $this->hooks->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\BasicGateway');
        $this->hooks->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\CreditsGateway');
        $this->hooks->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\CustomGateway');
        $this->hooks->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\TicketGateway');
        $this->hooks->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\PixGateway');
    }

    /**
     * Register woocommerce blocks support
     *
     * @return void
     */
    public function registerBlocks(): void
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new BasicBlock());
                    $payment_method_registry->register(new CustomBlock());
                    $payment_method_registry->register(new CreditsBlock());
                    $payment_method_registry->register(new PixBlock());
                    $payment_method_registry->register(new TicketBlock());
                }
            );
        }
    }

    /**
     * Register actions when gateway is not called on page
     *
     * @return void
     */
    public function registerActionsWhenGatewayIsNotCalled(): void
    {
        $this->helpers->actions->registerActionWhenGatewayIsNotCalled(
            $this->hooks->product,
            'registerBeforeAddToCartForm',
            'MercadoPago\Woocommerce\Gateways\CreditsGateway',
            'renderCreditsBanner'
        );
    }

    /**
     * Init plugin
     *
     * @return void
     */
    public function init(): void
    {
        if (!class_exists('WC_Payment_Gateway')) {
            $this->adminNoticeMissWoocoommerce();
            return;
        }

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

        $this->registerBlocks();
        $this->registerGateways();
        $this->registerActionsWhenGatewayIsNotCalled();

        $this->hooks->plugin->registerEnableCreditsAction([$this->helpers->creditsEnabled, 'enableCreditsAction']);
        $this->hooks->plugin->executeCreditsAction();
        $this->hooks->plugin->executePluginLoadedAction();
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
        $this->woocommerce = $dependencies->woocommerce;

        // Configs
        $this->storeConfig    = $dependencies->storeConfig;
        $this->sellerConfig   = $dependencies->sellerConfig;
        $this->metadataConfig = $dependencies->metadataConfig;

        // Order
        $this->orderBilling  = $dependencies->orderBilling;
        $this->orderShipping = $dependencies->orderShipping;
        $this->orderMetadata = $dependencies->orderMetadata;
        $this->orderStatus   = $dependencies->orderStatus;

        // Helpers
        $this->helpers = $dependencies->helpers;

        // Hooks
        $this->hooks = $dependencies->hooks;

        // General
        $this->logs = $dependencies->logs;

        // Exclusive
        $this->settings = $dependencies->settings;

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
        $links = $this->helpers->links->getLinks();

        $pluginLinks = [
            [
                'text'   => $this->adminTranslations->plugin['set_plugin'],
                'href'   => $links['admin_settings_page'],
                'target' => $this->hooks->admin::HREF_TARGET_DEFAULT,
            ],
            [
                'text'   => $this->adminTranslations->plugin['payment_method'],
                'href'   => $links['admin_gateways_list'],
                'target' => $this->hooks->admin::HREF_TARGET_DEFAULT,
            ],
            [
                'text'   => $this->adminTranslations->plugin['plugin_manual'],
                'href'   => $links['docs_integration_introduction'],
                'target' => $this->hooks->admin::HREF_TARGET_BLANK,
            ],
        ];

        $this->hooks->admin->registerPluginActionLinks(self::PLUGIN_NAME, $pluginLinks);
    }

    /**
     * Show php version unsupported notice
     *
     * @return void
     */
    public function verifyPhpVersionNotice(): void
    {
        $this->helpers->notices->adminNoticeError($this->adminTranslations->notices['php_wrong_version'], false);
    }

    /**
     * Show curl missing notice
     *
     * @return void
     */
    public function verifyCurlNotice(): void
    {
        $this->helpers->notices->adminNoticeError($this->adminTranslations->notices['missing_curl'], false);
    }

    /**
     * Show gd missing notice
     *
     * @return void
     */
    public function verifyGdNotice(): void
    {
        $this->helpers->notices->adminNoticeWarning($this->adminTranslations->notices['missing_gd_extensions'], false);
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

    /**
     * Show woocommerce missing notice
     * This function should use WordPress features only
     *
     * @return void
     */
    public function adminNoticeMissWoocoommerce(): void
    {
        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_style('woocommerce-mercadopago-admin-notice-css');
            wp_register_style(
                'woocommerce-mercadopago-admin-notice-css',
                sprintf('%s%s', plugin_dir_url(__FILE__), '../assets/css/admin/mp-admin-notices.css'),
                false,
                MP_VERSION
            );
        });

        add_action(
            'admin_notices',
            function () {
                $isInstalled = false;
                $currentUserCanInstallPlugins = current_user_can('install_plugins');

                $minilogo     = sprintf('%s%s', plugin_dir_url(__FILE__), '../assets/images/minilogo.png');
                $translations = [
                    'activate_woocommerce' => __('Activate WooCommerce', 'woocommerce-mercadopago'),
                    'install_woocommerce'  => __('Install WooCommerce', 'woocommerce-mercadopago'),
                    'see_woocommerce'      => __('See WooCommerce', 'woocommerce-mercadopago'),
                    'miss_woocommerce'     => sprintf(
                        __('The Mercado Pago module needs an active version of %s in order to work!', 'woocommerce-mercadopago'),
                        '<a target="_blank" href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
                    ),
                ];

                $activateLink = wp_nonce_url(
                    self_admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=all'),
                    'activate-plugin_woocommerce/woocommerce.php'
                );

                $installLink = wp_nonce_url(
                    self_admin_url('update.php?action=install-plugin&plugin=woocommerce'),
                    'install-plugin_woocommerce'
                );

                if (function_exists('get_plugins')) {
                    $allPlugins  = get_plugins();
                    $isInstalled = !empty($allPlugins['woocommerce/woocommerce.php']);
                }

                if ($isInstalled && $currentUserCanInstallPlugins) {
                    $missWoocommerceAction = 'active';
                } else {
                    if ($currentUserCanInstallPlugins) {
                        $missWoocommerceAction = 'install';
                    } else {
                        $missWoocommerceAction = 'see';
                    }
                }

                include dirname(__FILE__) . '/../templates/admin/notices/miss-woocommerce-notice.php';
            }
        );
    }
}
