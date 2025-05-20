<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\CreditsGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
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
        $result = $gateway->getCreditsGifMobilePath('MLA');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/a91b365a-73dc-461a-9f3f-f8b3329ae5d2.gif', $result);
    }

    public function testGetCreditsGifPathMobileMLB()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $result = $gateway->getCreditsGifMobilePath('MLB');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/8bcbd873-6ec3-45eb-bccf-47bdcd9af255.gif', $result);
    }

    public function testGetCreditsGifPathDesktopMLA()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $result = $gateway->getCreditsGifDesktopPath('MLA');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/e6af4c4b-bede-4a6a-8711-b3d19fe423e3.gif', $result);
    }

    public function testGetCreditsGifPathDesktopMLB()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $result = $gateway->getCreditsGifDesktopPath('MLB');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/8afbe775-e8c3-4fa1-b013-ab7f079872b7.gif', $result);
    }

    public function testGetCreditsGifPathDefault()
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $result = $gateway->getCreditsGifDesktopPath('UNKNOWN');
        $this->assertEquals('https://http2.mlstatic.com/storage/cpp/static-files/e6af4c4b-bede-4a6a-8711-b3d19fe423e3.gif', $result);
    }

    public function testGetCheckoutName(): void
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->assertSame($gateway->getCheckoutName(), 'checkout-credits');
    }

    public function testValidateFields(): void
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->assertSame($gateway->validate_fields(), true);
    }

    public function testGetSiteId(): void
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $gateway->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('test');

        $this->assertEquals($gateway->getSiteId(), 'TEST');
    }

    public function testGenerateMpCreditsCheckoutExampleHtml(): void
    {
        $gateway = Mockery::mock(CreditsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $gateway
            ->shouldReceive('get_field_key')
            ->andReturn('test');

        defined('MP_PLUGIN_FILE') || define('MP_PLUGIN_FILE', dirname(__DIR__) . 'woocommerce-mercadopago.php');

        $result = 'test';

        WP_Mock::userFunction('wc_get_template_html')->andReturn($result);

        $this->assertEquals($gateway->generate_mp_credits_checkout_example_html('key', []), $result);
    }
}
