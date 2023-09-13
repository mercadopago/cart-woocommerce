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
        $this->setAdditionalInfoBaseInfoTransaction();
        $this->setAdditionalInfoItemsTransaction();
        $this->setAdditionalInfoShipmentsTransaction();
        $this->setAdditionalInfoPayerTransaction();
        $this->setAdditionalInfoSellerTransaction();
    }

    /**
     * Set base information
     *
     * @return void
     */
    public function setAdditionalInfoBaseInfoTransaction(): void
    {
        $this->transaction->additional_info->ip_address = $this->mercadopago->url->getServerAddress();
        $this->transaction->additional_info->referral_url = $this->mercadopago->url->getBaseUrl();
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
     * Set additional seller information
     *
     * @return void
     */
    public function setAdditionalInfoSellerTransaction(): void
    {
        $seller = $this->transaction->additional_info->seller;

        $seller->store_id      = $this->mercadopago->store->getStoreId();
        $seller->business_type = $this->mercadopago->store->getStoreCategory('others');
        $seller->collector     = $this->mercadopago->seller->getClientId();
        $seller->website       = $this->mercadopago->url->getBaseUrl();
        $seller->platform_url  = $this->mercadopago->url->getBaseUrl();
        $seller->referral_url  = $this->mercadopago->url->getBaseUrl();

        //TODO verify address, phone and registration fields based on task PPWP-1929
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
        $payer->user_email           = $this->mercadopago->orderBilling->getEmail($this->order);
        $payer->phone->number        = $this->mercadopago->orderBilling->getPhone($this->order);
        $payer->mobile->number       = $this->mercadopago->orderBilling->getPhone($this->order);
        $payer->address->city        = $this->mercadopago->orderBilling->getCity($this->order);
        $payer->address->state       = $this->mercadopago->orderBilling->getState($this->order);
        $payer->address->country     = $this->mercadopago->orderBilling->getCountry($this->order);
        $payer->address->zip_code    = $this->mercadopago->orderBilling->getZipcode($this->order);
        $payer->address->street_name = $this->mercadopago->orderBilling->getAddress1($this->order);
        $payer->address->apartment   = $this->mercadopago->orderBilling->getAddress2($this->order);

        if ($this->mercadopago->currentUser->isUserLoggedIn()) {
            $payer->registered_user        = true;
            $payer->identification->number = $this->mercadopago->currentUser->getCurrentUserMeta('billing_document', true);
            $payer->registration_date      = $this->mercadopago->currentUser->getCurrentUserData()->user_registered;
            $payer->platform_email         = $this->mercadopago->currentUser->getCurrentUserData()->user_email;
            $payer->register_updated_at    = $this->mercadopago->currentUser->getCurrentUserData()->user_modified;

            //TODO verify this field based on task PPWP-1929
            //$payer->last_purchase          = $this->mercadopago->currentUser->getCurrentUserLastPurchase();
        }

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
