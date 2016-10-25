<?php
/**
 * Plugin Name: Woo Mercado Pago Module
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: This is the <strong>oficial</strong> module of Mercado Pago for WooCommerce plugin. This module enables WooCommerce to use Mercado Pago as a payment Gateway for purchases made in your e-commerce store.
 * Author: Mercado Pago
 * Author URI: https://www.mercadopago.com.br/developers/
 * Developer: Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright: Copyright(c) MercadoPago [https://www.mercadopago.com]
 * Version: 2.1.3
 * License: https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * Text Domain: woocommerce-mercadopago-module
 * Domain Path: /languages/
 */

/**
 * Implementation references:
 * 1. https://docs.woothemes.com/document/payment-gateway-api/
 * 2. https://www.mercadopago.com.br/developers/en/api-docs/
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// Check if class is already loaded
if ( !class_exists( 'WC_WooMercadoPago_Module' ) ) :

// WooCommerce MercadoPago Module main class
class WC_WooMercadoPago_Module {

	// Singleton design pattern
	protected static $instance = null;
	public static function initMercadoPagoGatewayClass() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	// Class constructor
	private function __construct() {
		// load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		// verify if WooCommerce is already installed
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			include_once 'mercadopago/mercadopago-gateway.php';
			include_once 'mercadopago/mercadopago-custom-gateway.php';
			include_once 'mercadopago/mercadopago-ticket-gateway.php';
			add_filter( 'woocommerce_payment_gateways', array( $this, 'addGateway' ) );
			add_filter( 'woomercadopago_settings_link_' . plugin_basename( __FILE__ ), array( $this, 'woomercadopago_settings_link' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'notifyWooCommerceMiss' ) );
		}
	}

	// As well as defining your class, you need to also tell WooCommerce (WC) that
	// it exists. Do this by filtering woocommerce_payment_gateways.
	public function addGateway( $methods ) {
		$methods[] = 'WC_WooMercadoPago_Gateway';
		$methods[] = 'WC_WooMercadoPagoCustom_Gateway';
		$methods[] = 'WC_WooMercadoPagoTicket_Gateway';
		return $methods;
	}

	// Places a warning error to notify user that WooCommerce is missing
	public function notifyWooCommerceMiss() {
		echo
			'<div class="error"><p>' . sprintf(
				__( 'Woo Mercado Pago Module depends on the last version of %s to execute!', 'woocommerce-mercadopago-module' ),
				'<a href="https://wordpress.org/extend/plugins/woocommerce/">' . 'WooCommerce' . '</a>'
			) .
			'</p></div>';
	}

	// Multi-language plugin
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-mercadopago-module' );
		load_textdomain(
			'woocommerce-mercadopago-module',
			trailingslashit(WP_LANG_DIR ) . 'woocommerce-mercadopago-module/woocommerce-mercadopago-module-' . $locale . '.mo'
		);
		load_plugin_textdomain( 'woocommerce-mercadopago-module', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	public static function woocommerceInstance() {
		if ( function_exists( 'WC' ) ) {
			return WC();
		} else {
			global $woocommerce;
			return $woocommerce;
		}
	}

	public static function getTemplatesPath() {
		return plugin_dir_path( __FILE__ ) . 'templates/';
	}

}

// Payment gateways should be created as additional plugins that hook into WooCommerce.
// Inside the plugin, you need to create a class after plugins are loaded
add_action( 'plugins_loaded', array( 'WC_WooMercadoPago_Module', 'initMercadoPagoGatewayClass' ), 0 );

// Add settings link on plugin page
function woomercadopago_settings_link( $links ) {
	$plugin_links = array();
	$plugin_links[] = '<a href="' . esc_url( admin_url(
		'admin.php?page=wc-settings&tab=checkout&section=WC_WooMercadoPago_Gateway' ) ) . '">' .
		__( 'Basic Checkout', 'woocommerce-mercadopago-module' ) .
	'</a>';
	$plugin_links[] = '<a href="' . esc_url( admin_url(
		'admin.php?page=wc-settings&tab=checkout&section=WC_WooMercadoPagoCustom_Gateway' ) ) . '">' .
		__( 'Custom Checkout', 'woocommerce-mercadopago-module' ) .
	'</a>';
	$plugin_links[] = '<a href="' . esc_url( admin_url(
		'admin.php?page=wc-settings&tab=checkout&section=WC_WooMercadoPagoTicket_Gateway' ) ) . '">' .
		__( 'Ticket', 'woocommerce-mercadopago-module' ) .
	'</a>';
	return array_merge( $plugin_links, $links );
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'woomercadopago_settings_link' );

endif;

?>
