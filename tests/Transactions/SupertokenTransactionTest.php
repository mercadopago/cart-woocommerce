<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfo;
use MercadoPago\PP\Sdk\Entity\Payment\Item;
use MercadoPago\PP\Sdk\Entity\Payment\ItemList;
use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\Woocommerce\Helpers\Numbers;
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
 *
 * Related ticket: PSW-3542
 */
class SupertokenTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = SupertokenTransaction::class;

    /**
     * @var MockInterface|SupertokenTransaction
     */
    private $transaction;

    /**
     * @dataProvider consolidateItemsProvider
     */
    public function testConsolidateItemsMatchesTransactionAmount(array $items, float $expectedTotal): void
    {
        $orderId = 12345;

        // Setup transaction mock with additional_info and items
        $this->transaction->transaction = Mockery::mock(Payment::class)->makePartial();
        $this->transaction->transaction->additional_info = Mockery::mock(AdditionalInfo::class);
        $this->transaction->transaction->additional_info->items = Mockery::mock(ItemList::class);

        // Create mock items
        $mockItems = [];
        foreach ($items as $itemData) {
            $item = Mockery::mock(Item::class);
            $item->unit_price = $itemData['unit_price'];
            $item->quantity = $itemData['quantity'];
            $mockItems[] = $item;
        }

        $this->transaction->transaction->additional_info->items->collection = $mockItems;
        $this->transaction->transaction->transaction_amount = $expectedTotal;

        // Setup store config mock
        $this->transaction->mercadopago->storeConfig
            ->shouldReceive('getStoreCategory')
            ->with('others')
            ->andReturn('others');

        // Setup order mock
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $this->setNotAccessibleProperty($this->transaction, 'order', $order);

        // Call updateTransactionItems
        $this->transaction->updateTransactionItems();

        // Get the consolidated item
        $consolidatedItems = $this->transaction->transaction->additional_info->items->collection;

        // Assert there is exactly one consolidated item
        $this->assertCount(1, $consolidatedItems);

        // Assert the unit_price equals the transaction_amount (within floating point tolerance)
        $consolidatedItem = $consolidatedItems[0];
        $this->assertEqualsWithDelta(
            $this->transaction->transaction->transaction_amount,
            $consolidatedItem['unit_price'],
            0.01,
            'The consolidated item unit_price should match the transaction_amount'
        );

        // Assert the structure of the consolidated item
        $this->assertEquals($orderId, $consolidatedItem['id']);
        $this->assertEquals('Consolidated Items', $consolidatedItem['title']);
        $this->assertEquals('Consolidated Items', $consolidatedItem['description']);
        $this->assertEquals(1, $consolidatedItem['quantity']);
    }

    /**
     * @dataProvider consolidateItemsProvider
     */
    public function testConsolidateItemsCalculatesCorrectTotal(array $items, float $expectedTotal): void
    {
        $orderId = 12345;

        // Setup transaction mock with additional_info and items
        $this->transaction->transaction = Mockery::mock(Payment::class)->makePartial();
        $this->transaction->transaction->additional_info = Mockery::mock(AdditionalInfo::class);
        $this->transaction->transaction->additional_info->items = Mockery::mock(ItemList::class);

        // Create mock items
        $mockItems = [];
        foreach ($items as $itemData) {
            $item = Mockery::mock(Item::class);
            $item->unit_price = $itemData['unit_price'];
            $item->quantity = $itemData['quantity'];
            $mockItems[] = $item;
        }

        $this->transaction->transaction->additional_info->items->collection = $mockItems;
        $this->transaction->transaction->transaction_amount = $expectedTotal;

        // Setup store config mock
        $this->transaction->mercadopago->storeConfig
            ->shouldReceive('getStoreCategory')
            ->with('others')
            ->andReturn('others');

        // Setup order mock
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $this->setNotAccessibleProperty($this->transaction, 'order', $order);

        // Call updateTransactionItems
        $this->transaction->updateTransactionItems();

        // Get the consolidated item
        $consolidatedItems = $this->transaction->transaction->additional_info->items->collection;
        $consolidatedItem = $consolidatedItems[0];

        // Assert the calculated total is correct (within floating point tolerance)
        $this->assertEqualsWithDelta(
            $expectedTotal,
            $consolidatedItem['unit_price'],
            0.01,
            'The consolidated item unit_price should be approximately equal to the expected total'
        );
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

    /**
     * Test that floating point precision issues don't cause mismatch
     * This is the main test case for the bug fix (PSW-3542)
     */
    public function testConsolidateItemsHandlesFloatingPointPrecision(): void
    {
        $orderId = 12345;

        // Simulate items that could cause floating point precision issues
        // For example: 47.38 + 47.38 + 47.38 = 142.14 (but might be 142.13999999 in float)
        $items = [
            ['unit_price' => 47.38, 'quantity' => 1],
            ['unit_price' => 47.38, 'quantity' => 1],
            ['unit_price' => 47.38, 'quantity' => 1],
        ];
        $expectedTotal = 142.14;

        // Setup transaction mock
        $this->transaction->transaction = Mockery::mock(Payment::class)->makePartial();
        $this->transaction->transaction->additional_info = Mockery::mock(AdditionalInfo::class);
        $this->transaction->transaction->additional_info->items = Mockery::mock(ItemList::class);

        // Create mock items
        $mockItems = [];
        foreach ($items as $itemData) {
            $item = Mockery::mock(Item::class);
            $item->unit_price = $itemData['unit_price'];
            $item->quantity = $itemData['quantity'];
            $mockItems[] = $item;
        }

        $this->transaction->transaction->additional_info->items->collection = $mockItems;
        $this->transaction->transaction->transaction_amount = $expectedTotal;

        // Setup store config mock
        $this->transaction->mercadopago->storeConfig
            ->shouldReceive('getStoreCategory')
            ->with('others')
            ->andReturn('others');

        // Setup order mock
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $this->setNotAccessibleProperty($this->transaction, 'order', $order);

        // Call updateTransactionItems
        $this->transaction->updateTransactionItems();

        // Get the consolidated item
        $consolidatedItems = $this->transaction->transaction->additional_info->items->collection;
        $consolidatedItem = $consolidatedItems[0];

        // The key assertion: both values should be equal to avoid order_items_total_amount_mismatch error
        // Using assertEqualsWithDelta to account for floating point precision
        $this->assertEqualsWithDelta(
            $this->transaction->transaction->transaction_amount,
            $consolidatedItem['unit_price'],
            0.01,
            'unit_price and transaction_amount should be equal (within floating point tolerance) to avoid mismatch errors'
        );
    }

    /**
     * Test with items that have many decimal places (simulating the original bug from PSW-3542)
     */
    public function testConsolidateItemsWithManyDecimalPlaces(): void
    {
        $orderId = 12345;

        // Simulate items with values that have floating point representation issues
        // This was the actual bug: unit_price was "75428.994999999995343387126922607421875"
        $items = [
            ['unit_price' => '75428.994999999995343387126922607421875', 'quantity' => 1],
        ];

        // Setup transaction mock
        $this->transaction->transaction = Mockery::mock(Payment::class)->makePartial();
        $this->transaction->transaction->additional_info = Mockery::mock(AdditionalInfo::class);
        $this->transaction->transaction->additional_info->items = Mockery::mock(ItemList::class);

        // Create mock items
        $mockItems = [];
        foreach ($items as $itemData) {
            $item = Mockery::mock(Item::class);
            $item->unit_price = $itemData['unit_price'];
            $item->quantity = $itemData['quantity'];
            $mockItems[] = $item;
        }

        $this->transaction->transaction->additional_info->items->collection = $mockItems;
        $this->transaction->transaction->transaction_amount = 75429.00;

        // Setup store config mock
        $this->transaction->mercadopago->storeConfig
            ->shouldReceive('getStoreCategory')
            ->with('others')
            ->andReturn('others');

        // Setup order mock
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $this->setNotAccessibleProperty($this->transaction, 'order', $order);

        // Call updateTransactionItems
        $this->transaction->updateTransactionItems();

        // Get the consolidated item
        $consolidatedItems = $this->transaction->transaction->additional_info->items->collection;
        $consolidatedItem = $consolidatedItems[0];

        // Assert the unit_price is properly formatted (not with excessive decimals)
        $this->assertEqualsWithDelta(
            75429.00,
            $consolidatedItem['unit_price'],
            0.01,
            'unit_price should be properly rounded, not have excessive decimal places'
        );
    }

    /**
     * Test Numbers::format helper for rounding values
     */
    public function testNumbersFormatRoundsCorrectly(): void
    {
        // Test that Numbers::format properly rounds values
        $this->assertEquals(142.14, Numbers::format(142.139999999999));
        $this->assertEquals(142.14, Numbers::format(142.1399999));
        $this->assertEquals(75429.00, Numbers::format(75428.994999999995));
        $this->assertEquals(100.00, Numbers::format(99.999999999));
        $this->assertEquals(100.01, Numbers::format(100.005));
    }

    /**
     * Test Numbers::makesValueSafe helper for handling string values
     */
    public function testNumbersMakesValueSafeHandlesStrings(): void
    {
        // Test that Numbers::makesValueSafe properly converts string values
        $this->assertIsFloat(Numbers::makesValueSafe('75428.994999999995343387126922607421875'));
        $this->assertEqualsWithDelta(75428.99, Numbers::makesValueSafe('75428.994999999995343387126922607421875'), 0.01);
        $this->assertEquals(142.14, Numbers::makesValueSafe('142.14'));
        $this->assertEquals(100.0, Numbers::makesValueSafe('100'));
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
