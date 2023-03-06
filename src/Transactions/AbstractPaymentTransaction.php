<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Numbers;

abstract class AbstractPaymentTransaction extends AbstractTransaction
{
    /**
     * Payment Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order, $checkout)
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

            $this->mercadopago->logs->file->info(
                'Payment created: ' . wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                __FUNCTION__
            );

            return $data;
        } catch (\Exception $e) {
            $this->mercadopago->logs->file->error(
                'payment creation failed with error: ' . $e->getMessage(),
                __FUNCTION__
            );

            return $e->getMessage();
        }
    }

    /**
     * Set additional info
     */
    public function setAdditionalInfoTransaction()
    {
        $this->setAdditionalInfoItemsTransaction();
        $this->setAdditionalInfoShipmentsTransaction();
        $this->setAdditionalInfoPayerTransaction();
    }

    /**
     * Set additional items information
     */
    public function setAdditionalInfoItemsTransaction()
    {
        $this->setItemsTransaction($this->transaction->additional_info->items);
    }

    /**
     * Set additional shipments information
     */
    public function setAdditionalInfoShipmentsTransaction()
    {
        $this->setShipmentsTransaction($this->transaction->additional_info->shipments);
    }

    /**
     * Set additional payer information
     */
    public function setAdditionalInfoPayerTransaction()
    {
        $payer   = $this->transaction->additional_info->payer;
        $address = $payer->address;

        $payer->first_name    = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_first_name', 'get_billing_first_name');
        $payer->last_name     = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_last_name', 'get_billing_last_name');
        $payer->phone->number = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_phone', 'get_billing_phone');
        $address->street_name =
            "{$this->getObjectAttributeValue($this->order, 'get_id', 'billing_address_1', 'get_billing_address_1')} / " .
            "{$this->getObjectAttributeValue($this->order, 'get_id', 'billing_city', 'get_billing_city')} " .
            "{$this->getObjectAttributeValue($this->order, 'get_id', 'billing_state', 'get_billing_state')} " .
            "{$this->getObjectAttributeValue($this->order, 'get_id', 'billing_country', 'get_billing_country')}";
        $address->zip_code    = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_postcode', 'get_billing_postcode');
    }

    /**
     * Set payer transaction
     */
    public function setPayerTransaction()
    {
        $payer = $this->transaction->payer;

        $payer->email                  = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_email', 'get_billing_email');
        $payer->first_name             = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_first_name', 'get_billing_first_name');
        $payer->last_name              = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_last_name', 'get_billing_last_name');
        $payer->address->street_name   = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_address_1', 'get_billing_address_1');
        $payer->address->street_number = '';
        $payer->address->neighborhood  = '';
        $payer->address->city          = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_city', 'get_billing_city');
        $payer->address->federal_unit  = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_state', 'get_billing_state');
        $payer->address->zip_code      = $this->getObjectAttributeValue($this->order, 'get_id', 'billing_postcode', 'get_billing_postcode');
    }
}
