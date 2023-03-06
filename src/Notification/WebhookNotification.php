<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\OrderStatus;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookNotification extends AbstractNotification
{

    /**
     * @var Requester`
     */
    public $requester;

    /**
     * @var array`
     */
    public $data;

    /**
     * WebhookNotification constructor
     */
    public function __construct(string $gateway, Logs $logs, OrderStatus $orderStatus, Seller $seller, Store $store, Requester $requester, array $data)
    {
        parent::__construct($gateway, $logs, $orderStatus, $seller, $store);
        $this->requester = $requester;
        $this->data      = $data;
    }

    public function handleReceivedNotification() {
        parent::handleReceivedNotification();

		if (!isset($this->data['data_id']) || !isset($this->data['type'])) {
			$this->logs->file->error(
				'data_id or type not set: ' .
				wp_json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                __FUNCTION__,
			);
			if (!isset($this->data['id']) || !isset($this->data['topic'])) {
				$this->logs->file->error(
					'Mercado Pago Request failure: ' .
					wp_json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    __FUNCTION__
				);
				$this->setResponse( 422, null, 'Mercado Pago Request failure' );
			}
		} else {
			if ( 'payment' === $this->data['type'] ) {
				$payment_id   = preg_replace( '/[^\d]/', '', $this->data['data_id'] );
                $headers = ['Authorization: Bearer ' .  $this->seller->getCredentialsAccessToken()];
    
                $paymentInfo = $this->requester->get('/v1/payments/' . $payment_id, $headers);

				if (( 200 === $paymentInfo->getStatus() || 201 === $paymentInfo->getStatus())) {
                    $this->handleSuccessfulRequest($paymentInfo->getData());
                    $this->setResponse( 200, 'OK', 'Webhook Notification Successfull' );
				} else {
					$this->logs->file->error('error when processing received data: ' . wp_json_encode( $paymentInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), __FUNCTION__);
				}
			}
		}
		$this->setResponse( 422, null, 'Mercado Pago Invalid Requisition' );
    }

	/**
	 * Process success response
	 *
	 * @param array $data Payment data.
	 *
	 * @return void
	 */
	public function handleSuccessfulRequest($data)
	{
		try {
			$order  = parent::handleSuccessfulRequest( $data );
			$status = $this->getProcessedStatus( $data, $order );
			$this->logs->file->info(
				__FUNCTION__,
				'Changing order status to: ' .
				$this->orderStatus->mapMpStatusToWoocommerceStatus( str_replace( '_', '', $status))
			);
			$this->processStatus( $status, $data, $order );
		} catch ( \Exception $e ) {
			$this->logs->file->error( __FUNCTION__, $e->getMessage() );
		}
	}

	/**
	 * Process status
	 *
	 * @param array  $data Payment data.
	 * @param object $order Order.
	 * @return string
	 */
	public function getProcessedStatus($data, $order) {
		$status        = isset( $data['status'] ) ? $data['status'] : 'pending';
		$total_paid    = isset( $data['transaction_details']['total_paid_amount'] ) ? $data['transaction_details']['total_paid_amount'] : 0.00;
		$total_refund  = isset( $data['transaction_amount_refunded'] ) ? $data['transaction_amount_refunded'] : 0.00;
		$coupon_amount = isset( $data['coupon_amount'] ) ? $data['coupon_amount'] : 0.00;

		$this->updateMeta( $order, '_used_gateway', get_class( $this ) );
        if ( ! empty( $data['payer']['email'] ) ) {
            $this->updateMeta($order, 'Buyer email', $data['payer']['email']);
        }
        if ( ! empty( $data['payment_type_id'] ) ) {
            $this->updateMeta($order, 'Payment type', $data['payment_type_id']);
        }
        if ( ! empty( $data['payment_method_id'] ) ) {
            $this->updateMeta($order, 'Payment method', $data['payment_method_id']);
        }
        $this->updateMeta(
            $order,
            'Mercado Pago - Payment ' . $data['id'],
            '[Date ' . gmdate( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
                ']/[Amount ' . $data['transaction_amount'] .
                ']/[Paid ' . $total_paid .
                ']/[Coupon ' . $coupon_amount .
                ']/[Refund ' . $total_refund . ']'
        );
        $this->updateMeta($order, '_Mercado_Pago_Payment_IDs', $data['id']);
        $order->save();

        return $status;
	}

	public function getPaymentInfo( $id ) {
		$accessToken = $this->seller->getCredentialsAccessToken();
        $paymentInfo  = $this->requester->get('/v1/payments/' . $id, array( 'Authorization' => 'Bearer ' . $accessToken));
		$couponAmount = (float) $paymentInfo->getData()['coupon_amount'];

		return $couponAmount;
	}

}