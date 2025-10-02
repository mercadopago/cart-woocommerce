<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\Helpers\Url;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class PaymentMethodsTest extends TestCase
{
    private PaymentMethods $paymentMethods;
    private $urlMock;

    public function setUp(): void
    {
        WP_Mock::setUp();

        $this->urlMock = Mockery::mock(Url::class);
        $this->paymentMethods = new PaymentMethods($this->urlMock);
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testGetEnabledPaymentMethodsReturnsCachedResult()
    {
        $expectedPaymentMethods = ['woo-mercado-pago-basic', 'woo-mercado-pago-custom'];

        $reflection = new \ReflectionClass($this->paymentMethods);
        $property = $reflection->getProperty('enabledPaymentMethods');
        $property->setAccessible(true);
        $property->setValue($this->paymentMethods, $expectedPaymentMethods);

        $result = $this->paymentMethods->getEnabledPaymentMethods();

        $this->assertEquals($expectedPaymentMethods, $result);
    }

    public function testGetEnabledPaymentMethodsReturnsEmptyArrayWhenWCNotExists()
    {
        $result = $this->paymentMethods->getEnabledPaymentMethods();

        $this->assertEquals([], $result);
    }

    public function testGetEnabledPaymentMethodsReturnsEmptyArrayWhenPaymentGatewaysMethodNotExists()
    {
        $wcMock = Mockery::mock('WC');
        $paymentGatewaysMock = Mockery::mock('WC_Payment_Gateways');

        WP_Mock::userFunction('WC')
            ->once()
            ->andReturn($wcMock);

        $wcMock->shouldReceive('payment_gateways')
            ->once()
            ->andReturn($paymentGatewaysMock);

        $result = $this->paymentMethods->getEnabledPaymentMethods();

        $this->assertEquals([], $result);
    }

    public function testGenerateIdFromPlace()
    {
        $result = $this->paymentMethods->generateIdFromPlace('visa', 'atm');

        $this->assertEquals('visa_atm', $result);
    }

    public function testGetPaymentMethodId()
    {
        $result = $this->paymentMethods->getPaymentMethodId('visa_atm');

        $this->assertEquals('visa', $result);
    }

    public function testGetPaymentPlaceId()
    {
        $result = $this->paymentMethods->getPaymentPlaceId('visa_atm');

        $this->assertEquals('atm', $result);
    }

    public function testGetPaymentPlaceIdWithoutPlaceId()
    {
        $result = $this->paymentMethods->getPaymentPlaceId('visa');

        $this->assertEquals('', $result);
    }
}
