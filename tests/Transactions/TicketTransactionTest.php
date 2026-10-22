<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\TicketTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TicketTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = TicketTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|TicketTransaction
     */
    private $transaction;

    /**
     * @testWith [""]
     *           ["random"]
     */
    public function testExtendInternalMetadata($paymentPlaceId): void
    {
        $this->setNotAccessibleProperty($this->transaction, 'checkout', $checkout = [
            'payment_method_id' => random()->creditCardType()
        ]);

        $this->transaction->mercadopago->helpers->paymentMethods
            ->expects()
            ->getPaymentPlaceId($checkout['payment_method_id'])
            ->andReturn($paymentPlaceId);

        $this->transaction->extendInternalMetadata(
            $metadata = new PaymentMetadata()
        );

        $expected = [
            'checkout' => IsType::TYPE_STRING,
            'checkout_type' => $this->equalTo(TicketTransaction::ID)
        ];

        if ($paymentPlaceId) {
            $expected['payment_option_id'] = $this->equalTo($paymentPlaceId);
        }

        $this->assertObjectSchema($expected, $metadata);
    }

    public function testSetWebpayPropertiesTransaction(): void
    {
        $this->setNotAccessibleProperty($this->transaction, 'checkout', [
            'payment_method_id' => random()->word()
        ]);

        $this->transaction->transaction = [];

        $this->transaction->setWebpayPropertiesTransaction();

        $this->assertSame([], $this->transaction->transaction);

        $this->setNotAccessibleProperty($this->transaction, 'checkout', [
            'payment_method_id' => 'webpay'
        ]);

        WP_Mock::userFunction('get_site_url')->andReturn($callback = random()->url());

        $this->transaction->transaction = [
            'transaction_details' => [],
            'additional_info' => [],
            'payer' => ['identification' => []],
        ];

        $this->transaction->setWebpayPropertiesTransaction();

        $this->assertSame([
            'transaction_details' => ['financial_institution' => '1234'],
            'additional_info' => ['ip_address' => '127.0.0.1'],
            'payer' => [
                'identification' => ['type' => 'RUT', 'number' => '0'],
                'entity_type' => 'individual',
            ],
            'callback_url' => $callback,
        ], $this->transaction->transaction);
    }

    public function testUpdatePayerTransactionMlb(): void
    {
        $this->setNotAccessibleProperty($this->transaction, 'countryConfigs', [
            'site_id' => 'MLB'
        ]);

        $this->setNotAccessibleProperty($this->transaction, 'checkout', $checkout = [
            'doc_type' => random()->word(),
            'doc_number' => random()->numerify('###########'),
            'address_city' => random()->city(),
            'address_federal_unit' => random()->stateAbbr(),
            'address_zip_code' => random()->postcode(),
            'address_street_name' => random()->streetName(),
            'address_neighborhood' => random()->word(),
            'address_street_number' => random()->optional()->randomNumber(4),
        ]);

        $this->transaction->transaction = [
            'payer' => ['identification' => [], 'address' => []],
            'additional_info' => ['payer' => ['address' => []]],
        ];

        $expectedAddress = [
            'city' => $checkout['address_city'],
            'federal_unit' => $checkout['address_federal_unit'],
            'zip_code' => $checkout['address_zip_code'],
            'street_name' => $checkout['address_street_name'],
            'neighborhood' => $checkout['address_neighborhood'],
            'street_number' => $checkout['address_street_number'] ?: 'S/N',
        ];

        $this->transaction->updatePayerTransaction();

        $this->assertSame([
            'identification' => [
                'type' => $checkout['doc_type'],
                'number' => $checkout['doc_number']
            ],
            'address' => $expectedAddress
        ], $this->transaction->transaction['payer']);

        $this->assertSame(
            $expectedAddress,
            $this->transaction->transaction['additional_info']['payer']['address']
        );
    }

    public function testUpdatePayerTransactionMlu(): void
    {
        $this->setNotAccessibleProperty($this->transaction, 'countryConfigs', [
            'site_id' => 'MLU'
        ]);

        $this->setNotAccessibleProperty($this->transaction, 'checkout', $checkout = [
            'doc_type' => random()->word(),
            'doc_number' => random()->numerify('###########'),
        ]);

        $this->transaction->transaction = ['payer' => ['identification' => []]];

        $this->transaction->updatePayerTransaction();

        $this->assertSame([
            'identification' => [
                'type' => $checkout['doc_type'],
                'number' => $checkout['doc_number']
            ],
        ], $this->transaction->transaction['payer']);
    }

    public function testUpdatePayerTransactionRola(): void
    {
        $this->setNotAccessibleProperty($this->transaction, 'countryConfigs', [
            'site_id' => strtoupper(random()->lexify('???'))
        ]);

        $this->transaction->updatePayerTransaction();

        $this->assertArrayNotHasKey('payer', $this->transaction->transaction);
    }
}
