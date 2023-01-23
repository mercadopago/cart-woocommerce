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
 * Class WC_WooMercadoPago_Notification_Abstract
 */
abstract class WC_WooMercadoPago_Notification_Abstract {
	/**
	 * Mercado Pago Module
	 *
	 * @var WC_WooMercadoPago_Module
	 */
	public $mp;

	/**
	 * Is sandbox?
	 *
	 * @var true
	 */
	public $sandbox;

	/**
	 * Mergado Pago Log
	 *
	 * @var WC_WooMercadoPago_Log
	 */
	public $log;

	/**
	 * Self!
	 *
	 * @var WC_WooMercadoPago_Payment_Abstract
	 */
	public $payment;

	/**
	 * WC_WooMercadoPago_Notification_Abstract constructor.
	 *
	 * @param WC_WooMercadoPago_Payment_Abstract $payment payment class.
	 */
	public function __construct( $payment ) {
		$this->payment = $payment;
		$this->mp      = $payment->mp;
		$this->log     = $payment->log;
		$this->sandbox = $payment->sandbox;

		add_action( 'woocommerce_api_' . strtolower( get_class( $payment ) ), array( $this, 'check_ipn_response' ) );
		// @todo remove when 5 is the most used.
		add_action( 'woocommerce_api_' . strtolower( preg_replace( '/_gateway/i', 'Gateway', get_class( $payment ) ) ), array( $this, 'check_ipn_response' ) );
		add_action( 'valid_mercadopago_ipn_request', array( $this, 'successful_request' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'process_cancel_order_meta_box_actions' ), 10, 1 );
	}

	/**
	 * Mercado Pago status
	 *
	 * @param string $mp_status Status.
	 * @return string|string[]
	 */
	public static function get_wc_status_for_mp_status( $mp_status ) {
		$defaults = array(
			'pending'     => 'pending',
			'approved'    => 'processing',
			'inprocess'   => 'on_hold',
			'inmediation' => 'on_hold',
			'rejected'    => 'failed',
			'cancelled'   => 'cancelled',
			'refunded'    => 'refunded',
			'chargedback' => 'refunded',
		);
		$status   = $defaults[ $mp_status ];
		return str_replace( '_', '-', $status );
	}

	/**
	 * Log IPN response
	 */
	public function check_ipn_response() {
		// @todo need to be analyzed better
		// @codingStandardsIgnoreLine
		@ob_clean();
		// phpcs:ignore WordPress.Security.NonceVerification
		$this->log->write_log( __FUNCTION__, 'received _get content: ' . wp_json_encode( $_GET, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}


	/**
	 * Process successful request
	 *
	 * @param array $data Preference data.
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	public function successful_request( $data ) {
		$this->log->write_log( __FUNCTION__, 'starting to process  update...' );
		$order_key = $data['external_reference'];

		if ( empty( $order_key ) ) {
			$this->log->write_log( __FUNCTION__, 'External Reference not found' );
			$this->set_response( 422, null, 'External Reference not found' );
		}

		$invoice_prefix = get_option( '_mp_store_identificator', 'WC-' );
		$id             = (int) str_replace( $invoice_prefix, '', $order_key );
		$order          = wc_get_order( $id );

		if ( ! $order ) {
			$this->log->write_log( __FUNCTION__, 'Order is invalid' );
			$this->set_response( 422, null, 'Order is invalid' );
		}

		if ( $order->get_id() !== $id ) {
			$this->log->write_log( __FUNCTION__, 'Order error' );
			$this->set_response( 422, null, 'Order error' );
		}

		$this->log->write_log( __FUNCTION__, 'updating metadata and status with data: ' . wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		return $order;
	}

	/**
	 * Process order status
	 *
	 * @param string $processed_status Status.
	 * @param array  $data Payment data.
	 * @param object $order Order.
	 *
	 * @throws WC_WooMercadoPago_Exception Invalid status response.
	 */
	public function process_status( $processed_status, $data, $order ) {
		$used_gateway = get_class( $this->payment );
		( new WC_WooMercadoPago_Order() )->process_status( $processed_status, $data, $order, $used_gateway );
	}

	/**
	 * Process cancel Order
	 *
	 * @param object $order Order.
	 * @throws WC_WooMercadoPago_Exception
	 */
	public function process_cancel_order_meta_box_actions( $order ) {
		$order_payment = wc_get_order( $order );
		$used_gateway  = ( method_exists( $order_payment, 'get_meta' ) ) ? $order_payment->get_meta( '_used_gateway' ) : get_post_meta( $order_payment->get_id(), '_used_gateway', true );
		$payments      = ( method_exists( $order_payment, 'get_meta' ) ) ? $order_payment->get_meta( '_Mercado_Pago_Payment_IDs' ) : get_post_meta( $order_payment->get_id(), '_Mercado_Pago_Payment_IDs', true );

		if ( 'WC_WooMercadoPago_Custom_Gateway' === $used_gateway ) {
			return;
		}

		$this->log->write_log( __FUNCTION__, 'cancelling payments for ' . $payments );

		// Canceling the order and all of its payments.
		if ( null !== $this->mp && ! empty( $payments ) ) {
			$payment_ids = explode( ', ', $payments );

			foreach ( $payment_ids as $p_id ) {
				$response = $this->mp->cancel_payment( $p_id );
				$status   = $response['status'];
				$this->log->write_log( __FUNCTION__, 'cancel payment of id ' . $p_id . ' => ' . ( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $response['response']['message'] ) );
			}
		} else {
			$this->log->write_log( __FUNCTION__, 'no payments or credentials invalid' );
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
	 * Set response
	 *
	 * @param int    $code         HTTP Code.
	 * @param string $code_message Message.
	 * @param string $body         Body.
	 */
	public function set_response( $code, $code_message, $body ) {
		status_header( $code, $code_message );
		die ( wp_kses_post ($body ));
	}

	public function update_meta( $order, $key, $value ) {
		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( $key, $value );
		} else {
			update_post_meta( $order->id, $key, $value );
		}
	}
}
