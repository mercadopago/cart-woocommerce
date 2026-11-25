<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MercadoPago\Woocommerce\Order\FreshOrderReader;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Contract of the reload sequence. That the reload really defeats WooCommerce caches is proven
 * against a real WooCommerce (legacy + HPOS) in the validation run, not by these doubles.
 */
class FreshOrderReaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        // Another test can leave these functions defined by WP_Mock, so the reader's
        // function_exists() guards would otherwise depend on test order.
        WP_Mock::userFunction('clean_post_cache')->andReturn(null);
        WP_Mock::userFunction('wp_cache_delete')->andReturn(true);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    private function orderWith(object $store, int $id = 28)
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn($id);
        $order->shouldReceive('get_data_store')->andReturn($store);

        return $order;
    }

    public function testRefreshFoldsPendingChangesThenRereadsFromTheDataStoreAndMeta(): void
    {
        $store = new FakeLegacyOrderStore();
        $order = $this->orderWith($store);

        $order->shouldReceive('apply_changes')->once()->ordered();
        $order->shouldReceive('set_object_read')->once()->with(false)->ordered();
        $order->shouldReceive('read_meta_data')->once()->with(true)->ordered();

        (new FreshOrderReader())->refresh($order);

        $this->assertSame(1, $store->reads);
    }

    public function testRefreshInvalidatesHposCacheWhenTheStoreExposesIt(): void
    {
        $store = new FakeHposOrderStore();
        $order = $this->orderWith($store);
        $order->shouldReceive('apply_changes');
        $order->shouldReceive('set_object_read');
        $order->shouldReceive('read_meta_data');

        (new FreshOrderReader())->refresh($order);

        $this->assertSame([[28]], $store->cleared);
        $this->assertSame(1, $store->reads);
    }

    public function testRefreshIgnoresOrdersWithoutId(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(0);
        $order->shouldReceive('get_data_store')->never();

        (new FreshOrderReader())->refresh($order);
    }
}

class FakeLegacyOrderStore
{
    public int $reads = 0;

    public function read($order): void
    {
        $this->reads++;
    }
}

class FakeHposOrderStore extends FakeLegacyOrderStore
{
    public array $cleared = [];

    public function clear_cached_data(array $orderIds): void
    {
        $this->cleared[] = $orderIds;
    }
}
