<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRefundContext;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_Order_Refund;
use WP_Mock;

class SmartPixRefundContextTest extends TestCase
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

    /** @dataProvider contextProvider */
    public function testOnlySavedCorrelatedNativeRefundsResolve(int $refundId, int $parentId, string $currency, string $total, float $amount, bool $expected): void
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_id')->andReturn($refundId);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn($parentId);
        $refund->shouldReceive('get_currency')->with('edit')->andReturn($currency);
        $refund->shouldReceive('get_total')->with('edit')->andReturn($total);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $context = new SmartPixRefundContext();
        $context->capture($refund, ['refund_payment' => true]);
        $result = $context->resolve($order, $amount);
        $this->assertSame($expected, $result !== null);
        if ($expected) {
            $this->assertSame(['identity' => 'refund_42', 'amount' => '25.50'], $result);
        }
    }

    public static function contextProvider(): array
    {
        return [[42, 321, 'BRL', '-25.50', 25.5, true], [0, 321, 'BRL', '-25.50', 25.5, false],
            [42, 999, 'BRL', '-25.50', 25.5, false], [42, 321, 'USD', '-25.50', 25.5, false],
            [42, 321, 'BRL', '-25.501', 25.5, false], [42, 321, 'BRL', '-25.50', 30.0, false],
            [42, 321, 'BRL', '25.50', 25.5, false], [42, 321, 'BRL', '-0.00', 0.0, false]];
    }

    public function testManualBookkeepingAndUncapturedRefundsNeverResolve(): void
    {
        $context = new SmartPixRefundContext();
        $order = Mockery::mock(WC_Order::class);
        $this->assertNull($context->resolve($order, 25.5));
        $refund = Mockery::mock(WC_Order_Refund::class);
        $context->capture($refund, ['refund_payment' => false]);
        $this->assertNull($context->resolve($order, 25.5));
    }

    /** @dataProvider stampProvider */
    public function testOnlyAGatewayRefundOfASmartPixOrderIsStampedBeforeItIsSaved(bool $refundPayment, string $contract, bool $stamped): void
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        if ($stamped) {
            $refund->shouldReceive('update_meta_data')->once()->with(SmartPixRefundContext::IN_FLIGHT_META_KEY, '1');
        } else {
            $refund->shouldNotReceive('update_meta_data');
        }
        $order = Mockery::mock(WC_Order::class);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => $order]);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn($contract);

        (new SmartPixRefundContext($metadata))->capture($refund, ['refund_payment' => $refundPayment]);
        $this->addToAssertionCount(1);
    }

    public static function stampProvider(): array
    {
        return [
            'gateway refund of a Smart Pix order' => [true, OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, true],
            'gateway refund of another contract' => [true, 'payments_v1', false],
            'manual bookkeeping refund' => [false, OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, false],
        ];
    }

    public function testAnUnreadableParentKeepsTheConservativeStamp(): void
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        $refund->shouldReceive('update_meta_data')->once()->with(SmartPixRefundContext::IN_FLIGHT_META_KEY, '1');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => static function () {
            throw new \RuntimeException('read failed');
        }]);

        (new SmartPixRefundContext(Mockery::mock(OrderMetadata::class)))->capture($refund, ['refund_payment' => true]);
        $this->addToAssertionCount(1);
    }

    public function testAMissingParentIsNotStamped(): void
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        $refund->shouldNotReceive('update_meta_data');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => false]);

        (new SmartPixRefundContext(Mockery::mock(OrderMetadata::class)))->capture($refund, ['refund_payment' => true]);
        $this->addToAssertionCount(1);
    }

    /** @dataProvider inFlightProvider */
    public function testOnlyAStampedRefundWithoutConfirmedPaymentIsInFlight(string $stamp, bool $refundedPayment, bool $expected): void
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_meta')->with(SmartPixRefundContext::IN_FLIGHT_META_KEY, true, 'edit')->andReturn($stamp);
        $refund->shouldReceive('get_refunded_payment')->with('edit')->andReturn($refundedPayment);

        $this->assertSame($expected, SmartPixRefundContext::isInFlight($refund));
    }

    public static function inFlightProvider(): array
    {
        return [
            'stamped, gateway call pending' => ['1', false, true],
            'stamped, gateway call confirmed' => ['1', true, false],
            'not stamped' => ['', false, false],
        ];
    }
}
