<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Transactions\WalletButtonTransaction;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WalletButtonTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = WalletButtonTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|WalletButtonTransaction
     */
    private $transaction;

    public function testExtendInternalMetadata(): void
    {
        $this->transaction->extendInternalMetadata(
            $metadata = new PaymentMetadata()
        );

        $this->assertObjectSchema([
            'checkout' => IsType::TYPE_STRING,
            'checkout_type' => $this->equalTo(WalletButtonTransaction::ID)
        ], $metadata);
    }
}
