<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\Notices;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\PP\Sdk\HttpClient\Response;
use Exception;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class CurrencyTest extends TestCase
{
    use GatewayMock;

    private string $gatewayClass = CustomGateway::class;

    private Currency $currency;
    private $adminTranslations;
    private $cache;
    private $country;
    private $notices;
    private $requester;
    private $seller;
    private $options;
    private $url;
    private $store;

    public function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        // Mock dependencies
        $this->adminTranslations = Mockery::mock(AdminTranslations::class);
        $this->adminTranslations->currency = [];
        $this->cache = Mockery::mock(Cache::class);
        $this->country = Mockery::mock(Country::class);
        $this->notices = Mockery::mock(Notices::class);
        $this->requester = Mockery::mock(Requester::class);
        $this->seller = Mockery::mock(Seller::class)->makePartial();
        $this->options = Mockery::mock(Options::class);
        $this->url = Mockery::mock(Url::class);
        $this->store = Mockery::mock(Store::class);

        // Create Currency instance
        $this->currency = new Currency(
            $this->adminTranslations,
            $this->cache,
            $this->country,
            $this->notices,
            $this->requester,
            $this->seller,
            $this->options,
            $this->url,
            $this->store
        );
    }

    public function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Test getCurrencyCode when conversion is enabled and currencies are different
     * Should return Mercado Pago currency
     */
    public function testGetCurrencyCodeWhenConversionEnabledAndDifferentCurrencies()
    {
        // Mock: conversion is enabled
        $this->options
            ->shouldReceive('getGatewayOption')
            ->with($this->gateway, 'currency_conversion')
            ->once()
            ->andReturn('yes');

        // Mock: MP currency is BRL (called twice: once in getCurrency, once in validateConversion)
        $this->country
            ->shouldReceive('getCountryConfigs')
            ->twice()
            ->andReturn(['currency' => 'BRL', 'currency_symbol' => 'R$']);

        // Mock: WooCommerce currency is USD (different from BRL, called once in validateConversion)
        WP_Mock::userFunction('get_woocommerce_currency')
            ->once()
            ->andReturn('USD');

        // Execute
        $result = $this->currency->getCurrencyCode($this->gateway);

        // Assert: should return MP currency (BRL)
        $this->assertEquals('BRL', $result);
    }

    /**
     * Test getCurrencyCode when conversion is enabled but currencies are the same
     * Should return WooCommerce currency
     */
    public function testGetCurrencyCodeWhenConversionEnabledButSameCurrencies()
    {
        // Mock: conversion is enabled
        $this->options
            ->shouldReceive('getGatewayOption')
            ->with($this->gateway, 'currency_conversion')
            ->once()
            ->andReturn('yes');

        // Mock: MP currency is BRL (only in validateConversion)
        $this->country
            ->shouldReceive('getCountryConfigs')
            ->once()
            ->andReturn(['currency' => 'BRL', 'currency_symbol' => 'R$']);

        // Mock: WooCommerce currency is also BRL (called twice: validateConversion + getCurrencyCode)
        WP_Mock::userFunction('get_woocommerce_currency')
            ->twice()
            ->andReturn('BRL');

        // Execute
        $result = $this->currency->getCurrencyCode($this->gateway);

        // Assert: should return WooCommerce currency (BRL)
        $this->assertEquals('BRL', $result);
    }

    /**
     * Test getCurrencyCode when conversion is disabled
     * Should always return WooCommerce currency
     */
    public function testGetCurrencyCodeWhenConversionDisabled()
    {
        // Mock: conversion is disabled
        $this->options
            ->shouldReceive('getGatewayOption')
            ->with($this->gateway, 'currency_conversion')
            ->once()
            ->andReturn('no');

        // Mock: WooCommerce currency is USD
        WP_Mock::userFunction('get_woocommerce_currency')
            ->once()
            ->andReturn('USD');

        // Execute
        $result = $this->currency->getCurrencyCode($this->gateway);

        // Assert: should return WooCommerce currency (USD)
        $this->assertEquals('USD', $result);
    }

    /**
     * Helper to set up common mocks for getRatio error metric tests
     */
    private function setupGetRatioMocks(): void
    {
        // Gateway needs an id and paymentMethodName for getRatio
        $this->gateway->id = 'woo-mercado-pago-custom';
        $this->gateway->shouldReceive('getPaymentMethodName')->andReturn('custom');

        // conversion enabled
        $this->options
            ->shouldReceive('getGatewayOption')
            ->with($this->gateway, 'currency_conversion')
            ->andReturn('yes');

        // MP currency (BRL) != WooCommerce currency (USD)
        $this->country
            ->shouldReceive('getCountryConfigs')
            ->andReturn(['currency' => 'BRL', 'currency_symbol' => 'R$']);

        WP_Mock::userFunction('get_woocommerce_currency')
            ->andReturn('USD');

        // Seller mocks for metric details
        $this->seller
            ->shouldReceive('getCredentialsAccessToken')
            ->andReturn('TEST-ACCESS-TOKEN');

        $this->seller
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->seller
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('12345');

        $this->store
            ->shouldReceive('isTestMode')
            ->andReturn(true);

        // No cache (for both API cache and metric cache)
        $this->cache
            ->shouldReceive('getCache')
            ->andReturn(null);

        // Allow metric cache writes
        $this->cache
            ->shouldReceive('setCache')
            ->byDefault();
    }

    /**
     * Test that metric is sent when API returns HTTP error status
     */
    public function testGetRatioSendsMetricOnHttpError()
    {
        $this->setupGetRatioMocks();

        // Mock API response with 403 error
        $response = new Response();
        $response->setStatus(403);
        $response->setData(['message' => 'VEF/VES currency convertion is not allowed']);

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($response);

        // Mock Datadog: byDefault() absorbs the active metric (diff/same) call,
        // while the specific expectation below validates the error metric.
        $datadogMock = Mockery::mock(Datadog::class);
        $datadogMock->shouldReceive('sendEvent')->byDefault();
        $datadogMock
            ->shouldReceive('sendEvent')
            ->once()
            ->with(
                'mp_currency_conversion_error',
                '403',
                'VEF/VES currency convertion is not allowed',
                null,
                Mockery::on(function ($details) {
                    return $details['site_id'] === 'MLB'
                        && $details['environment'] === 'homol'
                        && $details['cust_id'] === '12345'
                        && $details['from_currency'] === 'USD'
                        && $details['to_currency'] === 'BRL';
                })
            );

        $this->setNotAccessibleProperty($this->currency, 'datadog', $datadogMock);

        // getRatio throws because status !== 200
        $this->expectException(Exception::class);
        $this->currency->getRatio($this->gateway);
    }

    /**
     * Test that metric is sent when API call throws an exception
     */
    public function testGetRatioSendsMetricOnException()
    {
        $this->setupGetRatioMocks();

        // Mock requester throwing exception
        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andThrow(new Exception('Connection timed out'));

        // Mock Datadog: byDefault() absorbs the active metric (diff/same) call,
        // while the specific expectation below validates the error metric.
        $datadogMock = Mockery::mock(Datadog::class);
        $datadogMock->shouldReceive('sendEvent')->byDefault();
        $datadogMock
            ->shouldReceive('sendEvent')
            ->once()
            ->with(
                'mp_currency_conversion_error',
                '0',
                'Connection timed out',
                null,
                Mockery::on(function ($details) {
                    return $details['from_currency'] === 'USD'
                        && $details['to_currency'] === 'BRL'
                        && $details['site_id'] === 'MLB'
                        && $details['environment'] === 'homol';
                })
            );

        $this->setNotAccessibleProperty($this->currency, 'datadog', $datadogMock);

        // getRatio throws because getCurrencyConversion returns status 500
        $this->expectException(Exception::class);
        $this->currency->getRatio($this->gateway);
    }

    /**
     * Test that metric extracts message from API body when available
     */
    public function testGetRatioUsesApiBodyMessageWhenAvailable()
    {
        $this->setupGetRatioMocks();

        // Mock API response with 400 and message in body
        $response = new Response();
        $response->setStatus(400);
        $response->setData(['message' => 'Invalid currency pair']);

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($response);

        // Expect message from API body, not generic "HTTP 400"
        $datadogMock = Mockery::mock(Datadog::class);
        $datadogMock->shouldReceive('sendEvent')->byDefault();
        $datadogMock
            ->shouldReceive('sendEvent')
            ->once()
            ->with(
                'mp_currency_conversion_error',
                '400',
                'Invalid currency pair',
                null,
                Mockery::type('array')
            );

        $this->setNotAccessibleProperty($this->currency, 'datadog', $datadogMock);

        $this->expectException(Exception::class);
        $this->currency->getRatio($this->gateway);
    }

    /**
     * Test that metric falls back to generic message when API body has no message
     */
    public function testGetRatioFallsBackToGenericMessageWhenNoBodyMessage()
    {
        $this->setupGetRatioMocks();

        // Mock API response with 500 and no message in body
        $response = new Response();
        $response->setStatus(500);
        $response->setData(null);

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($response);

        // Expect fallback message
        $datadogMock = Mockery::mock(Datadog::class);
        $datadogMock->shouldReceive('sendEvent')->byDefault();
        $datadogMock
            ->shouldReceive('sendEvent')
            ->once()
            ->with(
                'mp_currency_conversion_error',
                '500',
                'HTTP 500',
                null,
                Mockery::type('array')
            );

        $this->setNotAccessibleProperty($this->currency, 'datadog', $datadogMock);

        $this->expectException(Exception::class);
        $this->currency->getRatio($this->gateway);
    }

    /**
     * Test that no error metric is sent on successful API response
     */
    public function testGetRatioDoesNotSendErrorMetricOnSuccess()
    {
        $this->setupGetRatioMocks();

        // Mock successful API response
        $response = new Response();
        $response->setStatus(200);
        $response->setData(['ratio' => 5.25]);

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($response);

        // Datadog should receive the active metric (diff) but NOT the error metric
        $datadogMock = Mockery::mock(Datadog::class);
        $datadogMock
            ->shouldReceive('sendEvent')
            ->once()
            ->with('mp_currency_conversion_diff', '1', null, Mockery::type('string'), Mockery::type('array'));
        $datadogMock
            ->shouldNotReceive('sendEvent')
            ->with('mp_currency_conversion_error', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());

        $this->setNotAccessibleProperty($this->currency, 'datadog', $datadogMock);

        $result = $this->currency->getRatio($this->gateway);

        $this->assertEquals(5.25, $result);
    }

    /**
     * Test that same currency metric is sent when conversion enabled but currencies match
     */
    public function testGetRatioSendsSameMetricWhenCurrenciesMatch()
    {
        $this->gateway->id = 'woo-mercado-pago-custom';
        $this->gateway->shouldReceive('getPaymentMethodName')->andReturn('custom');

        $this->options
            ->shouldReceive('getGatewayOption')
            ->with($this->gateway, 'currency_conversion')
            ->andReturn('yes');

        // Same currency: BRL = BRL
        $this->country
            ->shouldReceive('getCountryConfigs')
            ->andReturn(['currency' => 'BRL', 'currency_symbol' => 'R$']);

        WP_Mock::userFunction('get_woocommerce_currency')
            ->andReturn('BRL');

        $this->seller
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->seller
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('12345');

        $this->store
            ->shouldReceive('isTestMode')
            ->andReturn(false);

        $this->cache
            ->shouldReceive('getCache')
            ->andReturn(null);

        $this->cache
            ->shouldReceive('setCache')
            ->once()
            ->with(Mockery::pattern('/metric_mp_currency_conversion_same/'), true, 86400);

        $datadogMock = Mockery::mock(Datadog::class);
        $datadogMock
            ->shouldReceive('sendEvent')
            ->once()
            ->with(
                'mp_currency_conversion_same',
                '1',
                null,
                'custom',
                Mockery::on(function ($details) {
                    return $details['site_id'] === 'MLB'
                        && $details['environment'] === 'prod'
                        && $details['cust_id'] === '12345'
                        && $details['from_currency'] === 'BRL';
                })
            );

        $this->setNotAccessibleProperty($this->currency, 'datadog', $datadogMock);

        $result = $this->currency->getRatio($this->gateway);

        $this->assertEquals(1, $result);
    }
}
