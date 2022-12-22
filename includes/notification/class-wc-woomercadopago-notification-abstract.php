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
		// @todo check nonce
		// @codingStandardsIgnoreLine
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

		switch ( $processed_status ) {
			case 'approved':
				$this->mp_rule_approved( $data, $order, $used_gateway );
				break;
			case 'pending':
				$this->mp_rule_pending( $data, $order, $used_gateway );
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
	 * @param string $used_gateway Class of gateway.
	 */
	public function mp_rule_approved( $data, $order, $used_gateway ) {
		if ( 'partially_refunded' === $data['status_detail'] ) {
			return;
		}

		$status = $order->get_status();

		if ( 'pending' === $status || 'on-hold' === $status || 'failed' === $status ) {
			$order->add_order_note( 'Mercado Pago: ' . __( 'Payment approved.', 'woocommerce-mercadopago' ) );

			/**
			 * Apply filters woocommerce_payment_complete_order_status.
			 *
			 * @since 3.0.1
			 */
			$payment_completed_status = apply_filters(
				'woocommerce_payment_complete_order_status',
				$order->needs_processing() ? 'processing' : 'completed',
				$order->get_id(),
				$order
			);

			if ( method_exists( $order, 'get_status' ) && $order->get_status() !== 'completed' ) {
				switch ( $used_gateway ) {
					case 'WC_WooMercadoPago_Ticket_Gateway':
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
	}

	/**
	 * Rule of pending
	 *
	 * @param array  $data         Payment data.
	 * @param object $order        Order.
	 * @param string $used_gateway Gateway Class.
	 */
	public function mp_rule_pending( $data, $order, $used_gateway ) {
		if ( $this->can_update_order_status( $order ) ) {
			$order->update_status( self::get_wc_status_for_mp_status( 'pending' ) );
			switch ( $used_gateway ) {
				case 'WC_WooMercadoPago_Pix_Gateway':
					$notes    = $order->get_customer_order_notes();
					$has_note = false;

					if ( count( $notes ) > 1 ) {
						$has_note = true;
						break;
					}
					if ( ! $has_note ) {
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Waiting for the Pix payment.', 'woocommerce-mercadopago' )
						);
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Waiting for the Pix payment.', 'woocommerce-mercadopago' ),
							1,
							false
						);
					}
					break;

				case 'WC_WooMercadoPago_Ticket_Gateway':
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
		if ( $this->can_update_order_status( $order ) ) {
			$order->update_status(
				self::get_wc_status_for_mp_status( 'inprocess' ),
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
				self::get_wc_status_for_mp_status( 'rejected' ),
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
			self::get_wc_status_for_mp_status( 'refunded' ),
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
				self::get_wc_status_for_mp_status( 'cancelled' ),
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
		$order->update_status( self::get_wc_status_for_mp_status( 'inmediation' ) );
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
		$order->update_status( self::get_wc_status_for_mp_status( 'chargedback' ) );
		$order->add_order_note(
			'Mercado Pago: ' . __(
				'The payment is in mediation or the purchase was unknown by the customer.',
				'woocommerce-mercadopago'
			)
		);
	}

	/**
	 * Process cancel Order
	 *
	 * @param object $order Order.
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
	 * Can update order status?
	 *
	 * @param object $order Order.
	 *
	 * @return bool
	 */
	protected function can_update_order_status( $order ) {

		return method_exists( $order, 'get_status' ) && $order->get_status() !== 'completed' && $order->get_status() !== 'processing';
	}

	/**
	 * Validate Order Note by Type
	 *
	 * @param array  $data Payment Data.
	 * @param object $order Order.
	 * @param string $status Status.
	 */
	protected function validate_order_note_type( $data, $order, $status ) {
		$payment_id = $data['id'];

		if ( isset( $data['ipn_type'] ) && 'merchant_order' === $data['ipn_type'] ) {
			$payments = array();
			foreach ( $data['payments'] as $payment ) {
				$payments[] = $payment['id'];
			}

			$payment_id = implode( ',', $payments );
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: payment_id 2: status */
				__( 'Mercado Pago: The payment %1$s was notified by Mercado Pago with status %2$s.', 'woocommerce-mercadopago' ),
				$payment_id,
				$status
			)
		);
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
		// @todo need to implements better
		// @codingStandardsIgnoreLine
		die( $body );
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
