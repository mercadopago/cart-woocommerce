<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\Woocommerce\Api\Core\PreferencesClient;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use WC_Order;

abstract class AbstractPreferenceTransaction extends AbstractTransaction
{
    public function __construct(AbstractGateway $gateway, WC_Order $order)
    {
        parent::__construct($gateway, $order);

        $this->transactionType = 'Preference';
        $this->transaction = [
            'additional_info' => [
                'items' => [],
                'shipments' => ['receiver_address' => []],
                'payer' => [
                    'phone' => [],
                    'mobile' => [],
                    'address' => [],
                    'identification' => [],
                ],
                'seller' => [],
            ],
            'back_urls' => [],
            'items' => [],
            'payer' => [
                'phone' => [],
                'address' => [],
            ],
            'payment_methods' => ['excluded_payment_methods' => []],
            'shipments' => ['receiver_address' => []],
        ];

        $this->setCommonTransaction();
        $this->setPayerTransaction();
        $this->setBackUrlsTransaction();
        $this->setAutoReturnTransaction();
        $this->setShipmentsTransaction($this->transaction['shipments']);
        $this->setItemsTransaction($this->transaction['items']);
        $this->setShippingTransaction($this->transaction['items']);
        $this->setFeeTransaction($this->transaction['items']);
        $this->setAdditionalInfoTransaction();
    }

    public function createPreference()
    {
        $this->logTransactionPayload();

        try {
            $data = $this->mercadopago->coreApi->preferences
                ->create($this->transaction, $this->getCoreRequestContext())
                ->toArray();
            $this->mercadopago->logs->file->info('Preference created', $this->gateway::LOG_SOURCE, [
                'id' => isset($data['id']) ? (string) $data['id'] : '',
            ]);
            return $data;
        } catch (Exception $e) {
            $this->sendApiErrorMetric(PreferencesClient::CREATE_PATH, $e);
            throw $e;
        }
    }

    public function setCommonTransaction(): void
    {
        parent::setCommonTransaction();

        $isTestMode = $this->mercadopago->storeConfig->isTestMode();
        $isTestUser = $this->mercadopago->sellerConfig->isTestUser();

        if (!$isTestMode && !$isTestUser) {
            $this->transaction['sponsor_id'] = $this->countryConfigs['sponsor_id'];
        }
    }

    public function setPayerTransaction(): void
    {
        $this->transaction['payer']['email'] = $this->mercadopago->orderBilling->getEmail($this->order);
        $this->transaction['payer']['name'] = $this->mercadopago->orderBilling->getFirstName($this->order);
        $this->transaction['payer']['surname'] = $this->mercadopago->orderBilling->getLastName($this->order);
        $this->transaction['payer']['phone']['number'] = $this->mercadopago->orderBilling->getPhone($this->order);
        $this->transaction['payer']['address']['zip_code'] = $this->mercadopago->orderBilling->getZipcode($this->order);
        $this->transaction['payer']['address']['street_name'] = $this->mercadopago->orderBilling->getFullAddress($this->order);
    }

    public function setBackUrlsTransaction(): void
    {
        $sanitizeFallback = fn(string $fallback) => $this->mercadopago->helpers->strings->fixUrlAmpersand(esc_url($fallback));

        $this->transaction['back_urls']['success'] =
            $this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'success_url') ?:
            $sanitizeFallback($this->gateway->get_return_url($this->order));

        $this->transaction['back_urls']['failure'] =
            $this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'failure_url') ?:
            $sanitizeFallback($this->order->get_cancel_order_url());

        $this->transaction['back_urls']['pending'] =
            $this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'pending_url') ?:
            $sanitizeFallback($this->gateway->get_return_url($this->order));
    }

    public function setAutoReturnTransaction(): void
    {
        if ($this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'auto_return') === 'yes') {
            $this->transaction['auto_return'] = 'approved';
        }
    }
}
