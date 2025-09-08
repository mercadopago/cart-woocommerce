<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use WC_Order;

class PixTransaction extends AbstractPaymentTransaction
{
    public const ID = 'pix';

    /**
     * @var PixGateway
     */
    public AbstractGateway $gateway;

    public function __construct(AbstractGateway $gateway, WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction->payment_method_id          = self::ID;
        $this->transaction->installments               = 1;
        $this->transaction->date_of_expiration         = $this->getExpirationDate();
        $this->transaction->point_of_interaction->type = 'CHECKOUT';
    }

    public function extendInternalMetadata(PaymentMetadata $internalMetadata): void
    {
        $internalMetadata->checkout      = 'custom';
        $internalMetadata->checkout_type = self::ID;
    }

    public function getExpirationDate(): string
    {
        $expirationDate = $this->gateway->getCheckoutExpirationDate();

        if (strlen($expirationDate) === 1) {
            $expirationDate .= ' days';
        }

        return Date::sumToNowDate($expirationDate);
    }
}
