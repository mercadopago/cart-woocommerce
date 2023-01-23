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
 * Class WC_WooMercadoPago_Helper_Current_Url
 */
class WC_WooMercadoPago_Helper_Current_Url {

	public static function get_current_page() {
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return $current_page;
	}

	public static function get_current_section() {
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return $current_section;
	}

	public static function get_current_url() {
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return $current_url;
	}

	public static function validate_page( $expected_page, $current_page = null, $allow_partial_match = false ) {
		if ( ! $current_page ) {
			$current_page = self::get_current_page();
		}

		return self::compare_strings( $expected_page, $current_page, $allow_partial_match );
	}

	public static function validate_section( $expected_section, $current_section = null, $allow_partial_match = true ) {
		if ( ! $current_section ) {
			$current_section = self::get_current_section();
		}

		return self::compare_strings( $expected_section, $current_section, $allow_partial_match );
	}

	public static function validate_url( $expected_url, $current_url = null, $allow_partial_match = true ) {
		if ( ! $current_url ) {
			$current_url = self::get_current_url();
		}

		return self::compare_strings( $expected_url, $current_url, $allow_partial_match );
	}

	public static function compare_strings( $expected, $current, $allow_partial_match ) {
		if ( $allow_partial_match ) {
			return strpos($current, $expected) !== false;
		}

		return $expected === $current;
	}

}
