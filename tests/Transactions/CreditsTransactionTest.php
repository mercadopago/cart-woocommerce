<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Transactions\CreditsTransaction;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CreditsTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = CreditsTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|CreditsTransaction
     */
    private $transaction;

    public function testExtendInternalMetadata(): void
    {
        $this->transaction->extendInternalMetadata(
            $metadata = new PaymentMetadata()
        );

        $this->assertObjectSchema([
            'checkout' => IsType::TYPE_STRING,
            'checkout_type' => $this->equalTo(CreditsTransaction::ID)
        ], $metadata);
    }
}
