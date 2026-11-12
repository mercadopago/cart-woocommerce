<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixLegacyPaymentNotice;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixLegacyPaymentNoticeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        WP_Mock::userFunction('__', ['return_arg' => 0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function testApprovedPaymentOfAnotherMethodIsNotedOnceWithAMetricAndNoStatusChange(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_meta')->with('_mp_smart_pix_legacy_payments_noted', true)->once()->andReturn('');
        $order->shouldReceive('add_order_note')->once()->with(Mockery::on(static function (string $note): bool {
            return strpos($note, '123456789') !== false && strpos($note, 'Smart Pix') !== false;
        }));
        $order->shouldReceive('update_meta_data')->once()->with('_mp_smart_pix_legacy_payments_noted', '123456789');
        $order->shouldReceive('save')->once();
        $order->shouldNotReceive('payment_complete', 'update_status', 'set_status');
        $events = [];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->andReturnUsing(static function (...$arguments) use (&$events): void {
            $events[] = $arguments;
        });

        (new SmartPixLegacyPaymentNotice(new SmartPixTelemetry($sink)))->record(
            $order,
            ['id' => 123456789, 'status' => 'approved', 'payer' => ['email' => 'buyer@example.test']],
            'notification'
        );

        $this->assertSame([[
            SmartPixMetricSchema::LEGACY_PAYMENT,
            'approved',
            ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
                'payment_method' => 'smart_pix', 'site_id' => 'MLB'],
        ]], $events);
    }
}
