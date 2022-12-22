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
 * WC_WooMercadoPago_PreferenceAnalytics
 */
class WC_WooMercadoPago_PreferenceAnalytics {

	/**
	 * Ignore_fields variable
	 *
	 * @var array $ignore_fields
	 */
	public static $ignore_fields = [
		'title',
		'description',
		'_mp_public_key_prod',
		'_mp_public_key_test',
		'_mp_access_token_prod',
		'_mp_access_token_test'
	];

	/**
	 * Get basic settings function
	 *
	 * @return array get_basic_settings
	 */
	public function get_basic_settings() {
		return $this->get_settings( 'woocommerce_woo-mercado-pago-basic_settings' );
	}

	/**
	 * Get custom settings function
	 *
	 * @return array get_custom_settings
	 */
	public function get_custom_settings() {
		return $this->get_settings( 'woocommerce_woo-mercado-pago-custom_settings' );
	}

	/**
	 * Get ticket settings function
	 *
	 * @return array get_ticket_settings
	 */
	public function get_ticket_settings() {
		return $this->get_settings( 'woocommerce_woo-mercado-pago-ticket_settings' );
	}

	/**
	 * Get pix settings function
	 *
	 * @return array get_pix_settings
	 */
	public function get_pix_settings() {
		return $this->get_settings( 'woocommerce_woo-mercado-pago-pix_settings' );
	}

	/**
	 * Get credits settings function
	 *
	 * @return array get_credits_settings
	 */
	public function get_credits_settings() {
		return $this->get_settings( 'woocommerce_woo-mercado-pago-credits_settings' );
	}

	/**
	 * Get settings function
	 *
	 * @param string $option
	 * @return array
	 */
	public function get_settings( $option ) {
		$db_options   = get_option( $option, array() );
		$valid_values = array();

		foreach ( $db_options as $key => $value ) {
			if ( ! empty( $value ) && ! in_array( $key, self::$ignore_fields, true ) ) {
				$valid_values[ $key ] = $value;
			}
		}

		return $valid_values;
	}

}
