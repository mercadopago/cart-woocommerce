<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;

class CustomTransaction extends AbstractPaymentTransaction
{
    /**
     * @const
     */
    public const ID = 'credit_card';

    /**
     * Custom Transaction constructor
     *
     * @param AbstractGateway $gateway
     * @param \WC_Order $order
     * @param array $checkout
     */
    public function __construct(AbstractGateway $gateway, \WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction->payment_method_id   = $this->checkout['payment_method_id'];
        $this->transaction->installments        = (int) $this->checkout['installments'];
        $this->transaction->three_d_secure_mode = 'optional';

        $this->setTokenTransaction();
    }

    /**
     * Get internal metadata
     *
     * @return PaymentMetadata
     */
    public function getInternalMetadata(): PaymentMetadata
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata->checkout        = 'custom';
        $internalMetadata->checkout_type   = self::ID;

        return $internalMetadata;
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

            if (isset($this->checkout['customer_id'])) {
                $this->transaction->payer->id = $this->checkout['customer_id'];
            }

            if (isset($this->checkout['issuer'])) {
                $this->transaction->issuer_id = $this->checkout['issuer'];
            }
        }
    }
}
