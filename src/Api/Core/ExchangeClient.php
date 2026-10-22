<?php

namespace MercadoPago\Woocommerce\Api\Core;

use MercadoPago\Woocommerce\Api\Core\Models\ExchangeRate;

if (!defined('ABSPATH')) {
    exit;
}

class ExchangeClient
{
    private CoreApiClient $apiClient;

    public function __construct(CoreApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function getRate(string $currencyId, CoreRequestContext $context, bool $isTestMode): ExchangeRate
    {
        if (preg_match('/^[A-Z]{3}$/', $currencyId) !== 1) {
            throw new \InvalidArgumentException('Invalid currency id.');
        }

        $environment = $isTestMode ? 'beta' : 'prod';
        $path = sprintf(
            '/ppcore/%s/payment-methods/v1/exchange?currency.id=%s',
            $environment,
            rawurlencode($currencyId)
        );
        $response = $this->apiClient->get($path, $context->toHeaders());
        $data = $response->getData();
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Core exchange response must be a JSON object.');
        }

        return ExchangeRate::fromArray($data);
    }
}
