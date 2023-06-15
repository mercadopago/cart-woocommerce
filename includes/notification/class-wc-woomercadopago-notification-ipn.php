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
 * Class WC_WooMercadoPago_Notification_IPN
 */
class WC_WooMercadoPago_Notification_IPN extends WC_WooMercadoPago_Notification_Abstract {

	/**
	 *  IPN
	 */
	public function check_ipn_response() {
		parent::check_ipn_response();

		$data = $_GET; // phpcs:ignore WordPress.Security.NonceVerification

		if ( isset( $data['data_id'] ) && isset( $data['type'] ) ) {
			status_header( 200, 'OK' );
		}

		if ( ! isset( $data['id'] ) || ! isset( $data['topic'] ) ) {
			$this->log->write_log( __FUNCTION__, 'No ID or TOPIC param in Request IPN.' );
			$this->set_response( 422, null, __( 'No ID or TOPIC param in Request IPN', 'woocommerce-mercadopago' ) );
		}

		if ( 'payment' === $data['topic'] || 'merchant_order' !== $data['topic'] ) {
			$this->set_response( 200, null, __( 'Discarded notification. This notification is already processed as webhook-payment.', 'woocommerce-mercadopago' ) );
		}

		$access_token = $this->mp->get_access_token();
		if ( 'merchant_order' === $data['topic'] ) {
			$merchant_order_id = preg_replace( '/[^\d]/', '', $data['id'] );

			$ipn_info = $this->mp->get(
				'/merchant_orders/' . $merchant_order_id,
				array( 'Authorization' => 'Bearer ' . $access_token ),
				false
			);

			if ( is_wp_error( $ipn_info ) || ( 200 !== $ipn_info['status'] && 201 !== $ipn_info['status'] ) ) {
				$this->log->write_log( __FUNCTION__, ' IPN merchant_order not found ' . wp_json_encode( $ipn_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
				$this->set_response( 422, null, __( 'IPN merchant_order not found', 'woocommerce-mercadopago' ) );
			}

			$payments = $ipn_info['response']['payments'];
			if ( count( $payments ) < 1 ) {
				$this->log->write_log( __FUNCTION__, 'Not found Payments into Merchant_Order' );
				$this->set_response( 422, null, __( 'Not found Payments into Merchant_Order', 'woocommerce-mercadopago' ) );
			}

			$ipn_info['response']['ipn_type'] = 'merchant_order';

			/**
			 * Do action valid_mercadopago_ipn_request.
			 *
			 * @since 3.0.1
			 */
			do_action( 'valid_mercadopago_ipn_request', $ipn_info['response'] );

			$this->set_response( 200, 'OK', 'Notification IPN Successfull' );
		}
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
			$order            = parent::successful_request( $data );
			$processed_status = $this->process_status_mp_business( $data, $order );
			$this->log->write_log( __FUNCTION__, 'Changing order status to: ' . parent::get_wc_status_for_mp_status( str_replace( '_', '', $processed_status ) ) );
			$this->process_status( $processed_status, $data, $order );
		} catch ( Exception $e ) {
			$this->set_response( 422, null, $e->getMessage() );
			$this->log->write_log( __FUNCTION__, $e->getMessage() );
		}
	}

	/**
	 * Process status
	 *
	 * @param array  $data Payment data.
	 * @param object $order Order.
	 * @return string
	 */
	public function process_status_mp_business( $data, $order ) {
		$status   = 'pending';
		$payments = $data['payments'];

		if ( is_array($payments) ) {
			$total        = $data['shipping_cost'] + $data['total_amount'];
			$total_paid   = 0.00;
			$total_refund = 0.00;
			// Grab some information...
			foreach ( $data['payments'] as $payment ) {
				$coupon_mp = $this->get_payment_info($payment['id']);

				if ( $coupon_mp > 0 ) {
					$total_paid += (float) $coupon_mp;
				}

				if ( 'approved' === $payment['status'] ) {
					// Get the total paid amount, considering only approved incomings.
					$total_paid += (float) $payment['total_paid_amount'];
				} elseif ( 'refunded' === $payment['status'] ) {
					// Get the total refounded amount.
					$total_refund += (float) $payment['amount_refunded'];
				}
			}

			if ( $total_paid >= $total ) {
				$status = 'approved';
			} elseif ( $total_refund >= $total ) {
				$status = 'refunded';
			} else {
				$status = 'pending';
			}
		}
		// WooCommerce 3.0 or later.
		// Updates the type of gateway.
		$order->update_meta_data( '_used_gateway', 'WC_WooMercadoPago_Basic_Gateway' );
		if ( ! empty( $data['payer']['email'] ) ) {
			$order->update_meta_data( __( 'Buyer email', 'woocommerce-mercadopago' ), $data['payer']['email'] );
		}
		if ( ! empty( $data['payment_type_id'] ) ) {
			$order->update_meta_data( __( 'Payment type', 'woocommerce-mercadopago' ), $data['payment_type_id'] );
		}
		if ( ! empty( $data['payment_method_id'] ) ) {
			$order->update_meta_data( __( 'Payment method', 'woocommerce-mercadopago' ), $data['payment_method_id'] );
		}
		if ( ! empty( $data['payments'] ) ) {
			$payment_ids = array();
			foreach ( $data['payments'] as $payment ) {
				$coupon_mp     = $this->get_payment_info($payment['id']);
				$payment_ids[] = $payment['id'];
				$order->update_meta_data(
					'Mercado Pago - Payment ' . $payment['id'],
					'[Date ' . gmdate( 'Y-m-d H:i:s', strtotime( $payment['date_created'] ) ) .
					']/[Amount ' . $payment['transaction_amount'] .
					']/[Paid ' . $payment['total_paid_amount'] .
					']/[Coupon ' . $coupon_mp .
					']/[Refund ' . $payment['amount_refunded'] . ']'
				);
			}
			if ( count( $payment_ids ) > 0 ) {
				$order->update_meta_data( '_Mercado_Pago_Payment_IDs', implode( ', ', $payment_ids ) );
			}
		}
		$order->save();
		return $status;
	}
	public function get_payment_info( $id ) {
		$payment_info  = $this->mp->search_payment_v1($id);
		$coupon_amount = (float) $payment_info['response']['coupon_amount'];

		return $coupon_amount;
	}
}
