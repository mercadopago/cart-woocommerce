<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
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
    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();
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
}
