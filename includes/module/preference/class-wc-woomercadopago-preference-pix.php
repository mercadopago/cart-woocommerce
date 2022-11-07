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
 * Class WC_WooMercadoPago_Preference_Pix
 */
class WC_WooMercadoPago_Preference_Pix extends WC_WooMercadoPago_Preference_Abstract {


	/**
	 * WC_WooMercadoPago_PreferencePix constructor.
	 *
	 * @param WC_WooMercadoPago_Payment_Abstract $payment Payment.
	 * @param object                             $order Order.
	 * @param mixed                              $pix_checkout Pix checkout.
	 */
	public function __construct( $payment, $order, $pix_checkout ) {
		parent::__construct( $payment, $order, $pix_checkout );
		$pix_date_expiration = $this->adjust_pix_date_expiration();

		$this->transaction = $this->sdk->getPaymentInstance();
		$this->make_comum_transaction();

		$this->transaction->date_of_expiration         = $this->get_date_of_expiration( $pix_date_expiration );
		$this->transaction->transaction_amount         = $this->get_transaction_amount();
		$this->transaction->description                = implode( ', ', $this->list_of_items );
		$this->transaction->payment_method_id          = 'pix';
		$this->transaction->external_reference         = $this->get_external_reference();
		$this->transaction->point_of_interaction->type = 'CHECKOUT';

		$this->transaction->payer->email      = $this->get_email();
		$this->transaction->payer->first_name = ( method_exists( $this->order, 'get_id' ) ? html_entity_decode( $this->order->get_billing_first_name() ) : html_entity_decode( $this->order->billing_first_name ) );
		$this->transaction->payer->last_name  = ( method_exists( $this->order, 'get_id' ) ? html_entity_decode( $this->order->get_billing_last_name() ) : html_entity_decode( $this->order->billing_last_name ) );

		$this->transaction->payer->address->zip_code      = html_entity_decode( method_exists( $this->order, 'get_id' ) ? $this->order->get_billing_postcode() : $this->order->billing_postcode );
		$this->transaction->payer->address->street_name   = html_entity_decode( method_exists( $this->order, 'get_id' ) ? $this->order->get_billing_address_1() : $this->order->billing_address_1 );
		$this->transaction->payer->address->street_number = '';
		$this->transaction->payer->address->neighborhood  = '';
		$this->transaction->payer->address->city          = html_entity_decode( method_exists( $this->order, 'get_id' ) ? $this->order->get_billing_city() : $this->order->billing_city );
		$this->transaction->payer->address->federal_unit  = html_entity_decode( method_exists( $this->order, 'get_id' ) ? $this->order->get_billing_state() : $this->order->billing_state );

		$this->transaction->additional_info->items     = $this->items;
		$this->transaction->additional_info->payer     = $this->get_payer_custom();
		$this->transaction->additional_info->shipments = $this->shipments_receiver_address();
	}

	public function get_internal_metadata() {
		$metadata                  = parent::get_internal_metadata();
		$metadata['checkout']      = 'custom';
		$metadata['checkout_type'] = 'pix';
		return $metadata;
	}

	/**
	 * Get items build array
	 *
	 * @return array
	 */
	public function get_items_build_array() {
		$items = parent::get_items_build_array();
		foreach ( $items as $key => $item ) {
			if ( isset( $item['currency_id'] ) ) {
				unset( $items[ $key ]['currency_id'] );
			}
		}

		return $items;
	}

	/**
	 * Adjust old format of pix date expiration
	 *
	 * @return string
	 */
	public function adjust_pix_date_expiration() {
		$old_date_expiration = $this->payment->get_option_mp( 'checkout_pix_date_expiration', '' );

		if ( 1 === strlen( $old_date_expiration ) && '1' === $old_date_expiration ) {
			$new_date_expiration = '24 hours';
			$this->payment->update_option( 'checkout_pix_date_expiration', $new_date_expiration, true);
			return $new_date_expiration;
		} elseif ( 1 === strlen( $old_date_expiration ) ) {
			$new_date_expiration = $old_date_expiration . ' days';
			$this->payment->update_option( 'checkout_pix_date_expiration', $new_date_expiration, true);
			return $new_date_expiration;
		}

		return $old_date_expiration;
	}
}
