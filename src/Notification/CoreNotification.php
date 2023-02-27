<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Helpers\Device;
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
	 * SDK Notification
	 */
	protected $sdkNotification;

    /**
     * CoreNotification constructor
     */
    public function __construct(Logs $logs)
    {
        parent::__construct($logs);
        $this->sdkNotification = $this->getSdkInstance()->getNotificationInstance();
    }

    /**
     * Get SDK instance
     */
    public function getSdkInstance(): Sdk
    {
        $accessToken  = $this->mercadopago->seller->getCredentialsAccessToken();
        $platformId   = MP_PLATFORM_ID;
        $productId    = Device::getDeviceProductId();
        $integratorId = $this->mercadopago->store->getIntegratorId();

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
			do_action( 'valid_mercadopago_ipn_request', $notificationEntity->toArray() );

			$this->set_response( 200, 'OK', 'Successfully Notification by Core' );
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, 'receive notification failed: ' . $e->getMessage() );
			$this->set_response(500, 'Internal Server Error', $e->getMessage());
		}
    }

}
