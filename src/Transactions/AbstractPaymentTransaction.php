<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Numbers;

abstract class AbstractPaymentTransaction extends AbstractTransaction
{
    /**
     * Payment Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, \WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction = $this->sdk->getPaymentInstance();

        $this->setCommonTransaction();
        $this->setAdditionalInfoTransaction();
        $this->setPayerTransaction();

        $this->transaction->description        = implode(', ', $this->listOfItems);
        $this->transaction->transaction_amount = Numbers::format($this->orderTotal);
    }

    /**
     * Create Payment
     *
     * @return string|array
     */
    public function createPayment()
    {
        $payment = $this->getTransaction('Payment');
        $data = $payment->save();
        $this->mercadopago->logs->file->info('Payment created', $this->gateway::LOG_SOURCE, $data);
        return $data;
    }

    /**
     * Set additional info
     *
     * @return void
     */
    public function setAdditionalInfoTransaction(): void
    {
        $this->setAdditionalInfoItemsTransaction();
        $this->setAdditionalInfoShipmentsTransaction();
        $this->setAdditionalInfoPayerTransaction();
    }

    /**
     * Set additional items information
     *
     * @return void
     */
    public function setAdditionalInfoItemsTransaction(): void
    {
        $this->setItemsTransaction($this->transaction->additional_info->items);
    }

    /**
     * Set additional shipments information
     *
     * @return void
     */
    public function setAdditionalInfoShipmentsTransaction(): void
    {
        $this->setShipmentsTransaction($this->transaction->additional_info->shipments);
    }

    /**
     * Set additional payer information
     *
     * @return void
     */
    public function setAdditionalInfoPayerTransaction(): void
    {
        $payer = $this->transaction->additional_info->payer;

        $payer->first_name           = $this->mercadopago->orderBilling->getFirstName($this->order);
        $payer->last_name            = $this->mercadopago->orderBilling->getLastName($this->order);
        $payer->phone->number        = $this->mercadopago->orderBilling->getPhone($this->order);
        $payer->address->zip_code    = $this->mercadopago->orderBilling->getZipcode($this->order);
        $payer->address->street_name = $this->mercadopago->orderBilling->getFullAddress($this->order);
    }

    /**
     * Set payer transaction
     *
     * @return void
     */
    public function setPayerTransaction(): void
    {
        $payer = $this->transaction->payer;

        $payer->email                  = $this->mercadopago->orderBilling->getEmail($this->order);
        $payer->first_name             = $this->mercadopago->orderBilling->getFirstName($this->order);
        $payer->last_name              = $this->mercadopago->orderBilling->getLastName($this->order);
        $payer->address->city          = $this->mercadopago->orderBilling->getCity($this->order);
        $payer->address->federal_unit  = $this->mercadopago->orderBilling->getState($this->order);
        $payer->address->zip_code      = $this->mercadopago->orderBilling->getZipcode($this->order);
        $payer->address->street_name   = $this->mercadopago->orderBilling->getFullAddress($this->order);
        $payer->address->street_number = '';
        $payer->address->neighborhood  = '';
    }
}
