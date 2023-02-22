<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

class WalletButtonTransaction extends AbstractPreferenceTransaction
{
    /**
     * @const
     */
    public const ID = 'wallet_button';

    /**
     * Wallet Button Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order)
    {
        parent::constructor($gateway, $order);

        $this->transaction->purpose = 'wallet_purchase';
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata['checkout']               = 'pro';
        $internalMetadata['checkout_type']          = self::ID;
        $internalMetadata['wallet_button_settings'] =
            $this->mercadopago->analytics->getGatewaySettings($this->gateway::ID);

        return $internalMetadata;
    }
}
