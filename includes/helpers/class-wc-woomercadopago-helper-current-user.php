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

class WC_WooMercadoPago_Helper_Current_User {
	/**
	 * Log
	 *
	 * @var WC_WooMercadoPago_Log
	 * */
	private $log;

	/**
	 * Instance variable
	 *
	 * @var WC_WooMercadoPago_Helper_Nonce
	 */
	private static $instance = null;

	/**
	 * Current User constructor
	 */
	private function __construct() {
		$this->log = new WC_WooMercadoPago_Log($this);
	}

	/**
	 * Get WC_WooMercadoPago_Helper_Current_User instance
	 *
	 * @return WC_WooMercadoPago_Helper_Current_User
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get current user roles
	 *
	 * @return WP_User
	 */
	public function get_current_user() {
		return wp_get_current_user();
	}

	/**
	 * Verify if current_user has specifics roles
	 *
	 * @param array $roles 'administrator | editor | author | contributor | subscriber'
	 *
	 * @return bool
	 */
	public function user_has_roles( $roles ) {
		return ! empty ( array_intersect( $roles, $this->get_current_user()->roles) );
	}

	/**
	 * Validate if user has administrator or editor permissions
	 *
	 * @return void
	 */
	public function validate_user_needed_permissions() {
		$needed_roles = ['administrator', 'editor'];

		if ( ! $this->user_has_roles( $needed_roles ) ) {
			$this->log->write_log(__FUNCTION__, 'User does not have permission (need admin or editor).');
			wp_send_json_error('Forbidden', 403);
		}
	}
}
