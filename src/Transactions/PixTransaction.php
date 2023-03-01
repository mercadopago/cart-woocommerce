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
        parent::__construct($gateway, $order, $checkout);

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
            $expirationDate = '24 hours';
            $this->mercadopago->options->setMercadoPago($this->gateway, 'checkout_pix_date_expiration', $expirationDate);
        } elseif (1 === strlen($expirationDate)) {
            $expirationDate = $expirationDate . ' days';
            $this->mercadopago->options->setMercadoPago($this->gateway, 'checkout_pix_date_expiration', $expirationDate);
        }

        return gmdate('Y-m-d\TH:i:s.000O', strtotime('+' . $expirationDate));
    }
}
