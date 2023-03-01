<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderStatus
{
    /**
     * @var StoreTranslations
     */
    private $storeTranslations;

    /**
     * Order constructor
     */
    public function __construct(StoreTranslations $storeTranslations)
    {
        $this->storeTranslations = $storeTranslations;
    }

    /**
     * Get order status message
     *
     * @param string $statusDetail
     *
     * @return string
     */
    public function getOrderStatusMessage(string $statusDetail): string
    {
        $messages = $this->storeTranslations->commonMessages;

        switch ($statusDetail) {
            case 'accredited':
                return $messages['cho_accredited'];
            case 'pending_contingency':
                return $messages['cho_pending_contingency'];
            case 'pending_review_manual':
                return $messages['cho_pending_review_manual'];
            case 'cc_rejected_bad_filled_card_number':
                return $messages['cho_cc_rejected_bad_filled_card_number'];
            case 'cc_rejected_bad_filled_date':
                return $messages['cho_cc_rejected_bad_filled_date'];
            case 'cc_rejected_bad_filled_other':
                return $messages['cho_cc_rejected_bad_filled_other'];
            case 'cc_rejected_bad_filled_security_code':
                return $messages['cho_cc_rejected_bad_filled_security_code'];
            case 'cc_rejected_card_error':
                return $messages['cho_cc_rejected_card_error'];
            case 'cc_rejected_blacklist':
                return $messages['cho_cc_rejected_blacklist'];
            case 'cc_rejected_call_for_authorize':
                return $messages['cho_cc_rejected_call_for_authorize'];
            case 'cc_rejected_card_disabled':
                return $messages['cho_cc_rejected_card_disabled'];
            case 'cc_rejected_duplicated_payment':
                return $messages['cho_cc_rejected_duplicated_payment'];
            case 'cc_rejected_high_risk':
                return $messages['cho_cc_rejected_high_risk'];
            case 'cc_rejected_insufficient_amount':
                return $messages['cho_cc_rejected_insufficient_amount'];
            case 'cc_rejected_invalid_installments':
                return $messages['cho_cc_rejected_invalid_installments'];
            case 'cc_rejected_max_attempts':
                return $messages['cho_cc_rejected_max_attempts'];
            default:
                return $messages['cho_default'];
        }
    }

	/**
	 * Process order status
	 *
	 * @param string $processed_Status
	 * @param array  $data
	 * @param object $order
	 * @param string $usedGateway
	 *
	 * @throws \Exception Invalid status response.
	 */
	public function processStatus($processedStatus, $data, $order, $usedGateway) {
		switch ($processedStatus) {
			case 'approved':
				$this->approvedFlow($data, $order, $usedGateway);
				break;
			case 'pending':
				$this->pendingFlow($data, $order, $usedGateway);
				break;
			case 'in_process':
				$this->inProcessFlow($data, $order);
				break;
			case 'rejected':
				$this->rejectedFlow($data, $order);
				break;
			case 'refunded':
				$this->refundedFlow($order);
				break;
			case 'cancelled':
				$this->cancelledFlow($data, $order);
				break;
			case 'in_mediation':
				$this->inMediationFlow($order);
				break;
			case 'charged_back':
				$this->chargedBackFlow($order);
				break;
			default:
				throw new \Exception('Process Status - Invalid Status: ' . $processedStatus);
		}
	}

	/**
	 * Rule of approved payment
	 *
	 * @param array  $data Payment data.
	 * @param object $order Order.
	 * @param string $usedGateway Class of gateway.
	 */
	private function approvedFlow( $data, $order, $usedGateway ) {
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
				switch ( $usedGateway ) {
					case 'MercadoPago\Woocommerce\Gateways\TicketGateway':
						if ( 'no' === get_option( 'stock_reduce_mode', 'no' ) ) {
							$order->payment_complete();
							if ( 'completed' !== $payment_completed_status ) {
								$order->update_status( self::mapMpStatusToWoocommerceStatus( 'approved' ) );
							}
						}
						break;

					default:
						$order->payment_complete();
						if ( 'completed' !== $payment_completed_status ) {
							$order->update_status( self::mapMpStatusToWoocommerceStatus( 'approved' ) );
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
	 * @param string $usedGateway Gateway Class.
	 */
	private function pendingFlow( $data, $order, $usedGateway ) {
		if ( $this->canUpdateOrderStatus( $order ) ) {
			$order->update_status( self::mapMpStatusToWoocommerceStatus( 'pending' ) );
			switch ( $usedGateway ) {
				case 'MercadoPago\Woocommerce\Gateways\PixGateway':
					$notes = $order->get_customer_order_notes();
					if ( count( $notes ) > 1 ) {
						break;
					}

					$order->add_order_note(
						'Mercado Pago: ' . __( 'Waiting for the Pix payment.', 'woocommerce-mercadopago' )
					);

					$order->add_order_note(
						'Mercado Pago: ' . __( 'Waiting for the Pix payment.', 'woocommerce-mercadopago' ),
						1,
						false
					);
					break;

				case 'MercadoPago\Woocommerce\Gateways\TicketGateway':
					$notes = $order->get_customer_order_notes();
					if ( count( $notes ) > 1 ) {
						break;
					}

					$order->add_order_note(
						'Mercado Pago: ' . __( 'Waiting for the ticket payment.', 'woocommerce-mercadopago' )
					);

					$order->add_order_note(
						'Mercado Pago: ' . __( 'Waiting for the ticket payment.', 'woocommerce-mercadopago' ),
						1,
						false
					);
					break;

				default:
					$order->add_order_note(
						'Mercado Pago: ' . __( 'The customer has not made the payment yet.', 'woocommerce-mercadopago' )
					);
					break;
			}
		} else {
			$this->validateOrderNoteType( $data, $order, 'pending' );
		}
	}

	/**
	 * Rule of In Process
	 *
	 * @param array  $data  Payment data.
	 * @param object $order Order.
	 */
	private function inProcessFlow( $data, $order ) {
		if ( $this->canUpdateOrderStatus( $order ) ) {
			$order->update_status(
				self::mapMpStatusToWoocommerceStatus( 'inprocess' ),
				'Mercado Pago: ' . __( 'Payment is pending review.', 'woocommerce-mercadopago' )
			);
		} else {
			$this->validateOrderNoteType( $data, $order, 'in_process' );
		}
	}

	/**
	 * Rule of Rejected
	 *
	 * @param array  $data  Payment data.
	 * @param object $order Order.
	 */
	private function rejectedFlow( $data, $order ) {
		if ( $this->canUpdateOrderStatus( $order ) ) {
			$order->update_status(
				self::mapMpStatusToWoocommerceStatus( 'rejected' ),
				'Mercado Pago: ' . __( 'Payment was declined. The customer can try again.', 'woocommerce-mercadopago' )
			);
		} else {
			$this->validateOrderNoteType( $data, $order, 'rejected' );
		}
	}

	/**
	 * Rule of Refunded
	 *
	 * @param object $order Order.
	 */
	private function refundedFlow( $order ) {
		$order->update_status(
			self::mapMpStatusToWoocommerceStatus( 'refunded' ),
			'Mercado Pago: ' . __( 'Payment was returned to the customer.', 'woocommerce-mercadopago' )
		);
	}

	/**
	 * Rule of Cancelled
	 *
	 * @param array  $data  Payment data.
	 * @param object $order Order.
	 */
	private function cancelledFlow( $data, $order ) {
		if ( $this->canUpdateOrderStatus( $order ) ) {
			$order->update_status(
				self::mapMpStatusToWoocommerceStatus( 'cancelled' ),
				'Mercado Pago: ' . __( 'Payment was canceled.', 'woocommerce-mercadopago' )
			);
		} else {
			$this->validateOrderNoteType( $data, $order, 'cancelled' );
		}
	}

	/**
	 * Rule of In mediation
	 *
	 * @param object $order Order.
	 */
	private function inMediationFlow( $order ) {
		$order->update_status( self::mapMpStatusToWoocommerceStatus( 'inmediation' ) );
		$order->add_order_note(
			'Mercado Pago: ' . __( 'The payment is in mediation or the purchase was unknown by the customer.', 'woocommerce-mercadopago' )
		);
	}

	/**
	 * Rule of Charged back
	 *
	 * @param object $order Order.
	 */
	private function chargedBackFlow( $order ) {
		$order->update_status( self::mapMpStatusToWoocommerceStatus( 'chargedback' ) );
		$order->add_order_note(
			'Mercado Pago: ' . __(
				'The payment is in mediation or the purchase was unknown by the customer.',
				'woocommerce-mercadopago'
			)
		);
	}

	/**
	 * Mercado Pago status
	 *
	 * @param string $mpStatus Status.
	 * @return string
	 */
	public static function mapMpStatusToWoocommerceStatus( $mpStatus ) {
		$statusMap = array(
			'pending'     => 'pending',
			'approved'    => 'processing',
			'inprocess'   => 'on_hold',
			'inmediation' => 'on_hold',
			'rejected'    => 'failed',
			'cancelled'   => 'cancelled',
			'refunded'    => 'refunded',
			'chargedback' => 'refunded',
		);
		$status   = $statusMap[ $mpStatus ];
		return str_replace( '_', '-', $status );
	}

	/**
	 * Can update order status?
	 *
	 * @param object $order Order.
	 *
	 * @return bool
	 */
	protected function canUpdateOrderStatus( $order ) {
		return method_exists( $order, 'get_status' ) &&
			$order->get_status() !== 'completed' &&
			$order->get_status() !== 'processing';
	}

	/**
	 * Validate Order Note by Type
	 *
	 * @param array  $data Payment Data.
	 * @param object $order Order.
	 * @param string $status Status.
	 */
	protected function validateOrderNoteType( $data, $order, $status ) {
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
}
