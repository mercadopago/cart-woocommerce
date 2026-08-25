<?php

namespace MercadoPago\Woocommerce\SuperToken\Adapters;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\SuperToken\Contracts\SuperTokenTransactionFactory;
use MercadoPago\Woocommerce\Transactions\AbstractPaymentTransaction;
use MercadoPago\Woocommerce\Transactions\SupertokenTransaction;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class DefaultSuperTokenTransactionFactory implements SuperTokenTransactionFactory
{
    /**
     * @return AbstractPaymentTransaction
     */
    public function create(AbstractGateway $gateway, WC_Order $order, array $checkout)
    {
        return new SupertokenTransaction($gateway, $order, $checkout);
    }
}
