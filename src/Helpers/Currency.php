<?php

namespace MercadoPago\Woocommerce\Helpers;

use Exception;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Translations\AdminTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class Currency
{
    private const CURRENCY_CONVERSION = 'currency_conversion';

    private const DEFAULT_RATIO = 1;

    private array $ratios = [];

    private array $translations;

    private Cache $cache;

    private Country $country;

    private Notices $notices;

    private Requester $requester;

    private Seller $seller;

    private Options $options;

    private Url $url;

    private Datadog $datadog;

    private Store $store;

    /**
     * Currency constructor
     *
     * @param AdminTranslations $adminTranslations
     * @param Cache             $cache
     * @param Country           $country
     * @param Notices           $notices
     * @param Requester         $requester
     * @param Seller            $seller
     * @param Options           $options
     * @param Url               $url
     * @param Store             $store
     */
    public function __construct(
        AdminTranslations $adminTranslations,
        Cache $cache,
        Country $country,
        Notices $notices,
        Requester $requester,
        Seller $seller,
        Options $options,
        Url $url,
        Store $store
    ) {
        $this->translations = $adminTranslations->currency;
        $this->cache        = $cache;
        $this->country      = $country;
        $this->notices      = $notices;
        $this->requester    = $requester;
        $this->seller       = $seller;
        $this->options      = $options;
        $this->url          = $url;
        $this->datadog      = Datadog::getInstance();
        $this->store        = $store;
    }

    /**
     * Get account currency
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->country->getCountryConfigs()['currency'];
    }

    /**
     * Get account currency symbol
     *
     * @return string
     */
    public function getCurrencySymbol(): string
    {
        return $this->country->getCountryConfigs()['currency_symbol'];
    }

    /**
     * Get Woocommerce currency
     *
     * @return string
     */
    public function getWoocommerceCurrency(): string
    {
        return get_woocommerce_currency();
    }

    /**
     * Get ratio incrementing the ratios array by gateway
     *
     * @param AbstractGateway $gateway
     *
     * @return float
     * @throws Exception
     */
    public function getRatio(AbstractGateway $gateway): float
    {
        if (!isset($this->ratios[$gateway->id])) {
            if ($this->isConversionEnabled($gateway)) {
                if (!$this->validateConversion()) {
                    $this->sendCurrencyConversionActiveMetric($gateway, 'mp_currency_conversion_diff');
                    $ratio = $this->loadRatio();
                    $this->setRatio($gateway->id, $ratio);
                } else {
                    $this->sendCurrencyConversionActiveMetric($gateway, 'mp_currency_conversion_same');
                    $this->setRatio($gateway->id);
                }
            } else {
                $this->setRatio($gateway->id);
            }
        }

        return $this->ratios[$gateway->id] ?: self::DEFAULT_RATIO;
    }

    /**
     * Get ratio without loading or setting it
     *
     * @param string|null $gateway_id
     *
     * @return float
     */
    public function onlyGetRatio(?string $gateway_id = null): float
    {
        return isset($this->ratios[$gateway_id]) ? $this->ratios[$gateway_id] : 1;
    }

    public function getCurrencyCode(AbstractGateway $gateway): string
    {
        if ($this->isConversionEnabled($gateway) && !$this->validateConversion()) {
            return $this->getCurrency();
        }

        return $this->getWoocommerceCurrency();
    }

    /**
     * Set ratio
     *
     * @param string $gatewayId
     * @param float $value
     *
     * @return void
     */
    public function setRatio(string $gatewayId, $value = self::DEFAULT_RATIO)
    {
        $this->ratios[$gatewayId] = $value;
    }

    /**
     * Verify if currency option is enabled
     *
     * @param AbstractGateway $gateway
     *
     * @return bool
     */
    public function isConversionEnabled(AbstractGateway $gateway): bool
    {
        return $this->options->getGatewayOption($gateway, self::CURRENCY_CONVERSION) === 'yes';
    }

    /**
     * Validate if account currency is equal to woocommerce currency
     *
     * @return bool
     */
    public function validateConversion(): bool
    {
        return $this->getCurrency() === $this->getWoocommerceCurrency();
    }

    /**
     * Handle currency conversion notices
     *
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function handleCurrencyNotices(AbstractGateway $gateway): void
    {
        if ($this->validateConversion() || !$this->url->validateSection($gateway->id)) {
            return;
        }

        if (!$this->validateConversion() && $this->isConversionEnabled($gateway)) {
            $this->showWeConvertingNoticeByCountry();
        }

        if (!$this->validateConversion() && !$this->isConversionEnabled($gateway)) {
            $this->notices->adminNoticeWarning($this->translations['not_compatible_currency_conversion']);
        }
    }

    /**
     * Load ratio
     *
     * @return float
     * @throws Exception
     */
    private function loadRatio(): float
    {
        $response = $this->getCurrencyConversion();
        if ($response['status'] !== 200) {
            throw new Exception(json_encode($response['data']));
        }
        if (isset($response['data']['ratio']) && $response['data']['ratio'] > 0) {
            return $response['data']['ratio'];
        }
        return self::DEFAULT_RATIO;
    }

    /**
     * Get currency conversion
     *
     * @return array
     */
    private function getCurrencyConversion(): array
    {
        $toCurrency   = $this->getCurrency();
        $fromCurrency = $this->getWoocommerceCurrency();
        $accessToken  = $this->seller->getCredentialsAccessToken();

        try {
            $key   = sprintf('%sat%s-%sto%s', __FUNCTION__, $accessToken, $fromCurrency, $toCurrency);
            $cache = $this->cache->getCache($key);

            if ($cache) {
                return $cache;
            }

            $uri     = sprintf('/currency_conversions/search?from=%s&to=%s', $fromCurrency, $toCurrency);
            $headers = ['Authorization: Bearer ' . $accessToken];

            $response           = $this->requester->get($uri, $headers);
            $serializedResponse = [
                'data'   => $response->getData(),
                'status' => $response->getStatus(),
            ];

            if ($response->getStatus() >= 400) {
                $data = $response->getData();
                $errorMessage = is_array($data) && isset($data['message']) && is_string($data['message']) ? $data['message'] : 'HTTP ' . $response->getStatus();

                $this->sendCurrencyConversionErrorMetric(
                    $fromCurrency,
                    $toCurrency,
                    $response->getStatus(),
                    $errorMessage
                );

                return $serializedResponse;
            }

            $this->cache->setCache($key, $serializedResponse);

            return $serializedResponse;
        } catch (Exception $e) {
            $this->sendCurrencyConversionErrorMetric(
                $fromCurrency,
                $toCurrency,
                0,
                $e->getMessage()
            );

            return [
                'data'   => null,
                'status' => 500,
            ];
        }
    }

    /**
     * Send cached daily metric for currency conversion active status
     *
     * @param AbstractGateway $gateway
     * @param string          $eventType
     *
     * @return void
     */
    private function sendCurrencyConversionActiveMetric(AbstractGateway $gateway, string $eventType): void
    {
        $cacheKey = sprintf('metric_%s_%s', $eventType, $gateway->id);

        if ($this->cache->getCache($cacheKey)) {
            return;
        }

        $this->datadog->sendEvent(
            $eventType,
            '1',
            null,
            $gateway->getPaymentMethodName(),
            [
                'site_id'        => $this->seller->getSiteId(),
                'environment'    => $this->store->isTestMode() ? 'homol' : 'prod',
                'cust_id'        => $this->seller->getCustIdFromAT(),
                'from_currency'  => $this->getWoocommerceCurrency(),
            ]
        );

        $this->cache->setCache($cacheKey, true, 86400);
    }

    /**
     * Send currency conversion error metric to Datadog
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param int    $httpStatus
     * @param string $errorMessage
     *
     * @return void
     */
    private function sendCurrencyConversionErrorMetric(
        string $fromCurrency,
        string $toCurrency,
        int $httpStatus,
        string $errorMessage
    ): void {
        $this->datadog->sendEvent(
            'mp_currency_conversion_error',
            (string) $httpStatus,
            $errorMessage,
            null,
            [
                'site_id' => $this->seller->getSiteId(),
                'environment' => $this->store->isTestMode() ? 'homol' : 'prod',
                'cust_id' => $this->seller->getCustIdFromAT(),
                'from_currency' => $fromCurrency,
                'to_currency'   => $toCurrency,
            ]
        );
    }

    /**
     * Set how 'we're converting' notice is show up.
     *
     * @return void
     */
    private function showWeConvertingNoticeByCountry()
    {
        $this->notices->adminNoticeInfo($this->translations['now_we_convert'] . $this->getCurrency());
    }
}
