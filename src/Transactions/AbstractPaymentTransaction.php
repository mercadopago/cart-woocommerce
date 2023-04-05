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

        try {
            $data = $payment->save();
            $this->mercadopago->logs->file->info('Payment created', __CLASS__, $data);
            return $data;
        } catch (\Exception $e) {
            $this->mercadopago->logs->file->error('Payment creation failed: ' . $e->getMessage(), __CLASS__);
            return $e->getMessage();
        }
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
        $payer   = $this->transaction->additional_info->payer;
        $address = $payer->address;

        $payer->first_name    = $this->getObjectAttributeValue($this->order, 'get_billing_first_name', 'billing_first_name');
        $payer->last_name     = $this->getObjectAttributeValue($this->order, 'get_billing_last_name', 'billing_last_name');
        $payer->phone->number = $this->getObjectAttributeValue($this->order, 'get_billing_phone', 'billing_phone');
        $address->zip_code    = $this->getObjectAttributeValue($this->order, 'get_billing_postcode', 'billing_postcode');
        $address->street_name = "
            {$this->getObjectAttributeValue($this->order, 'get_id', 'billing_address_1', 'get_billing_address_1')}
            {$this->getObjectAttributeValue($this->order, 'get_id', 'billing_city', 'get_billing_city')}
            {$this->getObjectAttributeValue($this->order, 'get_id', 'billing_state', 'get_billing_state')}
            {$this->getObjectAttributeValue($this->order, 'get_id', 'billing_country', 'get_billing_country')}
        ";
    }

    /**
     * Set payer transaction
     *
     * @return void
     */
    public function setPayerTransaction(): void
    {
        $payer = $this->transaction->payer;

        $payer->address->street_number = '';
        $payer->address->neighborhood  = '';
        $payer->email                  = $this->getObjectAttributeValue($this->order, 'get_billing_email', 'billing_email');
        $payer->first_name             = $this->getObjectAttributeValue($this->order, 'get_billing_first_name', 'billing_first_name');
        $payer->last_name              = $this->getObjectAttributeValue($this->order, 'get_billing_last_name', 'billing_last_name');
        $payer->address->street_name   = $this->getObjectAttributeValue($this->order, 'get_billing_address_1', 'billing_address_1');
        $payer->address->city          = $this->getObjectAttributeValue($this->order, 'get_billing_city', 'billing_city');
        $payer->address->federal_unit  = $this->getObjectAttributeValue($this->order, 'get_billing_state', 'billing_state');
        $payer->address->zip_code      = $this->getObjectAttributeValue($this->order, 'get_billing_postcode', 'billing_postcode');
    }
}
