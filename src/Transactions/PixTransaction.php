<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Date;

class PixTransaction extends AbstractPaymentTransaction
{
    /**
     * @const
     */
    public const ID = 'pix';

    /**
     * Pix Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order, $checkout)
    {
        parent::constructor($gateway, $order, $checkout);

        $this->transaction->payment_method_id          = self::ID;
        $this->transaction->date_of_expiration         = Date::format($this->getExpirationDate());
        $this->transaction->point_of_interaction->type = 'CHECKOUT';
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata['checkout']      = 'custom';
        $internalMetadata['checkout_type'] = self::ID;
        $internalMetadata['pix_settings']  = $this->mercadopago->analytics->getGatewaySettings($this->gateway::ID);

        return $internalMetadata;
    }

    /**
     * Get expiration date
     */
    public function getExpirationDate(): string
    {
        $expirationDate = $this->mercadopago->seller->getCheckoutDateExpirationPix($this->gateway, '');

        if (1 === strlen($expirationDate) && '1' === $expirationDate) {
            $newDateExpiration = '24 hours';
            $this->gateway->update_option('checkout_pix_date_expiration', $newDateExpiration);
            return $newDateExpiration;
        } elseif (1 === strlen($expirationDate)) {
            $newDateExpiration = $expirationDate . ' days';
            $this->gateway->update_option('checkout_pix_date_expiration', $newDateExpiration);
            return $newDateExpiration;
        }

        return $expirationDate;
    }
}
