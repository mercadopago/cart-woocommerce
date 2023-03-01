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

class IpnNotification extends AbstractNotification
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
     * IpnNotification constructor
     */
    public function __construct(string $gateway, Logs $logs, OrderStatus $orderStatus, Seller $seller, Store $store, Requester $requester, array $data)
    {
        parent::__construct($gateway, $logs, $orderStatus, $seller, $store);
        $this->requester = $requester;
        $this->data      = $data;
    }

    public function handleReceivedNotification() {
        parent::handleReceivedNotification();

        if (isset($this->data['data_id']) && isset($this->data['type'])) {
            status_header( 200, 'OK' );
        }

        if (!isset( $this->data['id']) || ! isset($this->data['topic'])) {
            $this->logs->file->error('No ID or TOPIC param in Request IPN.', __FUNCTION__);
            $this->setResponse( 422, null, 'No ID or TOPIC param in Request IPN');
        }

        if ('payment' === $this->data['topic'] || 'merchant_order' !== $this->data['topic']) {
            $this->setResponse( 200, null, 'Discarded notification. This notification is already processed as webhook-payment.');
        }

        if ('merchant_order' === $this->data['topic']) {
            $merchantOrderId = preg_replace('/[^\d]/', '', $this->data['id']);
            $headers = ['Authorization: Bearer ' .  $this->seller->getCredentialsAccessToken()];

            $ipnInfo = $this->requester->get('/merchant_orders/' . $merchantOrderId, $headers);

            if (200 !== $ipnInfo->getStatus() && 201 !== $ipnInfo->getStatus()) {
                $this->logs->file->error('IPN merchant_order not found ' . wp_json_encode($ipnInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), __FUNCTION__);
                $this->setResponse( 422, null, 'IPN merchant_order not found');
            }

            $payments = $ipnInfo->getData()['payments'];
            if (count($payments) < 1) {
                $this->logs->file->error('Not found Payments into Merchant_Order', __FUNCTION__);
                $this->setResponse( 422, null, 'Not found Payments into Merchant_Order');
            }

            $ipnInfo->getData()['ipn_type'] = 'merchant_order';

            $this->handleSuccessfulRequest($ipnInfo->getData());
            
            $this->setResponse( 200, 'OK', 'Notification IPN Successfull' );
        }
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
			$order           = parent::handleSuccessfulRequest($data);
			$processedStatus = $this->getProcessedStatus($data, $order);
			$this->logs->file->info('Changing order status to: ' . $this->orderStatus->mapMpStatusToWoocommerceStatus(str_replace('_', '', $processedStatus)), __FUNCTION__);
			$this->processStatus( $processedStatus, $data, $order );
		} catch ( \Exception $e ) {
			$this->setResponse( 422, null, $e->getMessage() );
			$this->logs->file->error($e->getMessage(), __FUNCTION__);
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
		$status   = 'pending';
		$payments = $data['payments'];

		if (is_array($payments)) {
			$total       = $data['shipping_cost'] + $data['total_amount'];
			$totalPaid   = 0.00;
			$totalRefund = 0.00;
			
            foreach ($data['payments'] as $payment) {
				$couponMp = $this->getPaymentInfo($payment['id']);

				if ( $couponMp > 0 ) {
					$totalPaid += (float) $couponMp;
				}

				if ('approved' === $payment['status']) {
					$totalPaid += (float) $payment['total_paid_amount'];
				} elseif ('refunded' === $payment['status']) {
					$totalRefund += (float) $payment['amount_refunded'];
				}
			}

			if ( $totalPaid >= $total ) {
				$status = 'approved';
			} elseif ( $totalRefund >= $total ) {
				$status = 'refunded';
			} else {
				$status = 'pending';
			}
		}

        $this->updateMeta($order, '_used_gateway', 'WC_WooMercadoPago_Basic_Gateway');
        if (!empty($data['payer']['email'])) {
            $this->updateMeta($order, 'Buyer email', $data['payer']['email']);
        }
        if (!empty($data['payment_type_id'])) {
            $this->updateMeta($order, 'Payment type', $data['payment_type_id']);
        }
        if (!empty($data['payment_method_id'])) {
            $this->updateMeta($order, 'Payment method', $data['payment_method_id']);
        }
        if (!empty($data['payments'])) {
            $paymentIds = [];
            foreach ($data['payments'] as $payment) {
                $couponMp     = $this->getPaymentInfo($payment['id']);
                $paymentIds[] = $payment['id'];
                $this->updateMeta(
                    $order,
                    'Mercado Pago - Payment ' . $payment['id'],
                    '[Date ' . gmdate('Y-m-d H:i:s', strtotime($payment['date_created'])) .
                        ']/[Amount ' . $payment['transaction_amount'] .
                        ']/[Paid '   . $payment['total_paid_amount'] .
                        ']/[Coupon ' . $couponMp .
                        ']/[Refund ' . $payment['amount_refunded'] . ']'
                );
            }
            if ( count($paymentIds) > 0 ) {
                $this->updateMeta($order, '_Mercado_Pago_Payment_IDs', implode(', ', $paymentIds));
            }
        }
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