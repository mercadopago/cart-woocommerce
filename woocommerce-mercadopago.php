<?php
/**
 * Plugin Name: Mercado Pago payments for WooCommerce
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: Configure the payment options and accept payments with cards, ticket and money of Mercado Pago account.
 * Version: 6.9.3
 * Author: Mercado Pago
 * Author URI: https://developers.mercadopago.com/
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /i18n/languages/
 * WC requires at least: 5.9
 * WC tested up to: 7.1.0
 *
 * @package MercadoPago
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WC_MERCADOPAGO_BASENAME' ) ) {
	define( 'WC_MERCADOPAGO_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
}
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
if ( ! class_exists( 'WC_WooMercadoPago_Init' ) ) {
	include_once dirname( __FILE__ ) . '/includes/module/class-wc-woomercadopago-init.php';

	register_activation_hook( __FILE__, array( 'WC_WooMercadoPago_Init', 'mercadopago_plugin_activation' ) );
	register_activation_hook( __FILE__, array( 'WC_WooMercadoPago_Init', 'mercadopago_handle_saved_cards_notice' ) );
	add_action( 'plugins_loaded', array( 'WC_WooMercadoPago_Init', 'woocommerce_mercadopago_init' ) );
}
