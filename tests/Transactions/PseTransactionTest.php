<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\PseTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use Mockery;
use WC_Order;

class PseTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = PseTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|PseTransaction
     */
    private $transaction;

    public function testConstructorInitializesPaymentPayloadAndPseFields(): void
    {
        $gateway = Mockery::mock(AbstractGateway::class);
        $order = Mockery::mock(WC_Order::class);
        $mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $mercadopago->helpers->currency
            ->shouldReceive('getRatio')
            ->once()
            ->with($gateway)
            ->andReturn(1.0);
        $mercadopago->helpers->country
            ->shouldReceive('getCountryConfigs')
            ->once()
            ->andReturn(['currency' => 'COP']);
        $GLOBALS['mercadopago'] = $mercadopago;

        $transaction = new class ($gateway, $order, ['bank' => '1001']) extends PseTransaction {
            public int $commonCalls = 0;
            public int $payerCalls = 0;
            public int $additionalInfoCalls = 0;
            public int $psePropertiesCalls = 0;

            public function setCommonTransaction(): void
            {
                $this->commonCalls++;
            }

            public function setPayerTransaction(): void
            {
                $this->payerCalls++;
            }

            public function setAdditionalInfoTransaction(): void
            {
                $this->additionalInfoCalls++;
            }

            public function setPsePropertiesTransaction(): void
            {
                $this->psePropertiesCalls++;
            }

            public function getTransactionType(): string
            {
                return $this->transactionType;
            }
        };

        $this->assertSame('Payment', $transaction->getTransactionType());
        $this->assertSame(PseTransaction::ID, $transaction->transaction['payment_method_id']);
        $this->assertSame(1, $transaction->transaction['installments']);
        $this->assertSame('', $transaction->transaction['description']);
        $this->assertSame(0.0, $transaction->transaction['transaction_amount']);
        $this->assertSame([], $transaction->transaction['payer']['address']);
        $this->assertSame([], $transaction->transaction['additional_info']['items']);
        $this->assertSame([], $transaction->transaction['point_of_interaction']['transaction_data']);
        $this->assertSame(1, $transaction->commonCalls);
        $this->assertSame(2, $transaction->payerCalls);
        $this->assertSame(1, $transaction->additionalInfoCalls);
        $this->assertSame(1, $transaction->psePropertiesCalls);
    }

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

        $this->transaction->transaction = [
            'transaction_details' => [],
            'payer' => ['phone' => [], 'address' => []],
        ];

        $this->transaction->setPsePropertiesTransaction();

        $this->assertEquals($expected, $this->transaction->transaction);
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

        $this->transaction->transaction['payer']['identification'] = [];

        $this->transaction->setPayerTransaction();

        $this->assertEquals($expected, $this->transaction->transaction['payer']);
    }
}
