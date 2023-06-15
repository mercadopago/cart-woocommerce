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
 * Class WC_WooMercadoPago_Notification_Webhook
 */
class WC_WooMercadoPago_Notification_Webhook extends WC_WooMercadoPago_Notification_Abstract {

	/**
	 * Notification Custom
	 */
	public function check_ipn_response() {
		parent::check_ipn_response();

		$data = $_GET; // phpcs:ignore WordPress.Security.NonceVerification
		header( 'HTTP/1.1 200 OK' );

		if ( isset( $data['coupon_id'] ) && ! empty( $data['coupon_id'] ) ) {
			if ( isset( $data['payer'] ) && ! empty( $data['payer'] ) ) {
				$response = $this->mp->check_discount_campaigns( $data['amount'], $data['payer'], $data['coupon_id'] );
				header( 'Content-Type: application/json' );
				echo wp_json_encode( $response );
			} else {
				$obj           = new stdClass();
				$obj->status   = 404;
				$obj->response = array(
					'message' => __( 'Please enter your email address at the billing address to use this service', 'woocommerce-mercadopago' ),
					'error'   => 'payer_not_found',
					'status'  => 404,
					'cause'   => array(),
				);
				header( 'HTTP/1.1 200 OK' );
				header( 'Content-Type: application/json' );
				echo wp_json_encode( $obj );
			}
			exit( 0 );
		} elseif ( ! isset( $data['data_id'] ) || ! isset( $data['type'] ) ) {
			$this->log->write_log(
				__FUNCTION__,
				'data_id or type not set: ' .
				wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
			);
			if ( ! isset( $data['id'] ) || ! isset( $data['topic'] ) ) {
				$this->log->write_log(
					__FUNCTION__,
					'Mercado Pago Request failure: ' .
					wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
				);
				$this->set_response( 422, null, 'Mercado Pago Request failure' );
			}
		} else {
			if ( 'payment' === $data['type'] ) {
				$payment_id   = preg_replace( '/[^\d]/', '', $data['data_id'] );
				$access_token = $this->mp->get_access_token();

				$payment_info = $this->mp->get(
					'/v1/payments/' . $payment_id,
					array( 'Authorization' => 'Bearer ' . $access_token ),
					false
				);

				if ( ! is_wp_error( $payment_info ) && ( 200 === $payment_info['status'] || 201 === $payment_info['status'] ) ) {
					if ( $payment_info['response'] ) {
						/**
						 * Do action valid_mercadopago_ipn_request.
						 *
						 * @since 3.0.1
						 */
						do_action( 'valid_mercadopago_ipn_request', $payment_info['response'] );

						$this->set_response( 200, 'OK', 'Webhook Notification Successfull' );
					}
				} else {
					$this->log->write_log( __FUNCTION__, 'error when processing received data: ' . wp_json_encode( $payment_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
				}
			}
		}
		$this->set_response( 422, null, 'Mercado Pago Invalid Requisition' );
	}

	/**
	 * Process success response
	 *
	 * @param array $data Payment data.
	 *
	 * @return bool|void|WC_Order|WC_Order_Refund
	 */
	public function successful_request( $data ) {
		try {
			$order  = parent::successful_request( $data );
			$status = $this->process_status_mp_business( $data, $order );
			$this->log->write_log(
				__FUNCTION__,
				'Changing order status to: ' .
				parent::get_wc_status_for_mp_status( str_replace( '_', '', $status ) )
			);
			$this->process_status( $status, $data, $order );
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, $e->getMessage() );
		}
	}

	/**
	 * Check and save customer card
	 *
	 * @param array $checkout_info Checkout info.
	 */
	public function check_and_save_customer_card( $checkout_info ) {
		$this->log->write_log( __FUNCTION__, 'checking info to create card: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		$cost_id           = null;
		$token             = null;
		$issuer_id         = null;
		$payment_method_id = null;
		if ( isset( $checkout_info['payer']['id'] ) && ! empty( $checkout_info['payer']['id'] ) ) {
			$cost_id = $checkout_info['payer']['id'];
		} else {
			return;
		}
		if ( isset( $checkout_info['metadata']['token'] ) && ! empty( $checkout_info['metadata']['token'] ) ) {
			$token = $checkout_info['metadata']['token'];
		} else {
			return;
		}
		if ( isset( $checkout_info['issuer_id'] ) && ! empty( $checkout_info['issuer_id'] ) ) {
			$issuer_id = (int) ( $checkout_info['issuer_id'] );
		}
		if ( isset( $checkout_info['payment_method_id'] ) && ! empty( $checkout_info['payment_method_id'] ) ) {
			$payment_method_id = $checkout_info['payment_method_id'];
		}
		try {
			$this->mp->create_card_in_customer( $cost_id, $token, $payment_method_id, $issuer_id );
		} catch ( WC_WooMercadoPago_Exception $ex ) {
			$this->log->write_log( __FUNCTION__, 'card creation failed: ' . wp_json_encode( $ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}
	}

	/**
	 * Process status mercado pago business
	 *
	 * @param array  $data Payment data.
	 * @param object $order Order.
	 *
	 * @return mixed|string
	 */
	public function process_status_mp_business( $data, $order ) {
		$status        = $data['status'] ?? 'pending';
		$total_paid    = $data['transaction_details']['total_paid_amount'] ?? 0.00;
		$total_refund  = $data['transaction_amount_refunded'] ?? 0.00;
		$coupon_amount = $data['coupon_amount'] ?? 0.00;
		// WooCommerce 3.0 or later.
		// Updates the type of gateway.
		$order->update_meta_data( '_used_gateway', get_class( $this->payment ) );
		if ( ! empty( $data['payer']['email'] ) ) {
			$order->update_meta_data( __( 'Buyer email', 'woocommerce-mercadopago' ), $data['payer']['email'] );
		}
		if ( ! empty( $data['payment_type_id'] ) ) {
			$order->update_meta_data( __( 'Payment type', 'woocommerce-mercadopago' ), $data['payment_type_id'] );
		}
		if ( ! empty( $data['payment_method_id'] ) ) {
			$order->update_meta_data( __( 'Payment method', 'woocommerce-mercadopago' ), $data['payment_method_id'] );
		}
		$order->update_meta_data(
			'Mercado Pago - Payment ' . $data['id'],
			'[Date ' . gmdate( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
			']/[Amount ' . $data['transaction_amount'] .
			']/[Paid ' . $total_paid .
			']/[Coupon ' . $coupon_amount .
			']/[Refund ' . $total_refund . ']'
		);
		$order->update_meta_data( '_Mercado_Pago_Payment_IDs', $data['id'] );
		$order->save();

		return $status;
	}
}
