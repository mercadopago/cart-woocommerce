<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\CreditsGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Transactions\BasicTransaction;
use Mockery;
use WP_Mock;

class CreditsGatewayTest extends TestCase
{
    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetCreditsGifPathMobileMLA()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $result = $gateway->getCreditsGifPath('MLA', 'mobile');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/fbc84a19-acb6-44be-9ad6-f7a6d974a8ce.gif', $result);
    }

    public function testGetCreditsGifPathMobileMLB()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $result = $gateway->getCreditsGifPath('MLB', 'mobile');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/a2ddea09-7982-44e0-861c-46e12eb5c3e3.gif', $result);
    }

    public function testGetCreditsGifPathDesktopMLA()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $result = $gateway->getCreditsGifPath('MLA', 'desktop');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/800e0db6-538c-493f-90b5-e2388af13387.gif', $result);
    }

    public function testGetCreditsGifPathDesktopMLB()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $result = $gateway->getCreditsGifPath('MLB', 'desktop');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/f428a107-17b0-4ce8-9867-5204e550ec12.gif', $result);
    }

    public function testGetCreditsGifPathDefault()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $result = $gateway->getCreditsGifPath('UNKNOWN', 'desktop');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/117d6be1-9f0a-466d-8d85-66f376d698cb.gif', $result);
    }
}