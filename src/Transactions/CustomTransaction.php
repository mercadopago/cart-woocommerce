<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

class CustomTransaction extends AbstractPaymentTransaction
{
    /**
     * @const
     */
    public const ID = 'credit_card';

    /**
     * Custom Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order, $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction->payment_method_id  = $this->checkout['paymentMethodId'];
        $this->transaction->installments       = (int) $this->checkout['installments'];

        $this->setAdditionalInfoTransaction();
        $this->setTokenTransaction();
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata['checkout']         = 'custom';
        $internalMetadata['checkout_type']    = self::ID;
        $internalMetadata['custom_settings']  = $this->mercadopago->metadataSettings->getGatewaySettings($this->gateway::ID);

        return $internalMetadata;
    }

    /**
     * Set token transaction
     */
    public function setTokenTransaction()
    {
        if (array_key_exists('token', $this->checkout)) {
            $this->transaction->token             = $this->checkout['token'];
            $this->transaction->metadata['token'] = $this->checkout['token'];
            if ($this->checkout['CustomerId']) {
                $this->transaction->payer->id = $this->checkout['CustomerId'];
            }
            if ($this->checkout['issuer']) {
                $this->transaction->issuer_id = $this->checkout['issuer'];
            }
        }
    }
}
