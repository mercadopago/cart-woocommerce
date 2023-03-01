<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

abstract class AbstractPreferenceTransaction extends AbstractTransaction
{
    /**
     * Preference Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order)
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
     * @return bool
     */
    public function createPreference(): string
    {
        $preference = $this->getTransaction();

        try {
            $data = $preference->save();

            $this->mercadopago->logs->file->info(
                'Preference created: ' . wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                __FUNCTION__
            );

            $checkboxCheckoutTestMode = $this->mercadopago->store->getCheckboxCheckoutTestMode();
            $testMode = ($checkboxCheckoutTestMode === 'yes');

            return ($testMode) ? $data['sandbox_init_point'] : $data['init_point'];
        } catch (\Exception $e) {
            $this->mercadopago->logs->file->error(
                'preference creation failed with error: ' . $e->getMessage(),
                __FUNCTION__
            );

            return false;
        }
    }

    /**
     * Set common transaction
     */
    public function setCommonTransaction()
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
     */
    public function setPayerTransaction()
    {
        $payer = $this->transaction->payer;

        $payer->email                =
            $this->getObjectAttributeValue($this->order, 'get_id', 'billing_email', 'get_billing_email');
        $payer->name                 =
            $this->getObjectAttributeValue($this->order, 'get_id', 'billing_first_name', 'get_billing_first_name');
        $payer->surname              =
            $this->getObjectAttributeValue($this->order, 'get_id', 'billing_last_name', 'get_billing_last_name');
        $payer->phone->number        =
            $this->getObjectAttributeValue($this->order, 'get_id', 'billing_phone', 'get_billing_phone');
        $payer->address->street_name =
            "{$this->getObjectAttributeValue($this->order, 'get_id', 'billing_address_1', 'get_billing_address_1')} / " .
            "{$this->getObjectAttributeValue($this->order, 'get_id', 'billing_city', 'get_billing_city')} " .
            "{$this->getObjectAttributeValue($this->order, 'get_id', 'billing_state', 'get_billing_state')} " .
            "{$this->getObjectAttributeValue($this->order, 'get_id', 'billing_country', 'get_billing_country')}";
        $payer->address->zip_code    =
            $this->getObjectAttributeValue($this->order, 'get_id', 'billing_postcode', 'get_billing_postcode');
    }

    /**
     * Set back URLs
     */
    public function setBackUrlsTransaction()
    {
        $successUrl = $this->mercadopago->options->getMercadoPago($this->gateway, 'success_url');
        $failureUrl = $this->mercadopago->options->getMercadoPago($this->gateway, 'failure_url');
        $pendingUrl = $this->mercadopago->options->getMercadoPago($this->gateway, 'pending_url');

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
     */
    public function setAutoReturnTransaction()
    {
        $autoReturn = $this->mercadopago->options->get('auto_return', 'yes');
        if ('yes' === $autoReturn) {
            $this->transaction->auto_return = 'approved';
        }
    }
}
