<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

abstract class AbstractPreferenceTransaction extends AbstractTransaction
{
    /**
     * Preference Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, \WC_Order $order)
    {
        parent::__construct($gateway, $order);

        $this->transaction = $this->sdk->getPreferenceInstance();

        $this->setCommonTransaction();
        $this->setPayerTransaction();
        $this->setBackUrlsTransaction();
        $this->setAutoReturnTransaction();
        $this->setShipmentsTransaction($this->transaction->shipments);
        $this->setItemsTransaction($this->transaction->items);
        $this->setShippingTransaction();
        $this->setFeeTransaction();
    }

    /**
     * Create preference
     *
     * @return string|bool
     */
    public function createPreference(): string
    {
        $preference = $this->getTransaction('Preference');

        try {
            $data = $preference->save();
            $this->mercadopago->logs->file->info('Preference created', $this->gateway::LOG_SOURCE, $data);
            return $this->mercadopago->store->isTestMode() ? $data['sandbox_init_point'] : $data['init_point'];
        } catch (\Exception $e) {
            $this->mercadopago->logs->file->error('Preference creation failed: ' . $e->getMessage(), __CLASS__);
            return false;
        }
    }

    /**
     * Set common transaction
     *
     * @return void
     */
    public function setCommonTransaction(): void
    {
        parent::setCommonTransaction();

        $isTestMode = $this->mercadopago->store->isTestMode();
        $isTestUser = $this->mercadopago->seller->isTestUser();

        if (!$isTestMode && !$isTestUser) {
            $this->transaction->sponsor_id = $this->countryConfigs['sponsor_id'];
        }
    }

    /**
     * Set payer
     *
     * @return void
     */
    public function setPayerTransaction(): void
    {
        $payer = $this->transaction->payer;

        $payer->email                = $this->mercadopago->orderBilling->getEmail($this->order);
        $payer->name                 = $this->mercadopago->orderBilling->getFirstName($this->order);
        $payer->surname              = $this->mercadopago->orderBilling->getLastName($this->order);
        $payer->phone->number        = $this->mercadopago->orderBilling->getPhone($this->order);
        $payer->address->zip_code    = $this->mercadopago->orderBilling->getZipcode($this->order);
        $payer->address->street_name = $this->mercadopago->orderBilling->getFullAddress($this->order);
    }

    /**
     * Set back URLs
     *
     * @return void
     */
    public function setBackUrlsTransaction(): void
    {
        $successUrl = $this->mercadopago->options->getGatewayOption($this->gateway, 'success_url');
        $failureUrl = $this->mercadopago->options->getGatewayOption($this->gateway, 'failure_url');
        $pendingUrl = $this->mercadopago->options->getGatewayOption($this->gateway, 'pending_url');

        $this->transaction->back_urls->success = empty($successUrl)
            ? $this->mercadopago->strings->fixUrlAmpersand(esc_url($this->get_return_url($this->order)))
            : $successUrl;

        $this->transaction->back_urls->failure = empty($failureUrl)
            ? $this->mercadopago->strings->fixUrlAmpersand(esc_url($this->order->get_cancel_order_url()))
            : $failureUrl;

        $this->transaction->back_urls->pending = empty($pendingUrl)
            ? $this->mercadopago->strings->fixUrlAmpersand(esc_url($this->get_return_url($this->order)))
            : $pendingUrl;
    }

    /**
     * Set auto return
     *
     * @return void
     */
    public function setAutoReturnTransaction(): void
    {
        if ($this->mercadopago->options->getGatewayOption($this->gateway, 'auto_return') === 'yes') {
            $this->transaction->auto_return = 'approved';
        }
    }
}
