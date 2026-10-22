<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Hooks\OrderMeta;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

class OrderMetaTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider singleValueProvider */
    public function testGetPreservesSingleFlagAndWooCommerceValue(bool $single): void
    {
        $value = $single ? 'first-attempt' : [new \WC_Meta_Data(['key' => '_fixture_attempt', 'value' => 'first-attempt'])];
        $arguments = null;
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()
            ->andReturnUsing(function ($key, $requestedSingle) use (&$arguments, $value) {
                $arguments = [$key, $requestedSingle];
                return $value;
            });

        $this->assertSame($value, (new OrderMeta())->get($order, '_fixture_attempt', $single));
        $this->assertSame(['_fixture_attempt', $single], $arguments);
    }

    public static function singleValueProvider(): array
    {
        return ['single value' => [true], 'multiple WC_Meta_Data objects' => [false]];
    }

    public function testGetDefaultsToSingleValue(): void
    {
        $arguments = null;
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()
            ->andReturnUsing(function ($key, $single) use (&$arguments) {
                $arguments = [$key, $single];
                return '';
            });

        $this->assertSame('', (new OrderMeta())->get($order, '_fixture_absent'));
        $this->assertSame(['_fixture_absent', true], $arguments);
    }

    /** @dataProvider uniqueValueProvider */
    public function testAddPreservesValueAndUniqueFlagWithoutSaving(bool $unique): void
    {
        $arguments = null;
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('add_meta_data')->once()
            ->andReturnUsing(function ($key, $value, $requestedUnique) use (&$arguments) {
                $arguments = [$key, $value, $requestedUnique];
            });
        $order->shouldNotReceive('save');

        (new OrderMeta())->add($order, '_fixture_attempt', ['id' => 'attempt-one'], $unique);

        $this->assertSame(['_fixture_attempt', ['id' => 'attempt-one'], $unique], $arguments);
    }

    public static function uniqueValueProvider(): array
    {
        return ['append allowed' => [false], 'unique entry' => [true]];
    }

    public function testAddDefaultsToNonUniqueMetadata(): void
    {
        $arguments = null;
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('add_meta_data')->once()
            ->andReturnUsing(function (...$args) use (&$arguments) {
                $arguments = $args;
            });
        $order->shouldNotReceive('save');

        (new OrderMeta())->add($order, '_fixture_attempt', 'attempt-one');

        $this->assertSame(['_fixture_attempt', 'attempt-one', false], $arguments);
    }

    /** @dataProvider metadataValueProvider */
    public function testUpdatePersistsAfterWritingTheUnchangedMetadataValue($value): void
    {
        $calls = [];
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('update_meta_data')->once()
            ->andReturnUsing(function ($key, $updatedValue) use (&$calls) {
                $calls[] = ['update', $key, $updatedValue];
            });
        $order->shouldReceive('save')
            ->andReturnUsing(function () use (&$calls) {
                $calls[] = ['save'];
                return 12;
            });

        (new OrderMeta())->update($order, '_fixture_state', $value);

        $this->assertSame([['update', '_fixture_state', $value], ['save']], $calls);
    }

    public static function metadataValueProvider(): array
    {
        return ['string' => ['approved'], 'array' => [['attempt' => 'one', 'status' => 'pending']], 'empty string' => ['']];
    }
}
