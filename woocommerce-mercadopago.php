<?php

/**
 * Plugin Name: Mercado Pago
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: Configure the payment options and accept payments with cards, ticket and money of Mercado Pago account.
 * Version: 7.4.0
 * Author: Mercado Pago
 * Author URI: https://developers.mercadopago.com/
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /i18n/languages/
 * WC requires at least: 5.5.2
 * WC tested up to: 8.1.0
 * Requires PHP: 7.4
 *
 * @package MercadoPago
 */

if (!defined('ABSPATH')) {
    exit;
}

include_once dirname(__FILE__) . '/src/Autoloader.php';

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use MercadoPago\Woocommerce\Autoloader;
use MercadoPago\Woocommerce\Packages;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!Autoloader::init()) {
    return false;
}

if (!Packages::init()) {
    return false;
}

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

function mp_register_activate()
{
    update_option('_mp_execute_activate', 'yes');
}

function mp_disable_plugin(): void
{
    $GLOBALS['mercadopago']->disablePlugin();
}
