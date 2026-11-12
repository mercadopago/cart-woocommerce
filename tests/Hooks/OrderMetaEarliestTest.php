<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Meta_Data;
use WC_Order;

class OrderMetaEarliestTest extends TestCase
{
    use WoocommerceMock;

    private OrderMeta $orderMeta;

    private WC_Order $orderMock;

    public function setUp(): void
    {
        $this->orderMeta = new OrderMeta();
        $this->orderMock = Mockery::mock('WC_Order');
    }

    private function metaEntry(int $id, $value): WC_Meta_Data
    {
        $meta        = Mockery::mock(WC_Meta_Data::class);
        $meta->id    = $id;
        $meta->value = $value;

        return $meta;
    }

    public function testGetEarliestReturnsEmptyStringWhenNoRowExists()
    {
        $this->orderMock
            ->shouldReceive('get_meta')
            ->with('_mp_idempotency_key', false)
            ->once()
            ->andReturn([]);

        $this->assertSame('', $this->orderMeta->getEarliest($this->orderMock, '_mp_idempotency_key'));
    }

    public function testGetEarliestReturnsTheOnlyRowWhenThereIsNoDuplicate()
    {
        $this->orderMock
            ->shouldReceive('get_meta')
            ->with('_mp_idempotency_key', false)
            ->once()
            ->andReturn([$this->metaEntry(42, 'only-candidate')]);

        $this->assertSame('only-candidate', $this->orderMeta->getEarliest($this->orderMock, '_mp_idempotency_key'));
    }

    /**
     * Reproduces the exact conflict a concurrent Smart Pix write can leave
     * behind: two rows for the same key, with the earliest-persisted one
     * (lowest id) appearing SECOND in the array `get_meta(..., false)`
     * returns. A data store is free to return duplicate rows in any order
     * when it has no explicit `ORDER BY` (true for HPOS, unlike the legacy
     * postmeta store) -- `getEarliest()` must not depend on that order.
     */
    public function testGetEarliestPicksTheLowestIdRegardlessOfArrayOrder()
    {
        $this->orderMock
            ->shouldReceive('get_meta')
            ->with('_mp_idempotency_key', false)
            ->once()
            ->andReturn([
                $this->metaEntry(9, 'persisted-later'),
                $this->metaEntry(3, 'persisted-first'),
            ]);

        $this->assertSame('persisted-first', $this->orderMeta->getEarliest($this->orderMock, '_mp_idempotency_key'));
    }
}
