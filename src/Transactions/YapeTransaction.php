<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use WC_Order;

class YapeTransaction extends AbstractPaymentTransaction
{
    public const ID = 'yape';

    /**
     * Yape transaction constructor
     *
     * @param AbstractGateway $gateway
     * @param WC_Order $order
     * @param array $checkout
     *
     * @throws Exception
     */
    public function __construct(AbstractGateway $gateway, WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction->payment_method_id   = self::ID;
        $this->transaction->installments        = 1;

        $this->setTokenTransaction();
    }

    public function extendInternalMetadata(PaymentMetadata $internalMetadata): void
    {
        $internalMetadata->checkout      = 'custom';
        $internalMetadata->checkout_type = self::ID;
    }

    /**
     * Set token transaction
     *
     * @return void
     */
    public function setTokenTransaction(): void
    {
        if (array_key_exists('token', $this->checkout)) {
            $this->transaction->token = $this->checkout['token'];
        }
    }
}
