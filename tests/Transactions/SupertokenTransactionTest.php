<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use Exception;
use MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfo;
use MercadoPago\PP\Sdk\Entity\Payment\Item;
use MercadoPago\PP\Sdk\Entity\Payment\ItemList;
use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Transactions\SupertokenTransaction;

/**
 * Tests for SupertokenTransaction class
 *
 * These tests ensure that the consolidated items unit_price matches the transaction_amount
 * to avoid the "order_items_total_amount_mismatch" error from the API.
 */
class SupertokenTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = SupertokenTransaction::class;



    /**
     * @dataProvider consolidateItemsProvider
     */
    public function testConsolidateItemsMatchesTransactionAmount(array $items, float $expectedTotal): void
    {
        $orderId = 12345;

        $this->transaction->transaction = Mockery::mock(Payment::class)->makePartial();
        $this->transaction->transaction->additional_info = Mockery::mock(AdditionalInfo::class);
        $this->transaction->transaction->additional_info->items = Mockery::mock(ItemList::class);

        $mockItems = [];
        foreach ($items as $itemData) {
            $item             = Mockery::mock(Item::class);
            $item->unit_price = $itemData['unit_price'];
            $item->quantity   = $itemData['quantity'];
            $mockItems[]      = $item;
        }

        $this->transaction->transaction->additional_info->items->collection = $mockItems;
        $this->transaction->transaction->transaction_amount                 = $expectedTotal;

        $this->transaction->mercadopago->storeConfig
            ->shouldReceive('getStoreCategory')
            ->with('others')
            ->andReturn('others');

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $this->setNotAccessibleProperty($this->transaction, 'order', $order);

        $this->transaction->updateTransactionItems();

        $consolidatedItems = $this->transaction->transaction->additional_info->items->collection;
        $consolidatedItem  = $consolidatedItems[0];

        $this->assertCount(1, $consolidatedItems);
        $this->assertEqualsWithDelta($expectedTotal, $consolidatedItem['unit_price'], 0.01);
        $this->assertEquals($orderId, $consolidatedItem['id']);
        $this->assertEquals('Consolidated Items', $consolidatedItem['title']);
        $this->assertEquals('Consolidated Items', $consolidatedItem['description']);
        $this->assertEquals(1, $consolidatedItem['quantity']);
    }

    public function testConsolidateItemsWithEmptyItems(): void
    {
        // Setup transaction mock with additional_info and empty items
        $this->transaction->transaction = Mockery::mock(Payment::class)->makePartial();
        $this->transaction->transaction->additional_info = Mockery::mock(AdditionalInfo::class);
        $this->transaction->transaction->additional_info->items = Mockery::mock(ItemList::class);
        $this->transaction->transaction->additional_info->items->collection = [];

        // Setup order mock
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(12345);
        $this->setNotAccessibleProperty($this->transaction, 'order', $order);

        // Call updateTransactionItems
        $this->transaction->updateTransactionItems();

        // Assert empty array is returned for empty items
        $this->assertEmpty($this->transaction->transaction->additional_info->items->collection);
    }





    public function testCreatePaymentHappyPath(): void
    {
        $superToken    = random()->uuid();
        $paymentTypeId = 'credit_card';

        $this->setPrivateSupertokenProperties($superToken, $paymentTypeId);

        $apiRoute = '/v1/asgard/payments';

        $this->transaction
            ->expects()
            ->updateTransactionItems();

        $this->transaction->transaction
            ->expects()
            ->getUris()
            ->andReturn(['post' => $apiRoute]);

        $this->transaction->transaction
            ->expects()
            ->saveWithSuperToken($superToken, $paymentTypeId)
            ->andReturn($data = [
                'random' => random()->word(),
            ]);

        $this->transaction->mercadopago->logs->file = Mockery::mock(File::class)
            ->expects()
            ->info('Payment created', '', $data)
            ->getMock();

        $this->transaction
            ->shouldAllowMockingProtectedMethods()
            ->expects()
            ->sendPaymentCreateResultMetric($apiRoute, null, $data);

        $this->assertEquals($data, $this->transaction->createPayment());
    }

    public function testCreatePaymentSendsApiErrorMetricAndRethrowsOnException(): void
    {
        $superToken    = random()->uuid();
        $paymentTypeId = 'credit_card';
        $apiRoute      = '/v1/asgard/payments';
        $exception     = new Exception('API failure', 500);

        $this->setPrivateSupertokenProperties($superToken, $paymentTypeId);

        $this->transaction
            ->expects()
            ->updateTransactionItems();

        $this->transaction->transaction
            ->expects()
            ->saveWithSuperToken($superToken, $paymentTypeId)
            ->andThrow($exception);

        $this->transaction->transaction
            ->expects()
            ->getUris()
            ->andReturn(['post' => $apiRoute]);

        $this->transaction
            ->shouldAllowMockingProtectedMethods()
            ->expects()
            ->sendApiErrorMetric($apiRoute, $exception)
            ->getMock()
            ->expects()
            ->sendPaymentCreateResultMetric($apiRoute, $exception);

        $this->expectExceptionObject($exception);

        $this->transaction->createPayment();
    }


    public function testPayloadBaselineSnapshotForCreditCard(): void
    {
        $checkout = [
            'payment_method_id'      => 'master',
            'payment_type_id'        => 'credit_card',
            'installments'           => '3',
            'authorized_pseudotoken' => 'BASELINE-SUPER-TOKEN-4265',
        ];

        $order = $this->buildBaselineOrder(4265, 100.0);
        $payload = $this->buildSupertokenPayload($checkout, $order);

        $this->assertMatchesPayloadSnapshot($payload, 'supertoken-credit-card');
    }

    public function testPayloadBaselineSnapshotForDebitCard(): void
    {
        $checkout = [
            'payment_method_id'      => 'debvisa',
            'payment_type_id'        => 'debit_card',
            'authorized_pseudotoken' => 'BASELINE-SUPER-TOKEN-4267',
        ];

        $order = $this->buildBaselineOrder(4267, 100.0);
        $payload = $this->buildSupertokenPayload($checkout, $order);

        $this->assertMatchesPayloadSnapshot($payload, 'supertoken-debit-card');
    }

    /**
     * @return MockInterface|\WC_Order
     */
    private function buildBaselineOrder(int $orderId, float $total): MockInterface
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        // WC items/fees/shipping return empty so setAdditionalInfoItemsTransaction doesn't fail;
        // updateTransactionItems() overwrites the collection with the consolidated item anyway.
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('get_fees')->andReturn([]);
        $order->shouldReceive('get_shipping_total')->andReturn('0');
        $order->shouldReceive('get_shipping_tax')->andReturn('0');
        return $order;
    }

    private function buildSupertokenPayload(array $checkout, MockInterface $order): array
    {
        $this->transaction->transaction = (new \MercadoPago\PP\Sdk\Sdk())->getPaymentInstance();

        $this->setNotAccessibleProperty($this->transaction, 'ratio', 1.0);
        $this->setNotAccessibleProperty($this->transaction, 'countryConfigs', ['currency' => 'BRL', 'sponsor_id' => '12345']);
        $this->setNotAccessibleProperty($this->transaction, 'orderTotal', 100.0);
        $this->setNotAccessibleProperty($this->transaction, 'listOfItems', ['Baseline Product x 1']);
        $this->setPrivateSupertokenProperties($checkout['authorized_pseudotoken'], $checkout['payment_type_id']);
        $this->setNotAccessibleProperty($this->transaction, 'order', $order);

        $mp = $this->transaction->mercadopago;
        $mp->hooks->options->shouldReceive('getGatewayOption')->andReturn('no');
        $mp->storeConfig->shouldReceive('getStoreId')->andReturn('WOOTEST');
        $mp->storeConfig->shouldReceive('getStoreName')->andReturn('Baseline Store');
        $mp->storeConfig->shouldReceive('getStoreCategory')->andReturn('others');
        $mp->sellerConfig->shouldReceive('getClientId')->andReturn('CLIENT-ID-4265');
        $mp->helpers->url->shouldReceive('getServerAddress')->andReturn('127.0.0.1');
        $mp->helpers->url->shouldReceive('getBaseUrl')->andReturn('https://baseline.example');
        $mp->helpers->currentUser->shouldReceive('isUserLoggedIn')->andReturn(false);
        $mp->orderBilling->shouldReceive('getEmail')->andReturn('buyer@baseline.example');
        $mp->orderBilling->shouldReceive('getFirstName')->andReturn('Baseline');
        $mp->orderBilling->shouldReceive('getLastName')->andReturn('Buyer');
        $mp->orderBilling->shouldReceive('getCity')->andReturn('São Paulo');
        $mp->orderBilling->shouldReceive('getState')->andReturn('SP');
        $mp->orderBilling->shouldReceive('getCountry')->andReturn('BR');
        $mp->orderBilling->shouldReceive('getZipcode')->andReturn('01310100');
        $mp->orderBilling->shouldReceive('getFullAddress')->andReturn('Av. Paulista 1000');
        $mp->orderBilling->shouldReceive('getAddress1')->andReturn('Av. Paulista');
        $mp->orderBilling->shouldReceive('getAddress2')->andReturn('1000');
        $mp->orderBilling->shouldReceive('getPhone')->andReturn('11999999999');
        $mp->orderShipping->shouldReceive('getAddress1')->andReturn('Av. Paulista');
        $mp->orderShipping->shouldReceive('getAddress2')->andReturn('1000');
        $mp->orderShipping->shouldReceive('getZipcode')->andReturn('01310100');
        $mp->orderShipping->shouldReceive('getCity')->andReturn('São Paulo');
        $mp->orderShipping->shouldReceive('getState')->andReturn('SP');
        $mp->orderShipping->shouldReceive('getCountry')->andReturn('BR');

        $metadata = $this->buildBaselineMetadata();
        $this->transaction->extendInternalMetadata($metadata);
        $this->transaction->shouldReceive('getInternalMetadata')->andReturn($metadata);
        // getNotificationUrl reads site_url from WP globals — stub so the snapshot stays stable.
        $this->transaction->shouldAllowMockingProtectedMethods()
            ->shouldReceive('getNotificationUrl')->andReturn('https://baseline.example/wc-api/webhook');

        $this->transaction->setCommonTransaction();
        $this->transaction->setPayerTransaction();
        $this->transaction->setAdditionalInfoTransaction();

        $this->transaction->transaction->description        = 'Baseline Product x 1';
        $this->transaction->transaction->transaction_amount = Numbers::format(100.0);
        $this->transaction->transaction->payment_method_id  = $checkout['payment_method_id'];
        if (isset($checkout['installments'])) {
            $this->transaction->transaction->installments = (int) $checkout['installments'];
        }

        $item             = Mockery::mock(Item::class);
        $item->unit_price = 100.0;
        $item->quantity   = 1;
        $this->transaction->transaction->additional_info->items->collection = [$item];

        $this->transaction->updateTransactionItems();

        return json_decode(json_encode($this->transaction->transaction->toArray()), true);
    }

    private function buildBaselineMetadata(): \MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata
    {
        $metadata                              = new \MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata();
        $metadata->platform                    = 'WOOCOMMERCE_MP_TEST';
        $metadata->platform_version            = '9.9.9';
        $metadata->module_version              = '8.0.0';
        $metadata->php_version                 = '8.1.0';
        $metadata->site_id                     = 'mlb';
        $metadata->sponsor_id                  = '12345';
        $metadata->collector                   = '67890';
        $metadata->test_mode                   = '1';
        $metadata->details                     = '';
        $metadata->settings                    = [];
        $metadata->seller_website              = 'https://baseline.example';
        $metadata->blocks_payment              = 'no';
        $metadata->auto_update                 = false;
        $metadata->flow_id                     = null;
        $metadata->billing_address             = new \MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataAddress();
        $metadata->billing_address->zip_code   = '01310100';
        $metadata->billing_address->street_name = 'Av. Paulista';
        $metadata->billing_address->city_name  = 'São Paulo';
        $metadata->billing_address->state_name = 'SP';
        $metadata->billing_address->country_name = 'BR';
        $metadata->user                        = new \MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataUser();
        $metadata->user->registered_user       = 'no';
        $metadata->user->user_email            = null;
        $metadata->user->user_registration_date = null;
        $metadata->cpp_extra                   = new \MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataCpp();
        $metadata->cpp_extra->platform_version = '9.9.9';
        $metadata->cpp_extra->module_version   = '8.0.0';
        $metadata->theme                       = new \MercadoPago\Woocommerce\Entities\Metadata\ThemeMetadata();
        $metadata->theme->theme_name           = 'baseline-theme';
        $metadata->theme->theme_version        = '1.0.0';
        return $metadata;
    }

    private function assertMatchesPayloadSnapshot(array $payload, string $name): void
    {
        $dir  = __DIR__ . '/__snapshots__';
        $file = "{$dir}/{$name}.json";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $actual = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        if (!file_exists($file) || getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($file, $actual);
            $this->addToAssertionCount(1);
            return;
        }

        $this->assertJsonStringEqualsJsonString(
            file_get_contents($file),
            $actual,
            "Payload does not match snapshot. Run with UPDATE_SNAPSHOTS=1 to update {$name}.json."
        );
    }

    private function setPrivateSupertokenProperties(string $superToken, string $paymentTypeId): void
    {
        foreach (['superToken' => $superToken, 'paymentTypeId' => $paymentTypeId] as $name => $value) {
            $property = new \ReflectionProperty(SupertokenTransaction::class, $name);
            $property->setAccessible(true);
            $property->setValue($this->transaction, $value);
        }
    }

    public function consolidateItemsProvider(): array
    {
        return [
            'single item' => [
                [
                    ['unit_price' => 100.00, 'quantity' => 1],
                ],
                100.00,
            ],
            'multiple items same price' => [
                [
                    ['unit_price' => 50.00, 'quantity' => 1],
                    ['unit_price' => 50.00, 'quantity' => 1],
                ],
                100.00,
            ],
            'items with quantities' => [
                [
                    ['unit_price' => 25.00, 'quantity' => 2],
                    ['unit_price' => 50.00, 'quantity' => 1],
                ],
                100.00,
            ],
            'items with decimals' => [
                [
                    ['unit_price' => 33.33, 'quantity' => 1],
                    ['unit_price' => 33.33, 'quantity' => 1],
                    ['unit_price' => 33.34, 'quantity' => 1],
                ],
                100.00,
            ],
            'items causing floating point issues' => [
                [
                    ['unit_price' => 47.38, 'quantity' => 1],
                    ['unit_price' => 47.38, 'quantity' => 1],
                    ['unit_price' => 47.38, 'quantity' => 1],
                ],
                142.14,
            ],
            'single high value item' => [
                [
                    ['unit_price' => 75428.99, 'quantity' => 1],
                ],
                75428.99,
            ],
            'item with discount (negative would be handled separately)' => [
                [
                    ['unit_price' => 150.00, 'quantity' => 1],
                    ['unit_price' => -50.00, 'quantity' => 1],
                ],
                100.00,
            ],
            "item with many decimal places" => [
                [
                    ['unit_price' => 75428.994999999995343387126922607421875, 'quantity' => 1],
                ],
                75429.00,
            ],
        ];
    }
}
