<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Transactions\BasicTransaction;
use MercadoPago\Woocommerce\Helpers;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AbstractGatewayTest extends TestCase
{
    private $sellerConfigMock;
    private $mercadopagoMock;
    private $adminTranslationsMock;
    private $gateway;

    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();

        $this->mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->sellerConfigMock = Mockery::mock(Seller::class);
        $this->adminTranslationsMock = Mockery::mock(AdminTranslations::class);
        $this->gateway = Mockery::mock(AbstractGateway::class)->makePartial();
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testProcessPayment()
    {
        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();

        $gateway = Mockery::mock(AbstractGateway::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $order = Mockery::mock('WC_Order');
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        $orderTotal = 100;
        $order->total = $orderTotal;

        $order->shouldReceive('get_total')
            ->andReturn($orderTotal);

        $cartHelper = Mockery::mock(Helpers::class);

        $discountValue = 10;
        $mercadopagoMock->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')
            ->once()
            ->with($gateway)
            ->andReturn($discountValue);

        $comissionValue = 1;
        $mercadopagoMock->helpers->cart->shouldReceive('calculateSubtotalWithCommission')
            ->once()
            ->with($gateway)
            ->andReturn($comissionValue);

        $productionMode = 'yes';

        $mercadopagoMock->storeConfig->shouldReceive('getProductionMode')
            ->once()
            ->andReturn($productionMode);

        $mercadopagoMock->orderMetadata->shouldReceive('setIsProductionModeData')
            ->once()
            ->with($order, $productionMode)
            ->andReturnSelf();

        $mercadopagoMock->orderMetadata->shouldReceive('setUsedGatewayData')
            ->once()
            ->with($order, '')
            ->andReturnSelf();

        $gateway->mercadopago = $mercadopagoMock;

        $gateway->discount = $discountValue;

        $text = 'discount of';
        $mercadopagoMock->storeTranslations->commonCheckout['discount_title'] = $text;

        $currencySymbol = '$';
        $mercadopagoMock->helpers->currency->shouldReceive('getCurrencySymbol')
            ->once()
            ->andReturn($currencySymbol);

        $mercadopagoMock->orderMetadata->shouldReceive('setDiscountData')
        ->once()
        ->with($order, 'discount of 9.09% = $ 10,00')
        ->andReturnSelf();

        $gateway->commission = $comissionValue;

        $text = 'fee of';
        $mercadopagoMock->storeTranslations->commonCheckout['fee_title'] = $text;

        $currencySymbol = '$';
        $mercadopagoMock->helpers->currency->shouldReceive('getCurrencySymbol')
            ->once()
            ->andReturn($currencySymbol);

        $mercadopagoMock->orderMetadata->shouldReceive('setCommissionData')
        ->once()
        ->with($order, "fee of 0.99% = $ 1,00")
        ->andReturnSelf();

        $result = $gateway->process_payment(1);
        $this->assertEquals($result, []);
        $this->assertIsArray($result);
    }

    public function testValidCredentialsReturnEmptyNotice()
    {

        $this->mercadopagoMock->sellerConfig = $this->sellerConfigMock;
        $this->mercadopagoMock->adminTranslations = $this->adminTranslationsMock;

        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
        ->once()
        ->andReturn(false);

        $this->gateway->id = 'test_gateway';
        $this->gateway->mercadopago = $this->mercadopagoMock;

        $result = $this->gateway->getCredentialExpiredNotice();
        $this->assertEquals(['type' => 'title', 'value' => ''], $result);
    }

    public function testReturnsNoticeForExpiredCredentialsNoCache()
    {
        $this->mercadopagoMock->sellerConfig = $this->sellerConfigMock;
        $this->mercadopagoMock->adminTranslations = $this->adminTranslationsMock;

        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validatePage')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validateSection')
        ->once()
        ->andReturn(true);

        WP_Mock::userFunction('get_transient')
        ->once()
        ->andReturn(false);

        $this->sellerConfigMock->shouldReceive('getCredentialsPublicKeyProd')
        ->once()
        ->andReturn('test_public_key');

        $this->sellerConfigMock->shouldReceive('isExpiredPublicKey')
            ->once()
            ->with('test_public_key')
            ->andReturn(true);

        WP_Mock::userFunction('set_transient')
            ->once()
            ->andReturn(true);

        $this->adminTranslationsMock->credentialsSettings = [
            'title_invalid_credentials' => 'Invalid Credentials',
            'subtitle_invalid_credentials' => 'Please update your credentials.',
            'button_invalid_credentials' => 'Update Credentials'
        ];

        $linksMock = [
            'admin_settings_page' => 'http://localhost.com/settings'
        ];

        $this->gateway->mercadopago = $this->mercadopagoMock;

        $reflection = new \ReflectionClass($this->gateway);
        $property = $reflection->getProperty('links');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $linksMock);

        $this->gateway->id = 'test_gateway';
        $result = $this->gateway->getCredentialExpiredNotice();

        $expected = [
            'type'  => 'mp_card_info',
            'value' => [
                'title'       => 'Invalid Credentials',
                'subtitle'    => 'Please update your credentials.',
                'button_text' => 'Update Credentials',
                'button_url'  => 'http://localhost.com/settings',
                'icon'        => 'mp-icon-badge-warning',
                'color_card'  => 'mp-alert-color-error',
                'size_card'   => 'mp-card-body-size',
                'target'      => '_blank',
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testReturnsNoticeForExpiredCredentialsWithCache()
    {
        $this->mercadopagoMock->sellerConfig = $this->sellerConfigMock;
        $this->mercadopagoMock->adminTranslations = $this->adminTranslationsMock;

        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validatePage')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validateSection')
        ->once()
        ->andReturn(true);

        $expected = [
            'type'  => 'mp_card_info',
            'value' => [
                'title'       => 'Invalid Credentials',
                'subtitle'    => 'Please update your credentials.',
                'button_text' => 'Update Credentials',
                'button_url'  => 'http://localhost.com/settings',
                'icon'        => 'mp-icon-badge-warning',
                'color_card'  => 'mp-alert-color-error',
                'size_card'   => 'mp-card-body-size',
                'target'      => '_blank',
            ]
        ];

        WP_Mock::userFunction('get_transient')
        ->once()
        ->andReturn($expected);

        $this->gateway->mercadopago = $this->mercadopagoMock;

        $this->gateway->id = 'test_gateway';
        $result = $this->gateway->getCredentialExpiredNotice();
        $this->assertEquals($expected, $result);
    }
}
