<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

final class Currency
{
    /**
     * @const
     */
    const CURRENCY_CONVERSION = 'currency_conversion';

    /**
     * @const
     */
    const DEFAULT_RATIO = 1;

    /**
     * @var array
     */
    private $ratios = array();

    /**
     * @var array
     */
    private $currencyAche = array();

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var Country
     */
    private $country;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var Logs
     */
    private $logs;

    /**
     * @var Requester
     */
    private $requester;

    /**
     * Currency constructor
     *
     * @param Cache $cache
     * @param Country $country
     * @param Options $options
     * @param Logs $logs
     * @param Requester $requester
     */
    public function __construct(Cache $cache, Country $country, Options $options, Logs $logs, Requester $requester)
    {
        $this->cache     = $cache;
        $this->country   = $country;
        $this->options   = $options;
        $this->logs      = $logs;
        $this->requester = $requester;
    }

    public function init(object $method, string $siteId, string $accessToken): Currency
    {
        $methodId = $method->id;

        if (!isset($this->ratios[$methodId])) {

            if (!$this->isEnabled($method)) {
                $this->setRatio($methodId);

                return $this;
            }

            $accountCurrency = $this->getAccountCurrency($methodId, $siteId);
            $localCurrency   = get_woocommerce_currency();

            if (!$accountCurrency || $accountCurrency === $localCurrency) {
                $this->setRatio($methodId);

                return $this;
            }

            $ratio = $this->loadRatio($localCurrency, $accountCurrency, $accessToken);

            $this->setRatio($methodId, $ratio);
        }

        return $this;

    }

    public function getRatio($methodId): int
    {
        return $this->ratios[$methodId] || self::DEFAULT_RATIO;
    }

    public function setRatio($methodId, $value = self::DEFAULT_RATIO)
    {
        $this->ratios[$methodId] = $value;
    }

    public function isEnabled(object $method): bool
    {
        return $method->get_option(self::CURRENCY_CONVERSION, '');
    }

    private function getAccountCurrency(string $methodId, string $siteId) {
        if ($this->currencyAche[$methodId]) {
            return $this->currencyAche[$methodId];
        }

        $country = $this->country->siteIdToCountry($siteId);
        $configs = $this->country->getCountryConfigs($country);

        return $configs[$country] ? $configs[$country]['currency'] : false;
    }

    private function loadRatio(string $fromCurrency, string $toCurrency, string $accessToken) {
        if($fromCurrency === $toCurrency) {
            return self::DEFAULT_RATIO;
        }

        $currencyConversionResponse = $this->getCurrencyConversion($fromCurrency, $toCurrency, $accessToken);

        try {
            if (200 !== $currencyConversionResponse['status']) {
                throw new \Exception($currencyConversionResponse['data']);
            }

            if (isset($currencyConversionResponse['data'], $currencyConversionResponse['data']['ratio']) && $currencyConversionResponse['data']['ratio'] > 0) {
                return $currencyConversionResponse['data']['ratio'];
            }

        } catch (\Exception $e) {
            $this->logs->file->error('Mercado pago gave error to get currency value, payment creation failed with error: '. $e->getMessage(), __FUNCTION__);
        }

        return self::DEFAULT_RATIO;
    }

    private function getCurrencyConversion(string $fromCurrency, string $toCurrency, string $accessToken)
    {
        try {
            $key   = sprintf('%sat%s-%sto%s', __FUNCTION__, $accessToken, $fromCurrency, $toCurrency);
            $cache = $this->cache->getCache($key);

            if ($cache) {
                return $cache;
            }

            $uri =  sprintf('/currency_conversions/search?from=%s&to=%s', $fromCurrency, $toCurrency);
            $headers = ['Authorization: Bearer ' . $accessToken];

            $response           = $this->requester->get($uri, $headers);
            $serializedResponse = [
                'data'   => $response->getData(),
                'status' => $response->getStatus(),
            ];

            $this->cache->setCache($key, $serializedResponse);

           return $serializedResponse;

        } catch (\Exception $e) {
            return [
                'data'   => null,
                'status' => 500,
            ];
        }
    }
}
