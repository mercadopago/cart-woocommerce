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
	 * Current User constructor
	 */
	private function __construct() {
		$this->log        = new WC_WooMercadoPago_Log($this);
		$this->options    = WC_WooMercadoPago_Options::get_instance();
		$this->debug_mode = false === $this->options->get_debug_mode() ? 'no' : $this->options->get_instance()->get_debug_mode();
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
		$current_user = $this->get_current_user();
		return is_super_admin( $current_user ) || ! empty ( array_intersect( $roles, $current_user->roles ) );
	}

	/**
	 * Validate if user has administrator or editor permissions
	 *
	 * @return void
	 */
	public function validate_user_needed_permissions() {
		$needed_roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

		if ( ! $this->user_has_roles( $needed_roles ) ) {
			$this->log->write_log(__FUNCTION__, 'User does not have permission (need admin or editor).');
			wp_send_json_error('Forbidden', 403);
		}
	}
}
