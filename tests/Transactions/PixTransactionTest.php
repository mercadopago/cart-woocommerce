<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\PixTransaction;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PixTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = PixTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|PixTransaction
     */
    private $transaction;

    public function testExtendInternalMetadata(): void
    {
        $this->transaction->extendInternalMetadata(
            $metadata = new PaymentMetadata()
        );

        $this->assertObjectSchema([
            'checkout' => IsType::TYPE_STRING,
            'checkout_type' => $this->equalTo(PixTransaction::ID)
        ], $metadata);
    }

    /**
     * @doesNotPerformAssertions
     * @testWith ["1"]
     *           ["30 minutes"]
     */
    public function testGetExpirationDate(string $expirationDate): void
    {
        $this->transaction->gateway
            ->expects()
            ->getCheckoutExpirationDate()
            ->andReturn($expirationDate);

        Mockery::mock('alias:' . Date::class)
            ->expects()
            ->sumToNowDate($expirationDate . (strlen($expirationDate) === 1 ? ' days' : ''))
            ->andReturn('');

        $this->transaction->getExpirationDate();
    }
}
