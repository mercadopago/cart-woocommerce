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
     * IpnNotification constructor
     */
    public function __construct(string $gateway, Logs $logs, OrderStatus $orderStatus, Requester $requester, Seller $seller, Store $store)
    {
        parent::__construct($gateway, $logs, $orderStatus, $seller, $store);
        $this->requester = $requester;
    }

}