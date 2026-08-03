<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use Exception;
use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\AbstractPaymentTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Mockery;
use stdClass;

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

        $this->transaction->transaction
            ->expects()
            ->getUris()
            ->andReturn(['post' => $apiRoute]);

        $this->transaction->transaction
            ->expects()
            ->save()
            ->andReturn($data = [
                'random' => random()->word()
            ]);

        $this->transaction->mercadopago->logs->file = Mockery::mock(File::class)
            ->expects()
            ->info('Payment created', '', $data)
            ->getMock();

        $this->transaction
            ->shouldAllowMockingProtectedMethods()
            ->expects()
            ->sendPaymentCreateResultMetric($apiRoute, null, $data);

        $this->assertEquals($data, $this->transaction->createPayment());
        $this->assertEquals($checkout['session_id'] ?? null, $this->transaction->transaction->session_id);
    }

    public function testCreatePaymentSendsApiErrorMetricAndRethrowsOnException(): void
    {
        $apiRoute  = '/v1/asgard/payments';
        $exception = new Exception('API failure', 500);

        $this->transaction
            ->expects()
            ->logTransactionPayload();

        $this->transaction->transaction
            ->expects()
            ->save()
            ->andThrow($exception);

        $this->transaction->transaction
            ->expects()
            ->getUris()
            ->andReturn(['post' => $apiRoute]);

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

        $this->assertObjectEqualsArray($expected, $this->transaction->transaction->payer);
    }
}
