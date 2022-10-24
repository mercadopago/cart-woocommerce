<?php

/**
 * Plugin Name: Mercado Pago payments for WooCommerce
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: Configure the payment options and accept payments with cards, ticket and money of Mercado Pago account.
 * Version: 8.0.0
 * Author: Mercado Pago
 * Author URI: https://developers.mercadopago.com/
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /i18n/languages/
 * WC requires at least: 5.5.2
 * WC tested up to: 6.3.0
 * Requires PHP: 7.2
 *
 * @package MercadoPago
 */

if (!defined('ABSPATH')) {
    exit;
}

use MercadoPago\Woocommerce\Autoloader;
use MercadoPago\Woocommerce\Packages;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!Autoloader::init() || !Packages::loadAutoloadPackages()) {
    return false;
}

Packages::init();

if (!class_exists('WoocommerceMercadoPago')) {
    WoocommerceMercadoPago::getInstance();
}
