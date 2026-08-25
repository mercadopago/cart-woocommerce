<?php

namespace MercadoPago\Woocommerce\Tests\SuperToken;

use MercadoPago\PP\Sdk\Exceptions\ApiException;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\SuperToken\Contracts\SuperTokenMetadataWriter;
use MercadoPago\Woocommerce\SuperToken\Contracts\SuperTokenTransactionFactory;
use MercadoPago\Woocommerce\SuperToken\SuperTokenCheckout;
use MercadoPago\Woocommerce\SuperToken\SuperTokenPaymentProcessor;
use MercadoPago\Woocommerce\SuperToken\SuperTokenValidator;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Transactions\AbstractPaymentTransaction;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;

class SuperTokenPaymentProcessorTest extends TestCase
{
    private $validator;
    private $transactionFactory;
    private $metadataWriter;
    private SuperTokenPaymentProcessor $processor;
    private $gateway;
    private $order;

    protected function setUp(): void
    {
        $this->validator = Mockery::mock(SuperTokenValidator::class);
        $this->transactionFactory = Mockery::mock(SuperTokenTransactionFactory::class);
        $this->metadataWriter = Mockery::mock(SuperTokenMetadataWriter::class);

        $this->processor = new SuperTokenPaymentProcessor(
            $this->validator,
            $this->transactionFactory,
            $this->metadataWriter
        );

        $this->gateway = Mockery::mock(AbstractGateway::class);
        $this->gateway->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->gateway->shouldReceive('setPaymentMethodName')->byDefault();

        $this->order = Mockery::mock(WC_Order::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testProcessHappyPathRunsFullSequenceAndReturnsResponseStatusResult(): void
    {
        $response = ['status' => 'approved'];
        $metadata = Mockery::mock(PaymentMetadata::class);
        $transaction = Mockery::mock(AbstractPaymentTransaction::class);

        $this->gateway->shouldReceive('setPaymentMethodName')
            ->once()
            ->with('woo-mercado-pago-super-token');

        $this->validator->shouldReceive('getMissingFields')
            ->once()
            ->with(Mockery::type(SuperTokenCheckout::class))
            ->andReturn([]);

        $this->transactionFactory->shouldReceive('create')
            ->once()
            ->with($this->gateway, $this->order, Mockery::type('array'))
            ->andReturn($transaction);

        $transaction->shouldReceive('getCheckoutSessionData')->andReturn(['_mp_flow_id' => 'flow-x']);

        $this->validator->shouldReceive('reportTelemetry')
            ->once()
            ->with(Mockery::type(SuperTokenCheckout::class), $this->gateway, 'flow-x');

        $transaction->shouldReceive('createPayment')->once()->andReturn($response);
        $transaction->shouldReceive('getInternalMetadata')->andReturn($metadata);

        $this->metadataWriter->shouldReceive('write')
            ->once()
            ->with($this->order, $response, $metadata);

        $this->gateway->shouldReceive('handleResponseStatus')
            ->once()
            ->with($this->order, $response)
            ->andReturn($expected = ['result' => 'success', 'redirect' => 'https://store.test/received']);

        $result = $this->processor->process($this->gateway, $this->order, [
            'authorized_pseudotoken' => 'pseudo',
            'amount'                 => '100',
            'payment_method_id'      => 'visa',
            'payment_type_id'        => 'credit_card',
            'installments'           => '3',
        ]);

        $this->assertSame($expected, $result);
    }

    public function testProcessThrowsInvalidCheckoutDataWhenFieldsAreMissing(): void
    {
        $this->validator->shouldReceive('getMissingFields')
            ->once()
            ->andReturn(['authorized_pseudotoken']);

        $this->transactionFactory->shouldNotReceive('create');
        $this->metadataWriter->shouldNotReceive('write');

        try {
            $this->processor->process($this->gateway, $this->order, [
                'amount'          => '100',
                'payment_method_id' => 'visa',
                'payment_type_id' => 'credit_card',
            ]);
            $this->fail('Expected InvalidCheckoutDataException was not thrown');
        } catch (InvalidCheckoutDataException $e) {
            $this->assertSame('authorized_pseudotoken', $e->getDetails()['missing_fields']);
            $this->assertSame('credit_card', $e->getDetails()['payment_type_id']);
        }
    }

    public function testProcessRoutesApiExceptionToProcessReturnFail(): void
    {
        $apiException = new ApiException('It was not possible to validate the payment.', 'CPP_AT_0103004');
        $transaction = Mockery::mock(AbstractPaymentTransaction::class);

        $this->validator->shouldReceive('getMissingFields')->once()->andReturn([]);
        $this->transactionFactory->shouldReceive('create')->once()->andReturn($transaction);
        $transaction->shouldReceive('getCheckoutSessionData')->andReturn(['_mp_flow_id' => 'flow-x']);
        $this->validator->shouldReceive('reportTelemetry')->once();
        $transaction->shouldReceive('createPayment')->once()->andThrow($apiException);

        $this->gateway->mercadopago->helpers->errorMessages
            ->shouldReceive('findCodeInOriginalMessage')
            ->once()
            ->with(null)
            ->andReturn(null);

        $this->metadataWriter->shouldNotReceive('write');
        $this->gateway->shouldNotReceive('handleResponseStatus');

        $this->gateway->shouldReceive('processReturnFail')
            ->once()
            ->with($apiException, 'CPP_AT_0103004', Mockery::any(), [], true)
            ->andReturn($expected = ['result' => 'fail', 'redirect' => '']);

        $result = $this->processor->process($this->gateway, $this->order, [
            'authorized_pseudotoken' => 'pseudo',
            'amount'                 => '100',
            'payment_method_id'      => 'visa',
            'payment_type_id'        => 'credit_card',
            'installments'           => '3',
        ]);

        $this->assertSame($expected, $result);
    }

    public function testProcessLetsNonApiExceptionPropagate(): void
    {
        $exception = new \RuntimeException('unexpected failure');
        $transaction = Mockery::mock(AbstractPaymentTransaction::class);

        $this->validator->shouldReceive('getMissingFields')->once()->andReturn([]);
        $this->transactionFactory->shouldReceive('create')->once()->andReturn($transaction);
        $transaction->shouldReceive('getCheckoutSessionData')->andReturn(['_mp_flow_id' => 'flow-x']);
        $this->validator->shouldReceive('reportTelemetry')->once();
        $transaction->shouldReceive('createPayment')->once()->andThrow($exception);

        $this->metadataWriter->shouldNotReceive('write');
        // Is not calling because the exception will be treated from
        // AbstractGateway::processReturnFail() and will be propagated to the caller
        $this->gateway->shouldNotReceive('handleResponseStatus');
        $this->gateway->shouldNotReceive('processReturnFail');

        $this->expectExceptionObject($exception);

        $this->processor->process($this->gateway, $this->order, [
            'authorized_pseudotoken' => 'pseudo',
            'amount'                 => '100',
            'payment_method_id'      => 'visa',
            'payment_type_id'        => 'credit_card',
            'installments'           => '3',
        ]);
    }
}
