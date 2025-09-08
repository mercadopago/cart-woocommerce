<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

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

    // TODO(PHP8.2): Change type hint from phpdoc to native
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

        $this->assertEquals($data, $this->transaction->createPayment());
        $this->assertEquals($checkout['session_id'] ?? null, $this->transaction->transaction->session_id);
    }

    public function testSetPayerTransaction(): void
    {
        $expected = $this->paymentSetPayerTransactionMock();

        $this->transaction->setPayerTransaction();

        $this->assertObjectEqualsArray($expected, $this->transaction->transaction->payer);
    }
}
