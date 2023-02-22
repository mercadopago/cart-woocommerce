<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Translations\AdminTranslations;

if (!defined('ABSPATH')) {
    exit;
}

final class Currency
{
    /**
     * @const
     */
    private const CURRENCY_CONVERSION = 'currency_conversion';

    /**
     * @const
     */
    private const DEFAULT_RATIO = 1;

    /**
     * @var array
     */
    private $ratios = [];

    /**
     * @var AdminTranslations
     */
    private $adminTranslations;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var Country
     */
    private $country;

    /**
     * @var Logs
     */
    private $logs;

    /**
     * @var Notices
     */
    private $notice;

    /**
     * @var Requester
     */
    private $requester;

    /**
     * @var Seller
     */
    private $seller;

    /**
     * Currency constructor
     *
     * @param AdminTranslations $adminTranslations
     * @param Cache $cache
     * @param Country $country
     * @param Logs $logs
     * @param Notices $notice
     * @param Requester $requester
     * @param Seller $seller
     */
    public function __construct(AdminTranslations $adminTranslations, Cache $cache, Country $country, Logs $logs, Notices $notice, Requester $requester, Seller $seller)
    {
        $this->adminTranslations = $adminTranslations;
        $this->cache             = $cache;
        $this->country           = $country;
        $this->logs              = $logs;
        $this->notice            = $notice;
        $this->requester         = $requester;
        $this->seller            = $seller;
    }

    /**
     * Init incrementing the ratios array by method
     *
     * @param $method
     *
     * @return void
     */
    public function init($method)
    {
        if (!isset($this->ratios[$method->id])) {
            $accountCurrency = $this->getAccountCurrency();
            $storeCurrency   = get_woocommerce_currency();

            if ($this->isEnabled($method) && $this->isAValidConversion($storeCurrency, $accountCurrency)) {
                $ratio = $this->loadRatio($storeCurrency, $accountCurrency);
                $this->setRatio($method->id, $ratio);
            } else {
                $this->setRatio($method->id);
            }
        }
    }

    /**
     * Get ratio
     *
     * @param $method
     *
     * @return float
     */
    public function getRatio($method): float
    {
        $this->init($method);
        return $this->ratios[$method->id] ?: self::DEFAULT_RATIO;
    }


    /**
     * Get Account currency
     *
     * @return string
     */
    public function getAccountCurrency(): string
    {
        $siteId  = $this->seller->getSiteId();
        $configs = $this->country->getCountryConfigs();

        return $configs['currency'];
    }

    /**
     * Set ratio
     *
     * @param $methodId
     * @param float $value
     *
     * @return void
     */
    public function setRatio($methodId, $value = self::DEFAULT_RATIO)
    {
        $this->ratios[$methodId] = $value;
    }

    /**
     * Is the option enabled?
     *
     * @param $method
     *
     * @return bool
     */
    public function isEnabled($method): bool
    {
        return $method->get_option(self::CURRENCY_CONVERSION, '');
    }

    /**
     * Is it a valid conversion?
     *
     * @param string $storeCurrency
     * @param string $accountCurrency
     *
     * @return bool
     */
    private function isAValidConversion(string $storeCurrency, string $accountCurrency): bool
    {
        if (!$accountCurrency || !$storeCurrency || $storeCurrency === $accountCurrency) {
            return false;
        }

        return true;
    }

    /**
     * Load ratio
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     *
     * @return float
     */
    private function loadRatio(string $fromCurrency, string $toCurrency): float
    {
        $currencyConversionResponse = $this->getCurrencyConversion($fromCurrency, $toCurrency);

        try {
            if (200 !== $currencyConversionResponse['status']) {
                throw new \Exception($currencyConversionResponse['data']);
            }

            if (isset($currencyConversionResponse['data'], $currencyConversionResponse['data']['ratio']) && $currencyConversionResponse['data']['ratio'] > 0) {
                return $currencyConversionResponse['data']['ratio'];
            }
        } catch (\Exception $e) {
            $this->logs->file->error('Mercado pago gave error to get currency value, payment creation failed with error: ' . $e->getMessage(), __FUNCTION__);
        }

        return self::DEFAULT_RATIO;
    }

    /**
     * Get currency conversion
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     *
     * @return array
     */
    private function getCurrencyConversion(string $fromCurrency, string $toCurrency): array
    {
        $accessToken = $this->seller->getCredentialsAccessToken();

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

            $this->cache->setCache($key, $serializedResponse);

            return $serializedResponse;
        } catch (\Exception $e) {
            return [
                'data'   => null,
                'status' => 500,
            ];
        }
    }

    /**
     * Handle currency conversion notices
     *
     * @param $method
     *
     * @return void
     */
    public function handleCurrencyNotices($method)
    {
        $dataSession = $_SESSION[self::CURRENCY_CONVERSION] ?: [];

        if ($dataSession['notice']) {
            unset($_SESSION[self::CURRENCY_CONVERSION]['notice']);

            if ('enabled' === $dataSession['notice']['type']) {
                $this->notice->adminNoticeInfo($this->adminTranslations->notices['currency_enabled'], false);
            } elseif ('disabled' === $dataSession['notice']['type']) {
                $this->notice->adminNoticeInfo($this->adminTranslations->notices['currency_disabled'], false);
            }
        }

        if (!$this->isEnabled($method) && $method->isCurrencyConvertable()) {
            $this->notice->adminNoticeWarning($this->adminTranslations->notices['currency_conversion'], false);
        }
    }
}
