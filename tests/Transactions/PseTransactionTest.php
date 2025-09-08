<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\PseTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PseTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = PseTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|PseTransaction
     */
    private $transaction;

    public function testExtendInternalMetadata(): void
    {
        $this->transaction->extendInternalMetadata(
            $metadata = new PaymentMetadata()
        );

        $this->assertObjectSchema([
            'checkout' => IsType::TYPE_STRING,
            'checkout_type' => $this->equalTo(PseTransaction::ID)
        ], $metadata);
    }

    public function testSetPsePropertiesTransaction(): void
    {
        $expected = [
            'callback_url' => random()->url(),
            'transaction_details' => [
                'financial_institution' => random()->bank()
            ],
            'payer' => [
                'entity_type' => random()->word(),
                'address' => [
                    'street_number' => random()->buildingNumber(),
                ],
            ]
        ];

        $order = $this->mockTransactionOrder();

        $order
            ->expects()
            ->get_checkout_order_received_url()
            ->andReturn($expected['callback_url']);

        $this->setNotAccessibleProperty($this->transaction, 'checkout', [
            'bank' => $expected['transaction_details']['financial_institution'],
            'person_type' => $expected['payer']['entity_type']
        ]);

        $this->transaction->mercadopago->orderBilling
            ->expects()
            ->getPhone($order)
            ->andReturn($phone = random()->phoneNumber());

        $phone = preg_replace('/[^\d]/', '', $phone);

        $expected['payer']['phone'] = [
            'area_code' => substr($phone, 0, 2),
            'number' => substr($phone, 2),
        ];

        $this->transaction->mercadopago->orderBilling
            ->expects()
            ->getFullAddress($order)
            ->andReturn('');

        $this->transaction->mercadopago->helpers->strings
            ->expects()
            ->getStreetNumberInFullAddress('', '00')
            ->andReturn($expected['payer']['address']['street_number']);

        $this->transaction->transaction = new stdClass();
        $this->transaction->transaction->transaction_details = new stdClass();
        $this->transaction->transaction->payer = new stdClass();
        $this->transaction->transaction->payer->phone = new stdClass();
        $this->transaction->transaction->payer->address = new stdClass();

        $this->transaction->setPsePropertiesTransaction();

        $this->assertObjectEqualsArray($expected, $this->transaction->transaction);
    }

    public function testSetPayerTransaction(): void
    {
        $expected = $this->paymentSetPayerTransactionMock([
            'identification' => [
                'type' => random()->word(),
                'number' => random()->numerify('#########')
            ]
        ]);

        $this->setNotAccessibleProperty($this->transaction, 'checkout', [
            'doc_type' => $expected['identification']['type'],
            'doc_number' => $expected['identification']['number']
        ]);

        $this->transaction->transaction->payer->identification = new stdClass();

        $this->transaction->setPayerTransaction();

        $this->assertObjectEqualsArray($expected, $this->transaction->transaction->payer);
    }
}
