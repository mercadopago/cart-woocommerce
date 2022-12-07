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
 * Class WC_WooMercadoPago_Preference_Basic
 */
class WC_WooMercadoPago_Preference_Basic extends WC_WooMercadoPago_Preference_Abstract {

	/**
	 * WC_WooMercadoPago_Preference_Basic constructor.
	 *
	 * @param WC_WooMercadoPago_Payment_Abstract $payment Payment.
	 * @param object                             $order Order.
	 */
	public function __construct( $payment, $order ) {
		parent::__construct( $payment, $order );
		$this->transaction = $this->sdk->getPreferenceInstance();

		$this->make_common_transaction();
		$this->transaction->__get('items')->setEntity( $this->items );
		$this->transaction->__get('payer')->setEntity( $this->get_payer_basic() );
		$this->transaction->__get('back_urls')->setEntity( $this->get_back_urls() );
		$this->transaction->__get('shipments')->setEntity( $this->shipments_receiver_address() );
		$this->transaction->__get('payment_methods')->setEntity( $this->get_payment_methods( $this->ex_payments, $this->installments ) );
		$this->transaction->__set('auto_return', $this->auto_return());
	}

	public function get_internal_metadata() {
		$metadata                  = parent::get_internal_metadata();
		$metadata['checkout']      = 'smart';
		$metadata['checkout_type'] = $this->payment->get_option_mp( 'method', 'redirect' );
		return $metadata;
	}

	/**
	 * Get payer basic
	 *
	 * @return array
	 */
	public function get_payer_basic() {
		return array(
			'name'    => ( method_exists( $this->order, 'get_id' ) ? html_entity_decode( $this->order->get_billing_first_name() ) : html_entity_decode( $this->order->billing_first_name ) ),
			'surname' => ( method_exists( $this->order, 'get_id' ) ? html_entity_decode( $this->order->get_billing_last_name() ) : html_entity_decode( $this->order->billing_last_name ) ),
			'email'   => $this->order->get_billing_email(),
			'phone'   => array(
				'number' => ( method_exists( $this->order, 'get_id' ) ? $this->order->get_billing_phone() : $this->order->billing_phone ),
			),
			'address' => array(
				'zip_code'    => ( method_exists( $this->order, 'get_id' ) ? $this->order->get_billing_postcode() : $this->order->billing_postcode ),
				'street_name' => html_entity_decode(
					method_exists( $this->order, 'get_id' ) ?
						$this->order->get_billing_address_1() . ' / ' .
						$this->order->get_billing_city() . ' ' .
						$this->order->get_billing_state() . ' ' .
						$this->order->get_billing_country() : $this->order->billing_address_1 . ' / ' .
						$this->order->billing_city . ' ' .
						$this->order->billing_state . ' ' .
						$this->order->billing_country
				),
			),
		);
	}

	/**
	 * Get back URLs
	 *
	 * @return array
	 */
	public function get_back_urls() {
		$success_url = $this->payment->get_option_mp( 'success_url', '' );
		$failure_url = $this->payment->get_option_mp( 'failure_url', '' );
		$pending_url = $this->payment->get_option_mp( 'pending_url', '' );
		return array(
			'success' => empty( $success_url ) ?
				WC_WooMercadoPago_Module::fix_url_ampersand(
					esc_url( $this->get_return_url( $this->order ) )
				) : $success_url,
			'failure' => empty( $failure_url ) ?
				WC_WooMercadoPago_Module::fix_url_ampersand(
					esc_url( $this->order->get_cancel_order_url() )
				) : $failure_url,
			'pending' => empty( $pending_url ) ?
				WC_WooMercadoPago_Module::fix_url_ampersand(
					esc_url( $this->get_return_url( $this->order ) )
				) : $pending_url,
		);
	}

	/**
	 * Get payment methods
	 *
	 * @param array $ex_payments Ex. payments.
	 * @param mixed $installments Installments.
	 * @return array
	 */
	public function get_payment_methods( $ex_payments, $installments ) {
		$excluded_payment_methods = array();

		if ( is_array( $ex_payments ) && count( $ex_payments ) !== 0 ) {
			foreach ( $ex_payments as $excluded ) {
				array_push(
					$excluded_payment_methods,
					array(
						'id' => $excluded,
					)
				);
			}
		}

		return array(
			'installments'             => $this->payment->get_valid_installments($installments),
			'excluded_payment_methods' => $excluded_payment_methods,
		);
	}

	/**
	 * Auto return
	 *
	 * @return string|void
	 */
	public function auto_return() {
		$auto_return = get_option( 'auto_return', 'yes' );
		if ( 'yes' === $auto_return ) {
			return 'approved';
		}
	}
}
