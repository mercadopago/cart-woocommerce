<?php
/**
 * Plugin Name: WooCommerce Mercado Pago
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: This is the <strong>oficial</strong> module of Mercado Pago for WooCommerce plugin. This module enables WooCommerce to use Mercado Pago as a payment Gateway for purchases made in your e-commerce store.
 * Author: Mercado Pago
 * Author URI: https://www.mercadopago.com.br/developers/
 * Developer: Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright: Copyright(c) MercadoPago [http://www.mercadopago.com]
 * Version: 1.0.0
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * Text Domain: woocommerce-mercadopago-module
 * Domain Path: /languages/
 */

/**
 * Implementation references:
 * 1. https://docs.woothemes.com/document/payment-gateway-api/
 * 2. https://www.mercadopago.com.br/developers/en/api-docs/
 */
 
// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// Check if class is already loaded
if (!class_exists('WC_WooMercadoPago_Module')) :

/*
 * WooCommerce MercadoPago Module main class
 */
class WC_WooMercadoPago_Module {

	// Singleton design pattern
	protected static $instance = null;
	public static function initMercadoPagoGatewayClass() {
		if (null == self::$instance) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	// Class constructor
	private function __construct() {
		// load plugin text domain
		add_action('init', array($this, 'load_plugin_textdomain'));
		// verify if WooCommerce is already installed
		if (class_exists('WC_Payment_Gateway')) {
			include_once 'mercadopago/mercadopago-gateway.php';
			add_filter('woocommerce_payment_gateways', array( $this, 'addGateway'));
		} else {
			add_action('admin_notices', array($this, 'notifyWooCommerceMiss'));
		}
	}
	
	// As well as defining your class, you need to also tell WooCommerce (WC) that
	// it exists. Do this by filtering woocommerce_payment_gateways.
	public function addGateway($methods) {
		$methods[] = 'WC_WooMercadoPago_Gateway';
		return $methods;
	}
	
	// Places a warning error to notify user that WooCommerce is missing
	public function notifyWooCommerceMiss() {
		echo
			'<div class="error"><p>' .
			sprintf(
				__('WooCommerce Mercado Pago depends on the last version of %s to execute!', 'woocommerce-mercadopago-module'),
				'<a href="http://wordpress.org/extend/plugins/woocommerce/">' . 'WooCommerce' . '</a>'
			) .
			'</p></div>';
	}
	
	// IPN compatibility with version prior to 2.1
	public static function woocommerceInstance() {
		if (function_exists('WC')) {
			return WC();
		} else {
			global $woocommerce;
			return $woocommerce;
		}
	}
	
	// Multi-language plugin
	public function load_plugin_textdomain() {
		$locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-mercadopago-module');
		load_textdomain('woocommerce-mercadopago-module', trailingslashit(WP_LANG_DIR ) . 'woocommerce-mercadopago-module/woocommerce-mercadopago-module-' . $locale . '.mo');
		load_plugin_textdomain('woocommerce-mercadopago-module', false, dirname(plugin_basename(__FILE__)) . '/languages/');
	}
	
}
	
// Payment gateways should be created as additional plugins that hook into WooCommerce.
// Inside the plugin, you need to create a class after plugins are loaded
add_action('plugins_loaded', array('WC_WooMercadoPago_Module', 'initMercadoPagoGatewayClass'), 0);

// Support to previous IPN implementations
function wcmercadopago_legacy_ipn() {
	if (isset($_GET['topic']) && !isset($_GET['wc-api'])) {
		$woocommerce = WC_WooMercadoPago_Module::woocommerceInstance();
		$woocommerce->payment_gateways();
		do_action('woocommerce_api_wc_woomercadopago_gateway');
	}
}
add_action('init', 'wcmercadopago_legacy_ipn');

endif;

?>
