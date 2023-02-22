<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

class BasicTransaction extends AbstractPreferenceTransaction
{
    /**
     * @const
     */
    public const ID = 'basic';

    /**
     * Basic Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order)
    {
        parent::constructor($gateway, $order);
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata['checkout']       = 'smart';
        $internalMetadata['checkout_type']  =
            $this->mercadopago->options->getMercadoPago($this->gateway, 'method', 'redirect');
        $internalMetadata['basic_settings'] = $this->mercadopago->analytics->getGatewaySettings($this->gateway::ID);

        return $internalMetadata;
    }
}
