<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricPayload;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use PHPUnit\Framework\TestCase;
use Mockery;
use WC_Order;

class SmartPixMetricContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /** @dataProvider invalidIds */
    public function testInvalidOrPrivateValuesAreNeverCorrelationIds($value): void
    {
        $this->assertNull(SmartPixMetricContext::identifier($value));
    }

    public static function invalidIds(): array
    {
        return [[null], [[]], [123], [''], ['Unknown'], ['not_available'], ['buyer@example.test'],
            ['APP_USR-private'], ['STAUTH-private'], ['Bearer-private'], ["request\n123"], [str_repeat('a', 129)]];
    }

    public function testCapturePreservesTheOriginalSdkInstanceOnRetries(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $id = '';
        $order->shouldReceive('get_meta')->with('_mp_smart_pix_sdk_instance_id', true)
            ->andReturnUsing(static function () use (&$id) {
                return $id;
            });
        $order->shouldReceive('update_meta_data')->with('_mp_smart_pix_sdk_instance_id', 'sdk-origin')
            ->once()->andReturnUsing(static function ($key, $value) use (&$id): void {
                $id = $value;
            });
        $order->shouldReceive('save_meta_data')->once();
        $order->shouldNotReceive('payment_complete', 'update_status', 'save');
        SmartPixMetricContext::capture($order, 'sdk-origin');
        SmartPixMetricContext::capture($order, 'sdk-after-reload');
        $this->assertSame(['sdk_instance_id' => 'sdk-origin'], SmartPixMetricContext::forOrder($order));
    }

    public function testEachRequestReplacesThePreviousRequestIdAndResetCannotLeakAnOrderContext(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_meta')->with('_mp_smart_pix_sdk_instance_id', true)->andReturn('sdk-original');
        $context = new SmartPixMetricContext();
        $context->observeOrder($order);
        $context->observeRequest('request-create', 'POST /plugins-platforms/v1/orders');
        $context->observeRequest('request-get', 'GET /plugins-platforms/v1/orders/{id}');
        $this->assertSame(['sdk_instance_id' => 'sdk-original', 'request_id' => 'request-get',
            'api_route' => 'GET /plugins-platforms/v1/orders/{id}'], $context->details());
        $context->observeRequest(null, 'GET /plugins-platforms/v1/orders/{id}');
        $this->assertArrayNotHasKey('request_id', $context->details());
        $context->reset();
        $this->assertSame([], $context->details());
        $context->observeRequest('request-valid', 'https://example.test/?token=private');
        $this->assertSame(['request_id' => 'request-valid'], $context->details());
    }

    public function testMetadataFailuresDoNotChangeThePaymentFlow(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_meta')->andThrow(new \RuntimeException('private diagnostics'));
        $order->shouldReceive('update_meta_data')->andThrow(new \RuntimeException('private diagnostics'));
        SmartPixMetricContext::capture($order, 'sdk-valid');
        $this->assertSame([], SmartPixMetricContext::forOrder($order));
    }

    /**
     * @testWith ["GET /plugins-platforms/v1/notification/order/{id}", true]
     *           ["GET /plugins-platforms/v1/notification/order/PPORDSYNTHETIC001", false]
     *           ["https://api.mercadopago.com/plugins-platforms/v1/notification/order/PPORDSYNTHETIC001", false]
     *           ["GET /v1/asgard/notification/{id}", false]
     */
    public function testNotificationCorrelationAcceptsOnlyTheCurrentStaticRoute(string $route, bool $accepted): void
    {
        $context = new SmartPixMetricContext();
        $context->observeRequest('request-notification', $route);
        $expected = ['request_id' => 'request-notification'];
        if ($accepted) {
            $expected['api_route'] = $route;
        }

        $this->assertSame($expected, $context->details());
    }
}
