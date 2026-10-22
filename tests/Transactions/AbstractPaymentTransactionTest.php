<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use Exception;
use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Core\Models\Payment;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\AbstractPaymentTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class AbstractPaymentTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = AbstractPaymentTransaction::class;

    // On PHP 8.2 the phpdoc type hint below can become a native union type.
    /**
     * @var MockInterface|AbstractPaymentTransaction
     */
    private $transaction;

    /**
     * @testWith [{"session_id": 1}]
     *           [{"session_id": null}]
     *           [[]]
     */
    public function testCreatePayment(array $checkout): void
    {
        $this->transaction
            ->expects()
            ->logTransactionPayload();

        if ($checkout) {
            $this->setNotAccessibleProperty($this->transaction, 'checkout', $checkout);
        }

        $apiRoute = '/v1/asgard/payments';

        $context = new CoreRequestContext();
        $this->transaction->shouldAllowMockingProtectedMethods()
            ->expects()->getCoreRequestContext()->andReturn($context);
        $data = [
            'id' => '123',
            'status' => 'approved',
            'status_detail' => 'accredited',
            'payment_method_id' => 'master',
            'payment_type_id' => 'credit_card',
            'payer' => ['email' => 'buyer@example.com'],
        ];
        $this->transaction->mercadopago->coreApi->payments
            ->expects()
            ->create($this->transaction->transaction, $context, isset($checkout['session_id']) ? (string) $checkout['session_id'] : null)
            ->andReturn(Payment::fromArray($data));

        $this->transaction->mercadopago->logs->file = Mockery::mock(File::class)
            ->expects()
            ->info('Payment created', '', [
                'id' => '123',
                'status' => 'approved',
                'status_detail' => 'accredited',
                'payment_method_id' => 'master',
                'payment_type_id' => 'credit_card',
            ])
            ->getMock();

        $this->transaction
            ->shouldAllowMockingProtectedMethods()
            ->expects()
            ->sendPaymentCreateResultMetric($apiRoute, null, $data);

        $this->assertEquals($data, $this->transaction->createPayment());
    }

    public function testCreatePaymentSendsApiErrorMetricAndRethrowsOnException(): void
    {
        $apiRoute  = '/v1/asgard/payments';
        $exception = new Exception('API failure', 500);

        $this->transaction
            ->expects()
            ->logTransactionPayload();

        $context = new CoreRequestContext();
        $this->transaction->shouldAllowMockingProtectedMethods()
            ->expects()->getCoreRequestContext()->andReturn($context);
        $this->transaction->mercadopago->coreApi->payments
            ->expects()
            ->create($this->transaction->transaction, $context, null)
            ->andThrow($exception);

        $this->transaction
            ->shouldAllowMockingProtectedMethods()
            ->expects()
            ->sendApiErrorMetric($apiRoute, $exception)
            ->getMock()
            ->expects()
            ->sendPaymentCreateResultMetric($apiRoute, $exception);

        $this->expectExceptionObject($exception);

        $this->transaction->createPayment();
    }

    public function testSetPayerTransaction(): void
    {
        $expected = $this->paymentSetPayerTransactionMock();

        $this->transaction->setPayerTransaction();

        $this->assertEquals($expected, $this->transaction->transaction['payer']);
    }
}
