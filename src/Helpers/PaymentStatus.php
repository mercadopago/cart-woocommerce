<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class PaymentStatus
{
    /**
     * Get Status Type
     * 
	 * @param $paymenStatus
     *
     * @return string
     */
    public static function getStatusType($paymentStatus): string
    {
        $paymentStatusMap = [
			'approved'     => 'success',
			'authorized'   => 'success',
			'pending'      => 'pending',
			'in_process'   => 'pending',
			'in_mediation' => 'pending',
			'rejected'     => 'rejected',
			'canceled'     => 'rejected',
			'refunded'     => 'refunded',
			'charged_back' => 'charged_back',
			'generic'      => 'rejected'
		];

		return array_key_exists($paymentStatus, $paymentStatusMap) ? $paymentStatusMap[$paymentStatus] : $paymentStatusMap['generic'];
    }

    /**
	 * Get Card Description
	 *
	 * @param $paymentStatusDetail
	 * @param $isCreditCard
	 *
	 * @return array
	 */
	public static function getCardDescription( $paymentStatusDetail, $isCreditCard ) {
		$paymentStatusDetailMap = [
			'accredited' => array(
				'alert_title' => __( 'Payment made', 'woocommerce-mercadopago' ),
				'description' => __( 'Payment made by the buyer and already credited in the account.', 'woocommerce-mercadopago' ),
			),
			'settled' => array(
				'alert_title' => __( 'Call resolved', 'woocommerce-mercadopago' ),
				'description' => __( 'Please contact Mercado Pago for further details.', 'woocommerce-mercadopago' ),
			),
			'reimbursed' => array(
				'alert_title' => __( 'Payment refunded', 'woocommerce-mercadopago' ),
				'description' => __( 'Your refund request has been made. Please contact Mercado Pago for further details.', 'woocommerce-mercadopago' ),
			),
			'refunded' => array(
				'alert_title' => __( 'Payment returned', 'woocommerce-mercadopago' ),
				'description' => __( 'The payment has been returned to the client.', 'woocommerce-mercadopago' ),
			),
			'partially_refunded' => array(
				'alert_title' => __( 'Payment returned', 'woocommerce-mercadopago' ),
				'description' => __( 'The payment has been partially returned to the client.', 'woocommerce-mercadopago' ),
			),
			'by_collector' => array(
				'alert_title' => __( 'Payment canceled', 'woocommerce-mercadopago' ),
				'description' => __( 'The payment has been successfully canceled.', 'woocommerce-mercadopago' ),
			),
			'by_payer' => array(
				'alert_title' => __( 'Purchase canceled', 'woocommerce-mercadopago' ),
				'description' => __( 'The payment has been canceled by the customer.', 'woocommerce-mercadopago' ),
			),
			'pending' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Awaiting payment from the buyer.', 'woocommerce-mercadopago' ),
			),
			'pending_waiting_payment' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Awaiting payment from the buyer.', 'woocommerce-mercadopago' ),
			),
			'pending_waiting_for_remedy' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Awaiting payment from the buyer.', 'woocommerce-mercadopago' ),
			),
			'pending_waiting_transfer' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Awaiting payment from the buyer.', 'woocommerce-mercadopago' ),
			),
			'pending_review_manual' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'We are veryfing the payment. We will notify you by email in up to 6 hours if everything is fine so that you can deliver the product or provide the service.', 'woocommerce-mercadopago' ),
			),
			'waiting_bank_confirmation' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'pending_capture' => array(
				'alert_title' => __( 'Payment authorized. Awaiting capture.', 'woocommerce-mercadopago' ),
				'description' => __( "The payment has been authorized on the client's card. Please capture the payment.", 'woocommerce-mercadopago' ),
			),
			'in_process' => array(
				'alert_title' => __( 'Payment in process', 'woocommerce-mercadopago' ),
				'description' => __( 'Please wait or contact Mercado Pago for further details', 'woocommerce-mercadopago' ),
			),
			'pending_contingency' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The bank is reviewing the payment. As soon as we have their confirmation, we will notify you via email so that you can deliver the product or provide the service.', 'woocommerce-mercadopago' ),
			),
			'pending_card_validation' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Awaiting payment information validation.', 'woocommerce-mercadopago' ),
			),
			'pending_online_validation' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Awaiting payment information validation.', 'woocommerce-mercadopago' ),
			),
			'pending_additional_info' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Awaiting payment information validation.', 'woocommerce-mercadopago' ),
			),
			'offline_process' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Please wait or contact Mercado Pago for further details', 'woocommerce-mercadopago' ),
			),
			'pending_challenge' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Waiting for the buyer.', 'woocommerce-mercadopago' ),
			),
			'pending_provider_response' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Waiting for the card issuer.', 'woocommerce-mercadopago' ),
			),
			'bank_rejected' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The payment could not be processed. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'rejected_by_bank' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'rejected_insufficient_data' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'bank_error' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'by_admin' => array(
				'alert_title' => __( 'Mercado Pago did not process the payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Please contact Mercado Pago for further details.', 'woocommerce-mercadopago' ),
			),
			'expired' => array(
				'alert_title' => __( 'Expired payment deadline', 'woocommerce-mercadopago' ),
				'description' => __( 'The client did not pay within the time limit.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_bad_filled_card_number' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_bad_filled_security_code' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The CVV is invalid. Please ask your client to review the details or use another card.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_bad_filled_date' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card is expired. Please ask your client to use another card or to contact the bank.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_high_risk' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'This payment was declined because it did not pass Mercado Pago security controls. Please ask your client to use another card.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_fraud' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The buyer is suspended in our platform. Your client must contact us to check what happened.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_blacklist' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_insufficient_amount' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => $isCreditCard
					? __( 'The card does not have enough limit. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' )
					: __( 'The card does not have sufficient balance. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_other_reason' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_max_attempts' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The CVV was entered incorrectly several times. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_invalid_installments' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card does not allow the number of installments entered. Please ask your client to choose another installment plan or to use another card.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_call_for_authorize' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please instruct your client to ask the bank to authotize it or to use another card.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_duplicated_payment' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'From Mercado Pago we have detected that this payment has already been made before. If that is not the case, your client may try to pay again.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_card_disabled' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card is not active yet. Please ask your client to use another card or to get in touch with the bank to activate it.', 'woocommerce-mercadopago' ),
			),
			'payer_unavailable' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The buyer is suspended in our platform. Your client must contact us to check what happened.', 'woocommerce-mercadopago' ),
			),
			'rejected_high_risk' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'This payment was declined because it did not pass Mercado Pago security controls. Please ask your client to use another card.', 'woocommerce-mercadopago' ),
			),
			'rejected_by_regulations' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'This payment was declined because it did not pass Mercado Pago security controls. Please ask your client to use another card.', 'woocommerce-mercadopago' ),
			),
			'rejected_cap_exceeded' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The amount exceeded the card limit. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_3ds_challenge' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Please ask your client to use another card or to get in touch with the card issuer.', 'woocommerce-mercadopago' ),
			),
			'rejected_other_reason' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Please ask your client to use another card or to get in touch with the card issuer.', 'woocommerce-mercadopago' ),
			),
			'authorization_revoked' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'Please ask your client to use another card or to get in touch with the card issuer.', 'woocommerce-mercadopago' ),
			),
			'cc_amount_rate_limit_exceeded' => array(
				'alert_title' => __( 'Pending payment', 'woocommerce-mercadopago' ),
				'description' => __( "The amount exceeded the card's limit. Please ask your client to use another card or to get in touch with the bank.", 'woocommerce-mercadopago' ),
			),
			'cc_rejected_expired_operation' => array(
				'alert_title' => __( 'Expired payment deadline', 'woocommerce-mercadopago' ),
				'description' => __( 'The client did not pay within the time limit.', 'woocommerce-mercadopago' ),
			),
			'cc_rejected_bad_filled_other' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => $isCreditCard
					? __( 'The credit function is not enabled for the card. Please tell your client that it is possible to pay with debit or to use another one.', 'woocommerce-mercadopago' )
					: __( 'The debit function is not enabled for the card. Please tell your client that it is possible to pay with credit or to use another one.', 'woocommerce-mercadopago' ),
			),
			'rejected_call_for_authorize' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The card-issuing bank declined the payment. Please instruct your client to ask the bank to authorize it.', 'woocommerce-mercadopago' ),
			),
			'am_insufficient_amount' => array(
				'alert_title' => __( 'Declined payment', 'woocommerce-mercadopago' ),
				'description' => __( 'The buyer does not have enough balance to make the purchase. Please ask your client to deposit money to the Mercado Pago Account or to use a different payment method.', 'woocommerce-mercadopago' ),
			),
			'generic' => array(
				'alert_title' => __( 'There was an error', 'woocommerce-mercadopago' ),
				'description' => __( 'The transaction could not be completed.', 'woocommerce-mercadopago' ),
			),
		];

		return array_key_exists($paymentStatusDetail, $paymentStatusDetailMap)
			? $paymentStatusDetailMap[$paymentStatusDetail]
			: $paymentStatusDetailMap['generic'];
	}
}