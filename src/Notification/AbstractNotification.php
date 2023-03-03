<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\OrderStatus;
use MercadoPago\Woocommerce\Interfaces\NotificationInterface;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractNotification implements NotificationInterface
{
    /**
     * @var string
     */
    public $gateway;

    /**
     * @var Logs
     */
    public $logs;

    /**
     * @var OrderStatus
     */
    public $orderStatus;

    /**
     * @var Seller
     */
    public $seller;

    /**
     * @var Store
     */
    public $store;

    /**
     * AbstractNotification constructor
     */
    public function __construct(string $gateway, Logs $logs, OrderStatus $orderStatus, Seller $seller, Store $store)
    {
        $this->gateway     = $gateway;
        $this->logs        = $logs;
        $this->orderStatus = $orderStatus;
        $this->seller      = $seller;
        $this->store       = $store;
    }
    
    public function handleReceivedNotification() {
        @ob_clean();
        $this->logs->file->info('received _get content: ' . wp_json_encode($_GET, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), __FUNCTION__);
    }
    
    /**
	 * Process successful request
	 *
	 * @param array $data preference or payment data.
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	public function handleSuccessfulRequest($data) {
		$this->logs->file->info('starting to process update...', __FUNCTION__);
		$order_key = $data['external_reference'];

		if ( empty( $order_key ) ) {
			$this->logs->file->error('External Reference not found', __FUNCTION__);
			$this->setResponse( 422, null, 'External Reference not found' );
		}

		$invoice_prefix = get_option( '_mp_store_identificator', 'WC-' );
		$id             = (int) str_replace( $invoice_prefix, '', $order_key );
		$order          = wc_get_order( $id );

		if (!$order) {
			$this->logs->file->error('Order is invalid', __FUNCTION__);
			$this->setResponse( 422, null, 'Order is invalid' );
		}

		if ($order->get_id() !== $id) {
			$this->logs->file->error('Order error', __FUNCTION__);
			$this->setResponse( 422, null, 'Order error' );
		}

		$this->logs->file->info('updating metadata and status with data: ' . wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), __FUNCTION__);

		return $order;
	}

	/**
	 * Process order status
	 *
	 * @param string $processedStatus Status.
	 * @param array  $data Payment data.
	 * @param object $order Order.
	 *
	 * @throws \Exception Invalid status response.
	 */
	public function processStatus(string $processedStatus, array $data, object $order ) {
		$this->orderStatus->processStatus($processedStatus, $data, $order, $this->gateway);
	}

	public function updateMeta( $order, $key, $value ) {
		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( $key, $value );
		} else {
			update_post_meta( $order->id, $key, $value );
		}
	}

	/**
	 * Set response
	 *
	 * @param int    $code         HTTP Code.
	 * @param string $code_message Message.
	 * @param string $body         Body.
	 */
	public function setResponse($code, $code_message, $body) {
		status_header($code, $code_message);
		die (wp_kses_post($body));
	}
}
