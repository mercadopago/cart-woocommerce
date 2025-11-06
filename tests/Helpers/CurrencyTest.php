<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\Notices;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
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
    private $logs;
    private $notices;
    private $requester;
    private $seller;
    private $options;
    private $url;

    public function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        // Mock dependencies
        $this->adminTranslations = Mockery::mock(AdminTranslations::class);
        $this->adminTranslations->currency = [];
        
        $this->cache = Mockery::mock(Cache::class);
        $this->country = Mockery::mock(Country::class);
        $this->logs = Mockery::mock(Logs::class);
        $this->notices = Mockery::mock(Notices::class);
        $this->requester = Mockery::mock(Requester::class);
        $this->seller = Mockery::mock(Seller::class)->makePartial();
        $this->options = Mockery::mock(Options::class);
        $this->url = Mockery::mock(Url::class);

        // Create Currency instance
        $this->currency = new Currency(
            $this->adminTranslations,
            $this->cache,
            $this->country,
            $this->logs,
            $this->notices,
            $this->requester,
            $this->seller,
            $this->options,
            $this->url
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

}

