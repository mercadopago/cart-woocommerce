<?php

/**
 * Plugin Name: Mercado Pago
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: Configure the payment options and accept payments with cards, ticket and money of Mercado Pago account.
 * Version: 8.4.4
 * Author: Mercado Pago
 * Author URI: https://developers.mercadopago.com/
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /i18n/languages/
 * WC requires at least: 5.5.2
 * WC tested up to: 9.9.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * @package MercadoPago
 */

if (!defined('ABSPATH')) {
    exit;
}

defined('MP_PLUGIN_FILE') || define('MP_PLUGIN_FILE', __FILE__);

require_once dirname(__FILE__) . '/src/Startup.php';

if (!MercadoPago\Woocommerce\Startup::available()) {
    return false;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

add_action('before_woocommerce_init', function () {
    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__);
    }

    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__);
    }
});

if (!class_exists('WoocommerceMercadoPago')) {
    $GLOBALS['mercadopago'] = new WoocommerceMercadoPago();
}

register_activation_hook(__FILE__, 'mp_register_activate');
register_deactivation_hook(__FILE__, 'mp_disable_plugin');
add_filter('upgrader_post_install', function ($response, array $hookExtra): bool {
    if (!isset($hookExtra['plugin']) || empty($hookExtra['plugin'])) {
        return is_bool($response) ? $response : true;
    }

    if ($hookExtra['plugin'] !== plugin_basename(__FILE__)) {
        return is_bool($response) ? $response : true;
    }

    try {
        update_option('_mp_execute_after_update', 1);
        return is_bool($response) ? $response : true;
    } catch (\Exception $e) {
        error_log('MercadoPago Plugin Update Error: ' . $e->getMessage());
        return true;
    }
}, 10, 2);

function mp_register_activate()
{
    update_option('_mp_execute_activate', 1);
}

function mp_disable_plugin(): void
{
    $GLOBALS['mercadopago']->disablePlugin();
}
