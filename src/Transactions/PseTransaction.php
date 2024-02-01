<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;

class PseTransaction extends AbstractPaymentTransaction
{
    /**
     * @const
     */
    public const ID = 'pse';

    /**
     * PSE Transaction constructor
     *
     * @param AbstractGateway $gateway
     * @param \WC_Order $order
     * @param array $checkout
     */
    public function __construct(AbstractGateway $gateway, \WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction->payment_method_id          = self::ID;
        $this->transaction->installments               = 1;
        $this->transaction->external_reference = $this->getExternalReference();
        $this->setPayerTransaction();
        $this->setPsePropertiesTransaction();
    }

    /**
     * Get internal metadata
     *
     * @return PaymentMetadata
     */
    public function getInternalMetadata(): PaymentMetadata
    {

        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata->checkout      = 'custom';
        $internalMetadata->checkout_type = self::ID;

        return $internalMetadata;
    }

    /**
     * Set pse properties transaction
     *
     * @return void
     */
    public function setPsePropertiesTransaction(): void
    {
            $this->transaction->callback_url = $this->order->get_checkout_order_received_url();
            $this->transaction->transaction_details->financial_institution = $this->checkout['bank'];
            $this->transaction->payer->entity_type                         = $this->checkout['person_type'];
            $phone = preg_replace('/[^0-9]/', '', $this->mercadopago->orderBilling->getPhone($this->order));
            $phoneAreaCode = substr($phone, 0, 2);
            $phoneNumber = substr($phone, 2);
            $this->transaction->payer->phone->area_code                         = $phoneAreaCode;
            $this->transaction->payer->phone->number                         = $phoneNumber;
            $fullAddress = $this->mercadopago->orderBilling->getFullAddress($this->order);
            $this->transaction->payer->address->street_number =
                $this->mercadopago->helpers->strings->getStreetNumberInFullAddress($fullAddress, "00");
    }

    /**
     * Set payer transaction
     *
     * @return void
     */
    public function setPayerTransaction(): void
    {
        parent::setPayerTransaction();
        $payer    = $this->transaction->payer;
        $payer->identification->type   = $this->checkout['doc_type'];
        $payer->identification->number = $this->checkout['doc_number'];
    }
}
