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
 * Class WC_WooMercadoPago_Notification_Core
 */
class WC_WooMercadoPago_Notification_Core extends WC_WooMercadoPago_Notification_Abstract {

	/**
	 * SDK Preference
	 */
	protected $sdkNotification;

	public function __construct( $payment ) {
		parent::__construct($payment);

		$sdk                   = $payment->get_sdk_instance();
		$this->sdkNotification = $sdk->getNotificationInstance();
	}

	/**
	 *  IPN
	 */
	public function check_ipn_response() {
		parent::check_ipn_response();

		$notification_id = json_decode(file_get_contents('php://input'));

		// handling old notifications
		if ( gettype($notification_id) === 'object' ) {
			$class = get_class( $this->payment );

			if ( 'WC_WooMercadoPago_Basic_Gateway' === $class ) {
				$notification_handler = new WC_WooMercadoPago_Notification_IPN( $this->payment );
			} else {
				$notification_handler = new WC_WooMercadoPago_Notification_Webhook( $this->payment );
			}

			$notification_handler->check_ipn_response();
			return;
		}

		status_header( 200, 'OK' );

		$this->log->write_log( __FUNCTION__, 'Receiving Core Notification with ID: ' . $notification_id );

		try {
			$notificationEntity = $this->sdkNotification->read(array('id' => $notification_id));

			/**
			 * Do action valid_mercadopago_ipn_request.
			 *
			 * @since 3.0.1
			 */
			do_action( 'valid_mercadopago_ipn_request', $notificationEntity->toArray() );

			$this->set_response( 200, 'OK', 'Successfully Notification by Core' );
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, 'receive notification failed: ' . $e->getMessage() );
			$this->set_response(500, 'Internal Server Error', $e->getMessage());
		}
	}

	/**
	 * Process success response
	 *
	 * @param array $data Payment data.
	 *
	 * @return void
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
		$status = $data['status'];
		try {
			// Updates the type of gateway.
			$this->update_meta( $order, '_used_gateway', get_class( $this->payment ) );

			if ( ! empty( $data['payer']['email'] ) ) {
				$this->update_meta( $order, __( 'Buyer email', 'woocommerce-mercadopago' ), $data['payer']['email'] );
			}

			if ( ! empty( $data['payments_details'] ) ) {
				$payment_ids = array();

				foreach ( $data['payments_details'] as $payment ) {
					$payment_ids[] = $payment['id'];

					$this->update_meta(
						$order,
						'Mercado Pago - Payment ' . $payment['id'],
						'[Date ' . gmdate('Y-m-d H:i:s') .
						']/[Amount ' . $payment['total_amount'] .
						']/[Payment Type ' . $payment['payment_type_id'] .
						']/[Payment Method ' . $payment['payment_method_id'] .
						']/[Paid ' . $payment['paid_amount'] .
						']/[Coupon ' . $payment['coupon_amount'] .
						']/[Refund ' . $data['total_refunded'] . ']'
					);
					$this->update_meta($order, 'Mercado Pago - ' . $payment['id'] . ' - payment_type', $payment['payment_type_id']);

					if ( strpos($payment['payment_type_id'], 'card') !== false ) {
						$this->update_meta($order, 'Mercado Pago - ' . $payment['id'] . ' - installments', $payment['payment_method_info']['installments']);
						$this->update_meta($order, 'Mercado Pago - ' . $payment['id'] . ' - installment_amount', $payment['payment_method_info']['installment_amount']);
						$this->update_meta($order, 'Mercado Pago - ' . $payment['id'] . ' - transaction_amount', $payment['total_amount']);
						$this->update_meta($order, 'Mercado Pago - ' . $payment['id'] . ' - total_paid_amount', $payment['paid_amount']);
						$this->update_meta($order, 'Mercado Pago - ' . $payment['id'] . ' - card_last_four_digits', $payment['payment_method_info']['last_four_digits']);
					}
				}
			}

			if ( count( $payment_ids ) > 0 ) {
				$this->update_meta( $order, '_Mercado_Pago_Payment_IDs', implode( ', ', $payment_ids ) );
			}

			$order->save();

		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, $e->getMessage() );
		}

		return $status;
	}
}
