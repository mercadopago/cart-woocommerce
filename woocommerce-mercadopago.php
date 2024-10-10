<?php

/**
 * Plugin Name: Mercado Pago
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: Configure the payment options and accept payments with cards, ticket and money of Mercado Pago account.
 * Version: 7.8.1
 * Author: Mercado Pago
 * Author URI: https://developers.mercadopago.com/
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /i18n/languages/
 * WC requires at least: 5.5.2
 * WC tested up to: 9.0.2
 * Requires PHP: 7.4
 *
 * @package MercadoPago
 */

if (!defined('ABSPATH')) {
    exit;
}

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
add_filter('upgrader_post_install', function (bool $response, array $hookExtra): bool {
    if (($hookExtra['plugin'] ?? '') !== plugin_basename(__FILE__)) {
        return $response;
    }
    update_option('_mp_execute_after_update', 1);
    return $response;
}, 10, 2);

function mp_register_activate()
{
    update_option('_mp_execute_activate', 1);
}

function mp_disable_plugin(): void
{
    $GLOBALS['mercadopago']->disablePlugin();
}
