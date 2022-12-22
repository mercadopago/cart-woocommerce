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
 * Class WC_WooMercadoPago_Helper_Credits
 */
class WC_WooMercadoPago_Helper_Credits {

	/**
	 * Mercado Pago
	 *
	 * @var MP|null
	 */
	public $mp;

	/**
	 * Options
	 *
	 * @var WC_WooMercadoPago_Options
	 */
	public $mp_options;

	public function __construct() {
		$this->mp = WC_WooMercadoPago_Module::get_mp_instance_singleton();

		if ( null === $this->mp_options ) {
			$this->mp_options = WC_WooMercadoPago_Options::get_instance();
		}
		return $this->mp_options;
	}

	/**
	 * Get Payment Response function
	 *
	 * @return bool
	 */
	public function is_credits() {
		$site              = strtoupper($this->mp_options->get_site_id());
		$payments_response = $this->mp->get_payment_response_by_sites($site);
		if ( is_array($payments_response) ) {
			foreach ( $payments_response as $payment ) {
				if ( isset( $payment['id'] ) && 'consumer_credits' === $payment['id'] ) {
					return true;
				}
			}
		}
		return false;
	}
}
