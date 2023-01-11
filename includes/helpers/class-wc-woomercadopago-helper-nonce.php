<?php
/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * @package MercadoPago
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_WooMercadoPago_Helper_Nonce
 */
class WC_WooMercadoPago_Helper_Nonce {
	/**
	 * Log
	 *
	 * @var WC_WooMercadoPago_Log
	 * */
	private $log;

	/**
	 * Options
	 *
	 * @var WC_WooMercadoPago_Options
	 */
	private $options;

	/**
	 * Is debug mode
	 *
	 * @var mixed|string
	 */
	public $debug_mode;

	/**
	 * Instance variable
	 *
	 * @var WC_WooMercadoPago_Helper_Nonce
	 */
	private static $instance = null;

	/**
	 * Nonce constructor
	 */
	private function __construct() {
		$this->log        = new WC_WooMercadoPago_Log($this);
		$this->options    = WC_WooMercadoPago_Options::get_instance();
		$this->debug_mode = false === $this->options->get_debug_mode() ? 'no' : $this->options->get_instance()->get_debug_mode();
	}

	/**
	 * Get WC_WooMercadoPago_Helper_Nonce instance
	 *
	 * @return WC_WooMercadoPago_Helper_Nonce
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Generate wp_nonce
	 *
	 * @return string
	 */
	public function generate_nonce( $id ) {
		$nonce = wp_create_nonce( $id );

		if ( ! $nonce ) {
			$this->log->write_log( __FUNCTION__, 'Security nonce ' . $id . ' creation failed.' );
			return '';
		}

		return $nonce;
	}

	/**
	 * Retrieves or display nonce hidden field for forms
	 *
	 * @param int|string $id
	 * @param string $fieldName
	 *
	 * @return string
	 */
	public function generate_nonce_field( $id, $fieldName ) {
		return wp_nonce_field( $id, $fieldName );
	}

	/**
	 * Validate wp_nonce
	 *
	 * @param string $id
	 * @param string $nonce
	 *
	 * @return void
	 */
	public function validate_nonce( $id, $nonce ) {
		if ( ! wp_verify_nonce( $nonce, $id ) ) {
			$this->log->write_log(__FUNCTION__, 'Security nonce ' . $id . ' check failed. Nonce: ' . $nonce);
			wp_send_json_error( 'Forbidden', 403 );
		}
	}
}
