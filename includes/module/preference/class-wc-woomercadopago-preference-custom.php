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
 * Class WC_WooMercadoPago_PreferenceCustom
 */
class WC_WooMercadoPago_Preference_Custom extends WC_WooMercadoPago_Preference_Abstract {

	/**
	 * WC_WooMercadoPago_PreferenceCustom constructor.
	 *
	 * @param WC_WooMercadoPago_Payment_Abstract $payment Payment.
	 * @param object                             $order Order.
	 * @param array|null                         $custom_checkout Custom checkout.
	 */
	public function __construct( $payment, $order, $custom_checkout ) {
		parent::__construct( $payment, $order, $custom_checkout );

		$this->transaction = $this->sdk->getPaymentInstance();
		$this->make_common_transaction();

		$this->transaction->__set('transaction_amount', $this->get_transaction_amount());
		$this->transaction->__set('description', implode( ', ', $this->list_of_items ));
		$this->transaction->__set('installments', (int) $this->checkout['installments']);
		$this->transaction->__set('payment_method_id', $this->checkout['paymentMethodId']);
		$this->transaction->__get('payer')->email = $this->get_email();
		$this->transaction->__set('token', $this->checkout['token']);
		$this->transaction->__set('session_id', $this->checkout['session_id']);

		if ( array_key_exists( 'token', $this->checkout ) ) {
			$this->transaction->metadata['token'] = $this->checkout['token'];
			if ( ! empty( $this->checkout['CustomerId'] ) ) {
				$this->transaction->__get('payer')->id = $this->checkout['CustomerId'];
			}
			if ( ! empty( $this->checkout['issuer'] ) ) {
				$this->transaction->__set('issuer_id', (int) $this->checkout['issuer']);
			}
		}

		$this->transaction->__get('additional_info')->items     = $this->items;
		$this->transaction->__get('additional_info')->payer     = $this->get_payer_custom();
		$this->transaction->__get('additional_info')->shipments = $this->shipments_receiver_address();

		if (
			isset( $this->checkout['discount'] ) && ! empty( $this->checkout['discount'] ) &&
			isset( $this->checkout['coupon_code'] ) && ! empty( $this->checkout['coupon_code'] ) &&
			$this->checkout['discount'] > 0 && 'woo-mercado-pago-custom' === WC()->session->chosen_payment_method
		) {
			$this->transaction->__get('additional_info')->items[] = $this->add_discounts();
			$this->transaction                                    = array_merge( $this->preference, $this->add_discounts_campaign() );
		}
	}

	public function get_internal_metadata() {
		$metadata                  = parent::get_internal_metadata();
		$metadata['checkout']      = 'custom';
		$metadata['checkout_type'] = 'credit_card';
		return $metadata;
	}

	/**
	 * Ship cost item
	 *
	 * @return array
	 */
	public function ship_cost_item() {
		$item = parent::ship_cost_item();
		if ( isset( $item['currency_id'] ) ) {
			unset( $item['currency_id'] );
		}
		return $item;
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

}
