<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Mocks\SdkMock;
use MercadoPago\Woocommerce\Transactions\BasicTransaction;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Gateways\BasicGateway;
use Mockery;

class BasicTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = BasicTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|BasicTransaction
     */
    private $transaction;

    public function testExtendInternalMetadata(): void
    {
        $this->transaction->mercadopago->hooks->options
            ->expects()
            ->getGatewayOption($this->transaction->gateway, 'method', 'redirect')
            ->andReturn($method = random()->word());

        $this->transaction->extendInternalMetadata(
            $metadata = new PaymentMetadata()
        );

        $this->assertObjectSchema([
            'checkout' => IsType::TYPE_STRING,
            'checkout_type' => $this->equalTo($method)
        ], $metadata);
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
