<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\PP\Sdk\Entity\Notification\Notification;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\OrderStatus;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class CoreNotification extends AbstractNotification
{
    /**
     * @var WoocommerceMercadoPago
     */
    protected $mercadopago;

	/**
	 * @var Notification
	 */
	protected $sdkNotification;

    /**
     * CoreNotification constructor
     */
    public function __construct(string $gateway, Logs $logs, OrderStatus $orderStatus, Seller $seller, Store $store)
    {
        parent::__construct($gateway, $logs, $orderStatus, $seller, $store);
        $this->sdkNotification = $this->getSdkInstance()->getNotificationInstance();
    }

    /**
     * Get SDK instance
     */
    public function getSdkInstance(): Sdk
    {
        $accessToken  = $this->seller->getCredentialsAccessToken();
        $platformId   = MP_PLATFORM_ID;
        $productId    = Device::getDeviceProductId();
        $integratorId = $this->store->getIntegratorId();

        return new Sdk($accessToken, $platformId, $productId, $integratorId);
    }

    public function handleReceivedNotification() {
        parent::handleReceivedNotification();
        $notification_id = json_decode(file_get_contents('php://input'));
        
		try {
			$notificationEntity = $this->sdkNotification->read(array('id' => $notification_id));

			/**
			 * Do action valid_mercadopago_ipn_request.
			 *
			 * @since 3.0.1
			 */
			$this->handleSuccessfulRequest($notificationEntity->toArray());
		} catch ( \Exception $e ) {
			$this->logs->file->error('receive notification failed: ' . $e->getMessage(), __FUNCTION__);
			$this->setResponse(500, 'Internal Server Error', $e->getMessage());
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
            $this->processStatus($processedStatus, $data, $order);
		} catch (\Exception $e) {
			$this->setResponse(422, null, $e->getMessage());
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
	public function getProcessedStatus($data, $order)
	{
		$status = $data['status'];

		// Updates the type of gateway.
		$this->updateMeta($order, '_used_gateway', get_class( $this));

		if (!empty( $data['payer']['email'])) {
			$this->updateMeta($order, __('Buyer email', 'woocommerce-mercadopago'), $data['payer']['email']);
		}

		if (!empty( $data['payments_details'])) {
			$payment_ids = array();

			foreach ($data['payments_details'] as $payment) {
				$payment_ids[] = $payment['id'];

				$this->updateMeta(
					$order,
					'Mercado Pago - Payment ' . $payment['id'],
					'[Date ' . gmdate( 'Y-m-d H:i:s' ) .
						']/[Amount ' . $payment['total_amount'] .
						']/[Payment Type ' . $payment['payment_type_id'] .
						']/[Payment Method ' . $payment['payment_method_id'] .
						']/[Paid ' . $payment['paid_amount'] .
						']/[Coupon ' . $payment['coupon_amount'] .
						']/[Refund ' . $data['total_refunded'] . ']'
				);
			}

			if (count( $payment_ids ) > 0) {
				$this->updateMeta($order, '_Mercado_Pago_Payment_IDs', implode(', ', $payment_ids));
			}
		}

		$order->save();

		return $status;
	}

}
