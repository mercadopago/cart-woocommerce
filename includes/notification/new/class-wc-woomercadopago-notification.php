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

use Helpers\Credentials;
use Helpers\Cryptography;

/**
 * Class WC_WooMercadoPago_Notification
 */
class WC_WooMercadoPago_Notification {

	/**
	 * Static Instance
	 */
	public static $instance = null;

	/**
	 * Mergado Pago Log
	 *
	 * @var Log
	 */
	public $log;

	/**
	 * WC_WooMercadoPago_Notification_Abstrac constructor.
	 */
	public function __construct() {
		$this->log = new Log( 'CoreNotifier' );
		add_action( 'woocommerce_api_wc_mp_notification', array($this, 'check_mp_response'));
	}

	/**
	 * Init Mercado Pago Class
	 *
	 * @return WC_WooMercadoPago_Notification|null
	 * Singleton
	 */
	public static function init_notification_class() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set response
	 *
	 * @param int    $code         HTTP Code.
	 * @param string $code_message Message.
	 * @param string $body         Body.
	 */
	public function set_response( $code, $code_message, $body ) {
		header('Content-Type: application/json');
		status_header( $code, $code_message );

		$obj = array(
			'error' => $body
		);

		// @codingStandardsIgnoreLine
		if ($code > 299){
			die( wp_json_encode($obj) );
		} else {
			die( wp_json_encode($body) );
		}
	}

	/**
	 * Check response
	 */
	public function check_mp_response() {
		if (isset($_SERVER['REQUEST_METHOD'])) {
			// @codingStandardsIgnoreLine
			$method = $_SERVER['REQUEST_METHOD'];

			switch ($method) {
				case 'GET':
					$this->log->write_log(
						__FUNCTION__,
						// @codingStandardsIgnoreLine
						'Request GET from Core Notifier: ' . wp_json_encode($_GET)
					);
					// @codingStandardsIgnoreLine
					$this->get_order($_GET);
					break;

				case 'POST':
					$post = Request::getJsonBody();
					$this->log->write_log(
						__FUNCTION__,
						'Request POST from Core Notifier: ' . wp_json_encode($post)
					);
					$this->post_order($post);
					break;

				default:
					$this->set_response( 405, null, 'Method not allowed' );
					break;
			}
		}
	}

	/**
	 * Get endpoint to retrieve order information
	 */
	public function get_order( $data ) {
		try {
			if (
				isset( $data['payment_id'] ) &&
				isset( $data['external_reference'] ) &&
				isset( $data['timestamp'] )
			) {
				$credentials = new Credentials();
				$secret	     = $credentials->get_access_token();

				if ( is_null($secret) || empty($secret) ) {
					$this->set_response( 500, null, 'Credentials not found' );
				}

				$auth  = Cryptography::encrypt( $data, $secret );
				$token = Request::getBearerToken();

				if ( !$token ) {
					$this->set_response( 401, null, 'Unauthorized' );
				} elseif ( $auth === $token ) {
					$order = wc_get_order( $data['external_reference'] );				
					
					if ( $order ) {

						if ( $this->should_update_meta_data( $order, $data['payment_id'] ) ) {
							$this->update_mp_meta_data( $order, $data['payment_id'] );
						}

						$order_id = $order->get_id();

						$response 						= array();
						$response['order_id'] 			= $order_id;
						$response['external_reference'] = $order_id;
						$response['status'] 			= $this->get_wc_status_for_mp_status_mp($order->get_status());
						$response['created_at'] 		= $order->get_date_created()->getTimestamp();
						$response['total'] 				= $order->get_total();
						$response['timestamp'] 			= time();
						$response['hmac']				= '********************';

						$this->log->write_log(
							__FUNCTION__,
							'Response: ' . wp_json_encode($response));

						$hmac             = Cryptography::encrypt( $response, $secret );
						$response['hmac'] = $hmac;

						$this->set_response( 200, 'Success', $response );
					} else {
						$this->set_response( 404, null, 'Order not found' );
					}
				} else {
					$this->set_response( 401, null, 'Unauthorized' );
				}
			} else {
				$this->set_response(400, null, 'Missing fields');
			}
		} catch (Exception $e) {
			$this->set_response(500, null, $e->getMessage());
		}
	}

	public function should_update_meta_data( $order, $payment_id ) {
		//Woocommerce 3.0 later
		if ( method_exists( $order, 'get_meta' ) ) {
			$payment_id = $order->get_meta( 'Mercado Pago - Payment ' . $payment_id );
		} else {
			$payment_id = get_post_meta( $order->get_id(), 'Mercado Pago - Payment ' . $payment_id , true );
		}

		$this->log->write_log(
			__FUNCTION__,
			'[$payment_id]: ' . $payment_id);
		
		if ( empty($payment_id) ) {
			$this->log->write_log(
				__FUNCTION__,
				'Meta Data must be updated');
			return true;
		}
		
		$this->log->write_log(
			__FUNCTION__,
			'Meta Data should not be updated');
		return false;
		
	}

	public function update_mp_meta_data( $order, $payment_id) {		
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( 'Mercado Pago - Payment ' . $payment_id , $payment_id );
			$this->log->write_log(
				__FUNCTION__,
				'meta data update with "update_meta_data"');
		} else {
			update_post_meta( $order->get_id(), 'Mercado Pago - Payment ' . $payment_id , $payment_id );
			$this->log->write_log(
				__FUNCTION__,
				'meta data update with "update_post_meta"');
		}
		$this->log->write_log(
			__FUNCTION__,
			'Mercado Pago - Payment meta data updated. Value: ' . $payment_id );
		$order->save();
	}

	/**
	 * Post endpoint to update order status
	 */
	public function post_order( $data ) {
		try {
			if (
				isset( $data['status'] ) &&
				isset( $data['timestamp'] ) &&
				isset( $data['payment_id'] ) &&
				isset( $data['external_reference'] ) &&
				isset( $data['checkout'] ) &&
				isset( $data['checkout_type'] ) &&
				isset( $data['order_id'] ) &&
				isset( $data['payment_type_id'] ) &&
				isset( $data['payment_method_id'] ) &&
				isset( $data['payment_created_at'] ) &&
				isset( $data['total'] ) &&
				isset( $data['total_paid'] ) &&
				isset( $data['total_refunded'] )
			) {
				$credentials = new Credentials();
				$secret      = $credentials->get_access_token();

				if ( is_null($secret) || empty($secret) ) {
					$this->set_response( 500, null, 'Credentials not found' );
				}

				$auth  = Request::getBearerToken();
				$token = Cryptography::encrypt( $data, $secret );

				if ($token === $auth) {
					$order = wc_get_order( $data['external_reference'] );

					$response         	    = array();
					$response['old_status'] = $order->get_status();
					$response['new_status'] = $this->successful_request( $data, $order );
					$response['timestamp']  = time();

					$hmac             = Cryptography::encrypt($response, $secret);
					$response['hmac'] = $hmac;

					$this->set_response(200, null, $response );
				} else {
					$this->set_response(401, null, 'Unauthorized');
				}
			} else {
				$this->set_response(400, null, 'Missing fields');
			}
		} catch (Exception $e) {
			$this->set_response(500, null, $e->getMessage());
		}
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
			'in_process'   => 'on_hold',
			'in_mediation' => 'on_hold',
			'rejected'    => 'failed',
			'cancelled'   => 'cancelled',
			'refunded'    => 'refunded',
			'charged_back' => 'refunded',
		);
		$status   = $defaults[ $mp_status ];
		return str_replace( '_', '-', $status );
	}

	/**
	 * Mercado Pago status
	 *
	 * @param string $mp_status Status.
	 * @return string|string[]
	 */
	public static function get_wc_status_for_mp_status_mp( $mp_status ) {
		$defaults = array(
			'pending'     	=> 'pending',
			'processing'  	=> 'approved',
			'on_hold'   	=> 'in_process',
			'failed'    	=> 'rejected',
			'cancelled'   	=> 'cancelled',
			'refunded'    	=> 'refunded',
		);
		$status   = $defaults[ $mp_status ];
		return str_replace( '_', '-', $status );
	}

	/**
	 * Process successful request
	 *
	 * @param array $data data
	 * @param mixed $order WC_Order
	 *
	 * @return string WC_Order_Status
	 */

	public function successful_request( $data, $order ) {
		try {
			$status = $this->process_status_mp_business( $data, $order );
			$this->log->write_log(
				__FUNCTION__,
				'Changing order status to: ' .
				$this->get_wc_status_for_mp_status( str_replace( '_', '', $status ) )
			);
			$this->proccess_status( $status, $data, $order );
			return $order->get_status();
		} catch ( Exception $e ) {
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
		$orderData = $order->get_data();
		$status    = $data['status'] ? $data['status'] : 'pending';
		if ( method_exists( $order, 'update_meta_data' ) ) {
			if ( ! empty( $data['payment_type_id'] ) ) {
				$order->update_meta_data( __( 'Payment type', 'woocommerce-mercadopago' ), $data['payment_type_id'] );
			}
			if ( ! empty( $data['payment_method_id'] ) ) {
				$order->update_meta_data( __( 'Payment method', 'woocommerce-mercadopago' ), $data['payment_method_id'] );
			}

			if ( ! empty( $orderData['billing']['email'] ) ) {
				$order->update_meta_data( __( 'Buyer email', 'woocommerce-mercadopago' ), $orderData['billing']['email'] );
			}
			$payment_id = $data['payment_id'];
			$order->update_meta_data(
				'Mercado Pago - Payment ' . $data['payment_id'],
				'[Date ' . gmdate( 'Y-m-d H:i:s', strtotime( $data['payment_created_at'] ) ) .
				']/[Amount ' . $data['total'] .
				']/[Paid ' . $data['total_paid'] .
				']/[Refund ' . $data['total_refunded'] . ']'
			);
			$order->update_meta_data( '_Mercado_Pago_Payment_IDs', $payment_id);

			$order->save();
		} else {
			if ( ! empty( $data['payment_type_id'] ) ) {
				update_post_meta( $order->id, __( 'Payment type', 'woocommerce-mercadopago' ), $data['payment_type_id'] );
			}
			if ( ! empty( $data['payment_method_id'] ) ) {
				update_post_meta( $order->id, __( 'Payment method', 'woocommerce-mercadopago' ), $data['payment_method_id'] );
			}

			if ( ! empty( $orderData['billing']['email'] ) ) {
				$order->update_meta_data( __( 'Buyer email', 'woocommerce-mercadopago' ), $orderData['billing']['email'] );
			}
			$payment_id = $data['payment_id'];
			update_post_meta(
				$order->id,
				'Mercado Pago - Payment ' . $data['payment_id'],
				'[Date ' . gmdate( 'Y-m-d H:i:s', strtotime( $data['payment_created_at'] ) ) .
				']/[Amount ' . $data['total'] .
				']/[Paid ' . $data['total_paid'] .
				']/[Refund ' . $data['total_refunded'] . ']'
			);
			update_post_meta($order->id, '_Mercado_Pago_Payment_IDs', $payment_id);

		}

		return $status;
	}

	/**
	 * Process order status
	 *
	 * @param array $data data
	 * @param mixed $order WC_Order
	 *
	 * @return string WC_Order_Status
	 */
	public function proccess_status( $processed_status, $data, $order ) {
		switch ( $processed_status ) {
			case 'approved':
				$this->mp_rule_approved( $data, $order );
				break;
			case 'pending':
				$this->mp_rule_pending( $data, $order );
				break;
			case 'in_process':
				$this->mp_rule_in_process( $data, $order );
				break;
			case 'rejected':
				$this->mp_rule_rejected( $data, $order );
				break;
			case 'refunded':
				$this->mp_rule_refunded( $order );
				break;
			case 'cancelled':
				$this->mp_rule_cancelled( $data, $order );
				break;
			case 'in_mediation':
				$this->mp_rule_in_mediation( $order );
				break;
			case 'charged_back':
				$this->mp_rule_charged_back( $order );
				break;
			default:
				throw new WC_WooMercadoPago_Exception( 'Process Status - Invalid Status: ' . $processed_status );
		}
	}

	/**
	 * Rule of approved payment
	 *
	 * @param array  $data Payment data.
	 * @param object $order Order.
	 */
	public function mp_rule_approved( $data, $order ) {
		$order->add_order_note( 'Mercado Pago: ' . __( 'Payment approved.', 'woocommerce-mercadopago' ) );

		$payment_completed_status = apply_filters(
			'woocommerce_payment_complete_order_status',
			$order->needs_processing() ? 'processing' : 'completed',
			$order->get_id(),
			$order
		);

		if ( method_exists( $order, 'get_status' ) && $order->get_status() !== 'completed' ) {
			switch ( $data['checkout_type'] ) {
				case 'pix':
				case 'ticket':
					if ( 'no' === get_option( 'stock_reduce_mode', 'no' ) ) {
						$order->payment_complete();
						if ( 'completed' !== $payment_completed_status ) {
							$order->update_status( self::get_wc_status_for_mp_status( 'approved' ) );
						}
					}
					break;
				default:
					$order->payment_complete();
					if ( 'completed' !== $payment_completed_status ) {
						$order->update_status( self::get_wc_status_for_mp_status( 'approved' ) );
					}
					break;
			}
		}
	}

	/**
	 * Rule of pending
	 *
	 * @param array  $data  Payment data.
	 * @param object $order Order.
	 */
	public function mp_rule_pending( $data, $order ) {
		if ( $this->can_update_order_status( $order ) ) {
			$order->update_status( $this->get_wc_status_for_mp_status( 'pending' ) );
			switch ($data['checkout_type'] ) {
				case 'pix':
					$notes    = $order->get_customer_order_notes();
					$has_note = false;
					if ( count( $notes ) > 1 ) {
						$has_note = true;
						break;
					}
					if ( ! $has_note ) {
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Waiting for the PIX payment.', 'woocommerce-mercadopago' )
						);
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Waiting for the PIX payment.', 'woocommerce-mercadopago' ),
							1,
							false
						);
					}
					break;
				case 'ticket':
					$notes    = $order->get_customer_order_notes();
					$has_note = false;
					if ( count( $notes ) > 1 ) {
						$has_note = true;
						break;
					}
					if ( ! $has_note ) {
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Waiting for the ticket payment.', 'woocommerce-mercadopago' )
						);
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Waiting for the ticket payment.', 'woocommerce-mercadopago' ),
							1,
							false
						);
					}
					break;
				default:
					$order->add_order_note(
						'Mercado Pago: ' . __( 'The customer has not made the payment yet.', 'woocommerce-mercadopago' )
					);
					break;
			}
		} else {
			$this->validate_order_note_type( $data, $order, 'pending' );
		}
	}

	/**
	 * Rule of In Process
	 *
	 * @param array  $data  Payment data.
	 * @param object $order Order.
	 */
	public function mp_rule_in_process( $data, $order ) {
		if ($this->can_update_order_status( $order ) ) {
			$order->update_status(
				$this->get_wc_status_for_mp_status( 'in_process' ),
				'Mercado Pago: ' . __( 'Payment is pending review.', 'woocommerce-mercadopago' )
			);
		} else {
			$this->validate_order_note_type( $data, $order, 'in_process' );
		}
	}

	/**
	 * Rule of Rejected
	 *
	 * @param array  $data  Payment data.
	 * @param object $order Order.
	 */
	public function mp_rule_rejected( $data, $order ) {
		if ( $this->can_update_order_status( $order ) ) {
			$order->update_status(
				$this->get_wc_status_for_mp_status( 'rejected' ),
				'Mercado Pago: ' . __( 'Payment was declined. The customer can try again.', 'woocommerce-mercadopago' )
			);
		} else {
			$this->validate_order_note_type( $data, $order, 'rejected' );
		}
	}

	/**
	 * Rule of Refunded
	 *
	 * @param object $order Order.
	 */
	public function mp_rule_refunded( $order ) {
		$order->update_status(
			$this->get_wc_status_for_mp_status( 'refunded' ),
			'Mercado Pago: ' . __( 'Payment was returned to the customer.', 'woocommerce-mercadopago' )
		);
	}

	/**
	 * Rule of Cancelled
	 *
	 * @param array  $data  Payment data.
	 * @param object $order Order.
	 */
	public function mp_rule_cancelled( $data, $order ) {
		if ( $this->can_update_order_status( $order ) ) {
			$order->update_status(
				$this->get_wc_status_for_mp_status( 'cancelled' ),
				'Mercado Pago: ' . __( 'Payment was canceled.', 'woocommerce-mercadopago' )
			);
		} else {
			$this->validate_order_note_type( $data, $order, 'cancelled' );
		}
	}

	/**
	 * Rule of In mediation
	 *
	 * @param object $order Order.
	 */
	public function mp_rule_in_mediation( $order ) {
		$order->update_status( $this->get_wc_status_for_mp_status( 'in_mediation' ) );
		$order->add_order_note(
			'Mercado Pago: ' . __( 'The payment is in mediation or the purchase was unknown by the customer.', 'woocommerce-mercadopago' )
		);
	}

	/**
	 * Rule of Charged back
	 *
	 * @param object $order Order.
	 */
	public function mp_rule_charged_back( $order ) {
		$order->update_status($this->get_wc_status_for_mp_status( 'charged_back' ) );
		$order->add_order_note(
			'Mercado Pago: ' . __(
				'The payment is in mediation or the purchase was unknown by the customer.',
				'woocommerce-mercadopago'
			)
		);
	}


	/**
	 * Validate Order Note by Type
	 *
	 * @param array  $data Payment Data.
	 * @param object $order Order.
	 * @param string $status Status.
	 */
	protected function validate_order_note_type( $data, $order, $status ) {
		$order->add_order_note(
			sprintf(
			/* translators: 1: payment_id 2: status */
				__( 'Mercado Pago: The payment %1$s was notified by Mercado Pago with status %2$s.', 'woocommerce-mercadopago' ),
				$data,
				$status
			)
		);
	}

	protected function can_update_order_status( $order ) {
		return method_exists( $order, 'get_status' ) && $order->get_status() !== 'completed' && $order->get_status() !== 'processing';
	}
}
