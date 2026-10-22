<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\YapeTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class YapeTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = YapeTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|YapeTransaction
     */
    private $transaction;

    public function testExtendInternalMetadata(): void
    {
        $this->transaction->extendInternalMetadata(
            $metadata = new PaymentMetadata()
        );

        $this->assertObjectSchema([
            'checkout' => IsType::TYPE_STRING,
            'checkout_type' => $this->equalTo(YapeTransaction::ID)
        ], $metadata);
    }

    /**
     * @testWith [{"token": "random"}]
     *           [[]]
     */
    public function testSetTokenTransaction(array $expected): void
    {
        $this->setNotAccessibleProperty($this->transaction, 'checkout', $expected);

        $this->transaction->transaction = [];

        $this->transaction->setTokenTransaction();

        $this->assertSame($expected, $this->transaction->transaction);
    }
}
