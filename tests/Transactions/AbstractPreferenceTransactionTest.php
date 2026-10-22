<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use Exception;
use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Core\Models\Preference;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\AbstractPreferenceTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;
use WC_Order;

class AbstractPreferenceTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = AbstractPreferenceTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|AbstractPreferenceTransaction
     */
    private $transaction;

    public function testConstructorInitializesArrayPayloadAndRunsBuilders(): void
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
            ->andReturn(['currency' => 'BRL']);
        $GLOBALS['mercadopago'] = $mercadopago;

        $transaction = new class ($gateway, $order) extends AbstractPreferenceTransaction {
            public int $commonCalls = 0;
            public int $payerCalls = 0;
            public int $backUrlCalls = 0;
            public int $autoReturnCalls = 0;
            public int $shipmentsCalls = 0;
            public int $itemsCalls = 0;
            public int $shippingCalls = 0;
            public int $feeCalls = 0;
            public int $additionalInfoCalls = 0;

            public function extendInternalMetadata(PaymentMetadata $internalMetadata): void
            {
            }

            public function setCommonTransaction(): void
            {
                $this->commonCalls++;
            }

            public function setPayerTransaction(): void
            {
                $this->payerCalls++;
            }

            public function setBackUrlsTransaction(): void
            {
                $this->backUrlCalls++;
            }

            public function setAutoReturnTransaction(): void
            {
                $this->autoReturnCalls++;
            }

            public function setShipmentsTransaction(array &$shipments): void
            {
                $this->shipmentsCalls++;
            }

            public function setItemsTransaction(array &$items): void
            {
                $this->itemsCalls++;
            }

            public function setShippingTransaction(array &$items): void
            {
                $this->shippingCalls++;
            }

            public function setFeeTransaction(array &$items): void
            {
                $this->feeCalls++;
            }

            public function setAdditionalInfoTransaction(): void
            {
                $this->additionalInfoCalls++;
            }

            public function getTransactionType(): string
            {
                return $this->transactionType;
            }
        };

        $this->assertSame('Preference', $transaction->getTransactionType());
        $this->assertSame([
            'additional_info' => [
                'items' => [],
                'shipments' => ['receiver_address' => []],
                'payer' => [
                    'phone' => [],
                    'mobile' => [],
                    'address' => [],
                    'identification' => [],
                ],
                'seller' => [],
            ],
            'back_urls' => [],
            'items' => [],
            'payer' => [
                'phone' => [],
                'address' => [],
            ],
            'payment_methods' => ['excluded_payment_methods' => []],
            'shipments' => ['receiver_address' => []],
        ], $transaction->transaction);
        $this->assertSame(1, $transaction->commonCalls);
        $this->assertSame(1, $transaction->payerCalls);
        $this->assertSame(1, $transaction->backUrlCalls);
        $this->assertSame(1, $transaction->autoReturnCalls);
        $this->assertSame(1, $transaction->shipmentsCalls);
        $this->assertSame(1, $transaction->itemsCalls);
        $this->assertSame(1, $transaction->shippingCalls);
        $this->assertSame(1, $transaction->feeCalls);
        $this->assertSame(1, $transaction->additionalInfoCalls);
    }

    public function testCreatePreference(): void
    {
        $this->transaction
            ->expects()
            ->logTransactionPayload();

        $context = new CoreRequestContext();
        $this->transaction->shouldAllowMockingProtectedMethods()
            ->expects()->getCoreRequestContext()->andReturn($context);
        $data = [
            'id' => 'preference-id',
            'payer' => ['email' => 'buyer@example.com'],
        ];
        $this->transaction->mercadopago->coreApi->preferences
            ->expects()
            ->create($this->transaction->transaction, $context)
            ->andReturn(Preference::fromArray($data));

        $this->transaction->mercadopago->logs->file = Mockery::mock(File::class)
            ->expects()
            ->info('Preference created', '', ['id' => 'preference-id'])
            ->getMock();

        $this->assertEquals($data, $this->transaction->createPreference());
    }

    public function testCreatePreferenceSendsApiErrorMetricAndRethrowsOnException(): void
    {
        $apiRoute  = '/v1/asgard/preferences';
        $exception = new Exception('API failure', 500);

        $this->transaction
            ->expects()
            ->logTransactionPayload();

        $context = new CoreRequestContext();
        $this->transaction->shouldAllowMockingProtectedMethods()
            ->expects()->getCoreRequestContext()->andReturn($context);
        $this->transaction->mercadopago->coreApi->preferences
            ->expects()
            ->create($this->transaction->transaction, $context)
            ->andThrow($exception);

        $this->transaction
            ->shouldAllowMockingProtectedMethods()
            ->expects()
            ->sendApiErrorMetric($apiRoute, $exception);

        $this->expectExceptionObject($exception);

        $this->transaction->createPreference();
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testSetAutoReturnTransaction(bool $autoReturn): void
    {
        $this->transaction->mercadopago->hooks->options
            ->expects()
            ->getGatewayOption($this->transaction->gateway, 'auto_return')
            ->andReturn($autoReturn ? 'yes' : 'no');

        $this->assertNull($this->transaction->setAutoReturnTransaction());
        $this->assertSame($autoReturn ? 'approved' : null, $this->transaction->transaction['auto_return'] ?? null);
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testSetBackUrlsTransaction(bool $mockOptions): void
    {
        $expected = [
            'success' => random()->url(),
            'failure' => random()->url(),
            'pending' => random()->url(),
        ];

        $order = $this->mockTransactionOrder();

        if ($mockOptions) {
            $this->transaction->mercadopago->hooks->options
                ->expects()->getGatewayOption($this->transaction->gateway, 'success_url')->andReturn($expected['success'])
                ->getMock()->expects()->getGatewayOption($this->transaction->gateway, 'failure_url')->andReturn($expected['failure'])
                ->getMock()->expects()->getGatewayOption($this->transaction->gateway, 'pending_url')->andReturn($expected['pending']);
        } else {
            $this->transaction->mercadopago->hooks->options
                ->expects()
                ->getGatewayOption($this->transaction->gateway, Mockery::type('string'))
                ->times(3)
                ->andReturn(null);
            $this->transaction->mercadopago->helpers->strings
                ->expects()
                ->fixUrlAmpersand(Mockery::type('string'))
                ->times(3)
                ->andReturnArg(0);
            WP_Mock::userFunction('esc_url')
                ->andReturnArg(0);
            $this->transaction->gateway
                ->expects()
                ->get_return_url($order)
                ->times(2)
                ->andReturn($expected['success']);
            $order
                ->expects()
                ->get_cancel_order_url()
                ->andReturn($expected['failure']);
            $expected['pending'] = $expected['success'];
        }

        $this->transaction->transaction['back_urls'] = [];
        $this->transaction->setBackUrlsTransaction();

        $this->assertSame($expected, $this->transaction->transaction['back_urls']);
    }

    public function testSetPayerTransaction(): void
    {
        $expected = $this->preferenceSetPayerTransactionMock();

        $this->transaction->setPayerTransaction();

        $this->assertEquals($expected, $this->transaction->transaction['payer']);
    }

    /**
     * @testWith [true, true]
     *           [false, false]
     *           [true, false]
     *           [false, true]
     */
    public function testSetCommonTransaction(bool $isTestMode, bool $isTestUser): void
    {
        $expected = [
            'binary_mode' => random()->boolean(),
            'external_reference' => random()->uuid(),
            'notification_url' => random()->url(),
            'metadata' => [],
            'statement_descriptor' => random()->company(),
        ];

        $this->setCommonTransactionMock($expected);

        $this->transaction->mercadopago->storeConfig
            ->expects()
            ->isTestMode()
            ->andReturn($isTestMode);

        $this->transaction->mercadopago->sellerConfig
            ->expects()
            ->isTestUser()
            ->andReturn($isTestUser);

        if (!$isTestMode && !$isTestUser) {
            $expected['sponsor_id'] = random()->numberBetween();
            $this->setNotAccessibleProperty($this->transaction, 'countryConfigs', Arrays::only($expected, 'sponsor_id'));
        }

        $this->transaction->transaction = [];

        $this->transaction->setCommonTransaction();

        $this->assertSame($expected, $this->transaction->transaction);
    }
}
