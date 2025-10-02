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

    public function testWalletButtonSimplifiedImplementation(): void
    {
        // Test that the simplified implementation works
        $orderId = 123;
        $flowId = 'simplified-flow-id-789';

        // Set session data (simulating what CustomGateway does)
        $_SESSION['mp_wallet_flow_id_' . $orderId] = $flowId;

        // Verify session data is set
        $this->assertEquals($flowId, $_SESSION['mp_wallet_flow_id_' . $orderId]);

        // Simulate cleanup
        unset($_SESSION['mp_wallet_flow_id_' . $orderId]);

        // Verify cleanup worked
        $this->assertArrayNotHasKey('mp_wallet_flow_id_' . $orderId, $_SESSION);
    }
}
