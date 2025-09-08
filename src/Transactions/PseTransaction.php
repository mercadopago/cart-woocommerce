<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use WC_Order;

class PseTransaction extends AbstractPaymentTransaction
{
    public const ID = 'pse';

    public function __construct(AbstractGateway $gateway, WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction->payment_method_id = self::ID;
        $this->transaction->installments      = 1;
        $this->setPayerTransaction();
        $this->setPsePropertiesTransaction();
    }

    public function extendInternalMetadata(PaymentMetadata $internalMetadata): void
    {
        $internalMetadata->checkout      = 'custom';
        $internalMetadata->checkout_type = self::ID;
    }

    public function setPsePropertiesTransaction(): void
    {
        $phone = preg_replace('/[^0-9]/', '', $this->mercadopago->orderBilling->getPhone($this->order));
        $this->transaction->callback_url                               = $this->order->get_checkout_order_received_url();
        $this->transaction->transaction_details->financial_institution = $this->checkout['bank'];
        $this->transaction->payer->entity_type                         = $this->checkout['person_type'];
        $this->transaction->payer->phone->area_code                    = substr($phone, 0, 2);
        $this->transaction->payer->phone->number                       = substr($phone, 2);
        $this->transaction->payer->address->street_number              = $this->mercadopago->helpers->strings->getStreetNumberInFullAddress(
            $this->mercadopago->orderBilling->getFullAddress($this->order),
            "00"
        );
    }

    public function setPayerTransaction(): void
    {
        parent::setPayerTransaction();
        $payer                         = $this->transaction->payer;
        $payer->identification->type   = $this->checkout['doc_type'];
        $payer->identification->number = $this->checkout['doc_number'];
    }
}
