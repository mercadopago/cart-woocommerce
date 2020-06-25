<?php

/**
 * Plugin Name: Mercado Pago payments for WooCommerce
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: Configure the payment options and accept payments with cards, ticket and money of Mercado Pago account.
 * Version: 4.2.1
 * Author: Mercado Pago
 * Author URI: https://developers.mercadopago.com/
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /i18n/languages/
 * WC requires at least: 3.0.0
 * WC tested up to: 4.1.0
 * @package MercadoPago
 * @category Core
 * @author Mercado Pago
 */


// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WC_MERCADOPAGO_BASENAME')) {
    define('WC_MERCADOPAGO_BASENAME', plugin_basename(__FILE__));
}

if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}
/**
 * Summary: Places a warning error to notify user that other older versions are active.
 * Description: Places a warning error to notify user that other older versions are active.
 * @since 3.0.7
 */
function wc_mercado_pago_notify_deprecated_presence()
{
    echo '<div class="error"><p>' .
        __('It seems that you already have the Mercado Pago module installed. Please uninstall it before using this version.', 'woocommerce-mercadopago') .
        '</p></div>';
}

// Check if previously versions are installed, as we can't let both operate.
if (class_exists('WC_WooMercadoPago_Module')) {
    add_action('admin_notices', 'wc_mercado_pago_notify_deprecated_presence');
    return;
} else {
    require_once dirname(__FILE__) . '/includes/admin/notices/WC_WooMercadoPago_Notices.php';
    WC_WooMercadoPago_Notices::initMercadopagoNnotice();
}

/**
 * Load plugin text domain.
 *
 * Need to require here before test for PHP version.
 *
 * @since 3.0.1
 */
function woocommerce_mercadopago_load_plugin_textdomain()
{
    $text_domain = 'woocommerce-mercadopago';
    $locale = apply_filters('plugin_locale', get_locale(), $text_domain);

    $original_language_file = dirname(__FILE__) . '/i18n/languages/woocommerce-mercadopago-' . $locale . '.mo';

    // Unload the translation for the text domain of the plugin
    unload_textdomain($text_domain);
    // Load first the override file
    load_textdomain($text_domain, $original_language_file);
}

add_action('plugins_loaded', 'woocommerce_mercadopago_load_plugin_textdomain');

/**
 * Notice about unsupported PHP version.
 *
 * @since 3.0.1
 */
function wc_mercado_pago_unsupported_php_version_notice()
{
    $type = 'error';
    $message = esc_html__('Mercado Pago payments for WooCommerce requires PHP version 5.6 or later. Please update your PHP version.', 'woocommerce-mercadopago');
    echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
}

// Check for PHP version and throw notice.
if (version_compare(PHP_VERSION, '5.6', '<=')) {
    add_action('admin_notices', 'wc_mercado_pago_unsupported_php_version_notice');
    return;
}

/**
 * Curl validation
 */
function wc_mercado_pago_notify_curl_error()
{
    $type = 'error';
    $message = __('Mercado Pago Error: PHP Extension CURL is not installed.', 'woocommerce-mercadopago');
    echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
}

if (!in_array('curl', get_loaded_extensions())) {
    add_action('admin_notices', 'wc_mercado_pago_notify_curl_error');
    return;
}

/**
 * Summary: Places a warning error to notify user that WooCommerce is missing.
 * Description: Places a warning error to notify user that WooCommerce is missing.
 */
function notify_woocommerce_miss()
{
    $type = 'error';
    $message = sprintf(
        __('The Mercado Pago module needs an active version of %s in order to work!', 'woocommerce-mercadopago'),
        ' <a href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
    );
    echo WC_WooMercadoPago_Notices::getAlertWocommerceMiss($message, $type);
}

add_action('plugins_loaded', 'woocommerce_mercadopago_init');

function woocommerce_mercadopago_init()
{
    // Load Mercado Pago SDK
    require_once dirname(__FILE__) . '/includes/module/sdk/lib/MP.php';

    // Checks with WooCommerce is installed.
    if (class_exists('WC_Payment_Gateway')) {
        require_once dirname(__FILE__) . '/includes/module/config/WC_WooMercadoPago_Constants.php';
        require_once dirname(__FILE__) . '/includes/module/WC_WooMercadoPago_Exception.php';
        require_once dirname(__FILE__) . '/includes/module/WC_WooMercadoPago_Configs.php';
        require_once dirname(__FILE__) . '/includes/module/log/WC_WooMercadoPago_Log.php';
        require_once dirname(__FILE__) . '/includes/module/WC_WooMercadoPago_Module.php';
        require_once dirname(__FILE__) . '/includes/module/WC_WooMercadoPago_Credentials.php';

        WC_WooMercadoPago_Module::init_mercado_pago_class();

        add_action('woocommerce_order_actions', 'add_mp_order_meta_box_actions');
    } else {
        add_action('admin_notices', 'notify_woocommerce_miss');
    }
}

function add_mp_order_meta_box_actions($actions)
{
    $actions['cancel_order'] = __('Cancel order', 'woocommerce-mercadopago');
    return $actions;
}

add_action('woocommerce_settings_checkout', 'mp_show_admin_notices');
/**
 *
 */
function mp_show_admin_notices()
{
    if (!WC_WooMercadoPago_Module::isWcNewVersion() || (isset($_GET['page']) && $_GET['page'] == "wc-settings") && is_plugin_active('woocommerce-admin/woocommerce-admin.php')) {
        return;
    }

    $noticesArray = WC_WooMercadoPago_Module::$notices;
    $notices = array_unique($noticesArray, SORT_STRING);
    foreach ($notices as $notice) {
        echo $notice;
    }
}
