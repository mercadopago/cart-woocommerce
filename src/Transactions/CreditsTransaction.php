<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;

class CreditsTransaction extends AbstractPreferenceTransaction
{
    /**
     * @const
     */
    public const ID = 'credits';

    /**
     * Credits Transaction constructor
     *
     * @param AbstractGateway $gateway
     * @param \WC_Order $order
     */
    public function __construct(AbstractGateway $gateway, \WC_Order $order)
    {
        parent::__construct($gateway, $order);

        $this->transaction->purpose = 'onboarding_credits';
    }

    /**
     * Get internal metadata
     *
     * @return PaymentMetadata
     */
    public function getInternalMetadata(): PaymentMetadata
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata->checkout        = 'pro';
        $internalMetadata->checkout_type   = self::ID;

        return $internalMetadata;
    }
}
