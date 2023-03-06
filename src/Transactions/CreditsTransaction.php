<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

class CreditsTransaction extends AbstractPreferenceTransaction
{
    /**
     * @const
     */
    public const ID = 'credits';

    /**
     * Credits Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order)
    {
        parent::__construct($gateway, $order);

        $this->transaction->purpose = 'onboarding_credits';
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata['checkout']         = 'pro';
        $internalMetadata['checkout_type']    = self::ID;
        $internalMetadata['credits_settings'] = $this->mercadopago->metadataSettings->getGatewaySettings($this->gateway::ID);

        return $internalMetadata;
    }
}
