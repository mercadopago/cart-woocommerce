<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use WC_Order;

abstract class AbstractPreferenceTransaction extends AbstractTransaction
{
    public function __construct(AbstractGateway $gateway, WC_Order $order)
    {
        parent::__construct($gateway, $order);

        $this->transaction = $this->getSdk()->getPreferenceInstance();

        $this->setCommonTransaction();
        $this->setPayerTransaction();
        $this->setBackUrlsTransaction();
        $this->setAutoReturnTransaction();
        $this->setShipmentsTransaction($this->transaction->shipments);
        $this->setItemsTransaction($this->transaction->items);
        $this->setShippingTransaction($this->transaction->items);
        $this->setFeeTransaction($this->transaction->items);
        $this->setAdditionalInfoTransaction();
    }

    public function createPreference()
    {
        $this->logTransactionPayload();
        $data = $this->transaction->save();
        $this->mercadopago->logs->file->info('Preference created', $this->gateway::LOG_SOURCE, $data);

        return $data;
    }

    public function setCommonTransaction(): void
    {
        parent::setCommonTransaction();

        $isTestMode = $this->mercadopago->storeConfig->isTestMode();
        $isTestUser = $this->mercadopago->sellerConfig->isTestUser();

        if (!$isTestMode && !$isTestUser) {
            $this->transaction->sponsor_id = $this->countryConfigs['sponsor_id'];
        }
    }

    public function setPayerTransaction(): void
    {
        $payer                       = $this->transaction->payer;
        $payer->email                = $this->mercadopago->orderBilling->getEmail($this->order);
        $payer->name                 = $this->mercadopago->orderBilling->getFirstName($this->order);
        $payer->surname              = $this->mercadopago->orderBilling->getLastName($this->order);
        $payer->phone->number        = $this->mercadopago->orderBilling->getPhone($this->order);
        $payer->address->zip_code    = $this->mercadopago->orderBilling->getZipcode($this->order);
        $payer->address->street_name = $this->mercadopago->orderBilling->getFullAddress($this->order);
    }

    public function setBackUrlsTransaction(): void
    {
        $sanitizeFallback = fn(string $fallback) => $this->mercadopago->helpers->strings->fixUrlAmpersand(esc_url($fallback));

        $this->transaction->back_urls->success =
            $this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'success_url') ?:
            $sanitizeFallback($this->gateway->get_return_url($this->order));

        $this->transaction->back_urls->failure =
            $this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'failure_url') ?:
            $sanitizeFallback($this->order->get_cancel_order_url());

        $this->transaction->back_urls->pending =
            $this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'pending_url') ?:
            $sanitizeFallback($this->gateway->get_return_url($this->order));
    }

    public function setAutoReturnTransaction(): void
    {
        if ($this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'auto_return') === 'yes') {
            $this->transaction->auto_return = 'approved';
        }
    }
}
