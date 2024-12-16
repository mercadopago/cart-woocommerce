<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Mocks\SdkMock;
use MercadoPago\Woocommerce\Transactions\BasicTransaction;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Gateways\BasicGateway;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BasicTransactionTest extends TestCase
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

    public function testGetInternalMetadata()
    {
        $expectedPaymentMetadata = new PaymentMetadata();
        $expectedPaymentMetadata->checkout = 'smart';
        $expectedPaymentMetadata->checkout_type = 'redirect';

        $basic = Mockery::mock(BasicTransaction::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $basic->shouldReceive('getInternalMetadataStoreAndSellerInfo')->andReturn(new PaymentMetadata());

        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $mercadopagoMock->hooks->options->shouldReceive('getGatewayOption')->andReturn('redirect');
        $basic->mercadopago = $mercadopagoMock;

        $basic->transaction = SdkMock::getPreferenceEntityMock();
        $basic->gateway = Mockery::mock(BasicGateway::class)->makePartial();

        $result = $basic->getInternalMetadata();
        $this->assertEquals($expectedPaymentMetadata, $result);
    }

    public function testSetInstallmentsTransaction()
    {
        $expectedInstallments = 6;
        $basic = Mockery::mock(BasicTransaction::class)->makePartial();

        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $mercadopagoMock
            ->hooks
            ->options
            ->shouldReceive('getGatewayOption')
            ->andReturn($expectedInstallments);
        $basic->mercadopago = $mercadopagoMock;

        $basic->transaction = SdkMock::getPreferenceEntityMock();
        $basic->gateway = Mockery::mock(BasicGateway::class)->makePartial();

        $basic->setInstallmentsTransaction();
        $this->assertEquals($expectedInstallments, $basic->transaction->payment_methods->installments);
    }

    public function testSetExcludedPaymentMethodsTransaction()
    {
        $basic = Mockery::mock(BasicTransaction::class)->makePartial();

        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $mercadopagoMock
            ->sellerConfig
            ->shouldReceive('getExPayments')
            ->andReturn(['visa', 'master', 'cabal']);
        $basic->mercadopago = $mercadopagoMock;

        $basic->transaction = SdkMock::getPreferenceEntityMock();
        $basic->transaction->payment_methods->excluded_payment_methods->shouldReceive('add')
            ->with(['id' => 'visa'])
            ->once();
        $basic->transaction->payment_methods->excluded_payment_methods->shouldReceive('add')
            ->with(['id' => 'master'])
            ->once();
        $basic->transaction->payment_methods->excluded_payment_methods->shouldReceive('add')
            ->with(['id' => 'cabal'])
            ->once();

        $basic->gateway = Mockery::mock(BasicGateway::class)->makePartial();
        $this->assertNull($basic->setExcludedPaymentMethodsTransaction());
    }

    public function testSetPaymentMethodsTransaction()
    {
        $basic = Mockery::mock(BasicTransaction::class)->makePartial();
        $basic->shouldReceive('setInstallmentsTransaction')->once()->andReturnNull();
        $basic->shouldReceive('setExcludedPaymentMethodsTransaction')->once()->andReturnNull();

        $this->assertNull($basic->setPaymentMethodsTransaction());
    }
}
