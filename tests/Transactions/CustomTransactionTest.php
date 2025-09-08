<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Transactions\CustomTransaction;
use stdClass;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CustomTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = CustomTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|CustomTransaction
     */
    private $transaction;

    public function testExtendInternalMetadata(): void
    {
        $this->transaction->extendInternalMetadata(
            $metadata = new PaymentMetadata()
        );

        $this->assertObjectSchema([
            'checkout' => IsType::TYPE_STRING,
            'checkout_type' => $this->equalTo(CustomTransaction::ID)
        ], $metadata);
    }

    /**
     * @dataProvider setTokenTransactionProvider
     */
    public function testSetTokenTransaction(array $checkout, array $expected = []): void
    {
        $expected = array_merge([
            'token' => null,
            'customer_id' => null,
            'issuer_id' => null,
        ], $checkout, $expected);

        $this->transaction->transaction = new stdClass();
        $this->transaction->transaction->payer = new stdClass();

        $this->setNotAccessibleProperty($this->transaction, 'checkout', $checkout);

        $this->transaction->setTokenTransaction();

        $this->assertEquals($expected['token'] ?? null, $this->transaction->transaction->token ?? null);
        $this->assertEquals($expected['customer_id'] ?? null, $this->transaction->transaction->payer->id ?? null);
        $this->assertEquals($expected['issuer'] ?? null, $this->transaction->transaction->issuer_id ?? null);
    }

    public function setTokenTransactionProvider()
    {
        return [
            [
                [
                    'token' => random()->uuid(),
                    'customer_id' => random()->uuid(),
                    'issuer_id' => random()->numberBetween(),
                ],
            ],
            [
                [
                    'token' => '',
                    'customer_id' => random()->uuid(),
                    'issuer_id' => random()->numberBetween(),
                ],
                [
                    'customer_id' => null,
                    'issuer_id' => null,
                ],
            ],
            [
                [
                    'customer_id' => random()->uuid(),
                    'issuer_id' => random()->numberBetween(),
                ],
                [
                    'token' => null,
                    'customer_id' => null,
                    'issuer_id' => null,
                ],
            ],
            [
                [
                    'token' => random()->uuid(),
                    'customer_id' => random()->uuid(),
                ],
            ],
            [
                [
                    'token' => random()->uuid(),
                    'issuer_id' => random()->numberBetween(),
                ],
            ],
        ];
    }
}
