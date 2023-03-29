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
        $preference = $this->getTransaction();

        try {
            $data = $preference->save();
            $this->mercadopago->logs->file->info('Preference created', __METHOD__, $data);
            return $this->mercadopago->seller->isTestMode() ? $data['sandbox_init_point'] : $data['init_point'];
        } catch (\Exception $e) {
            $this->mercadopago->logs->file->error('Preference creation failed: ' . $e->getMessage(), __METHOD__);
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

        $isTestUser = $this->mercadopago->options->get('_test_user_v1', '');
        $isTestMode = $this->mercadopago->seller->isTestMode();

        if (!$isTestUser && !$isTestMode) {
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

        $payer->email                = $this->getObjectAttributeValue($this->order, 'get_billing_email', 'billing_email');
        $payer->name                 = $this->getObjectAttributeValue($this->order, 'get_billing_first_name', 'billing_first_name');
        $payer->surname              = $this->getObjectAttributeValue($this->order, 'get_billing_last_name', 'billing_last_name');
        $payer->phone->number        = $this->getObjectAttributeValue($this->order, 'get_billing_phone', 'billing_phone');
        $payer->address->zip_code    = $this->getObjectAttributeValue($this->order, 'get_billing_postcode', 'billing_postcode');
        $payer->address->street_name = "
            {$this->getObjectAttributeValue($this->order, 'get_billing_address_1', 'billing_address_1')}
            {$this->getObjectAttributeValue($this->order, 'get_billing_address_2', 'billing_address_2')}
            {$this->getObjectAttributeValue($this->order, 'get_billing_city', 'billing_city')}
            {$this->getObjectAttributeValue($this->order, 'get_billing_state', 'billing_state')}
            {$this->getObjectAttributeValue($this->order, 'get_billing_country', 'billing_country')}
        ";
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
        $autoReturn = $this->mercadopago->options->get('auto_return', 'yes') === 'yes';

        if ($autoReturn) {
            $this->transaction->auto_return = 'approved';
        }
    }
}
