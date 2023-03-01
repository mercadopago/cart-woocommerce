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
    }

}