<?php

namespace MercadoPago\Woocommerce\SuperToken\Contracts;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Transactions\AbstractPaymentTransaction;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

interface SuperTokenTransactionFactory
{
    /**
     * @return AbstractPaymentTransaction
     */
    public function create(AbstractGateway $gateway, WC_Order $order, array $checkout);
}
