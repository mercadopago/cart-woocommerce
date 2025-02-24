<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;
use WC_Order;

class OrderStatusTest extends TestCase
{
    private OrderStatus $orderStatus;

    private StoreTranslations $storeTranslationsMock;

    public function setUp(): void
    {
        WP_Mock::setUp();

        $this->storeTranslationsMock = Mockery::mock(StoreTranslations::class);
        $this->storeTranslationsMock->orderStatus = [
            'payment_approved' => 'Payment Approved',
            'cho_approved' => 'Payment Approved',
            'cho_pending' => 'Payment Pending',
            'cho_default' => 'Default Message',
            'in_process' => 'In Process',
            'rejected' => 'Rejected',
            'refunded' => 'Refunded',
            'partial_refunded' => 'Partially Refunded',
            'cancelled' => 'Cancelled',
            'in_mediation' => 'In Mediation',
            'charged_back' => 'Charged Back',
            'validate_order_1' => 'Validate Order 1',
            'validate_order_2' => 'Validate Order 2'
        ];
        $this->storeTranslationsMock->commonMessages = [
            'cho_approved' => 'Payment Approved',
            'cho_pending' => 'Payment Pending',
            'cho_default' => 'Default Message'
        ];

        $this->orderStatus = new OrderStatus($this->storeTranslationsMock);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetOrderStatusMessage_ReturnsCorrectMessage(): void
    {
        $message = $this->orderStatus->getOrderStatusMessage('approved');
        $this->assertEquals('Payment Approved', $message);

        $message = $this->orderStatus->getOrderStatusMessage('unknown_status');
        $this->assertEquals('Default Message', $message);
    }

    public function testMapMpStatusToWoocommerceStatus_ReturnsCorrectWoocommerceStatus(): void
    {
        $status = $this->orderStatus->mapMpStatusToWoocommerceStatus('pending');
        $this->assertEquals('pending', $status);

        $status = $this->orderStatus->mapMpStatusToWoocommerceStatus('approved');
        $this->assertEquals('processing', $status);

        $status = $this->orderStatus->mapMpStatusToWoocommerceStatus('inprocess');
        $this->assertEquals('on-hold', $status);

        $status = $this->orderStatus->mapMpStatusToWoocommerceStatus('inmediation');
        $this->assertEquals('on-hold', $status);

        $status = $this->orderStatus->mapMpStatusToWoocommerceStatus('rejected');
        $this->assertEquals('failed', $status);

        $status = $this->orderStatus->mapMpStatusToWoocommerceStatus('cancelled');
        $this->assertEquals('cancelled', $status);

        $status = $this->orderStatus->mapMpStatusToWoocommerceStatus('refunded');
        $this->assertEquals('refunded', $status);

        $status = $this->orderStatus->mapMpStatusToWoocommerceStatus('chargedback');
        $this->assertEquals('refunded', $status);
    }

    public function testInMediationFlow_AddsOrderNotesAndUpdatesStatus(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_status')
            ->andReturn('pending');
        $orderMock->shouldReceive('update_status')
            ->with('on-hold');
        $orderMock->shouldReceive('add_order_note')
            ->with('Mercado Pago: In Mediation');

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('inMediationFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $orderMock);

        $this->assertTrue(true);
    }

    public function testChargedBackFlow_AddsOrderNotesAndUpdatesStatus(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_status')
            ->andReturn('pending');
        $orderMock->shouldReceive('update_status')
            ->with('refunded');
        $orderMock->shouldReceive('add_order_note')
            ->with('Mercado Pago: Charged Back');

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('chargedBackFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $orderMock);

        $this->assertTrue(true);
    }

    public function testMapMpStatusPendingToWoocommerceStatus()
    {
        $mpStatusWoo = new OrderStatus($this->storeTranslationsMock);
        $result = $mpStatusWoo->mapMpStatusToWoocommerceStatus('pending');
        $this->assertEquals('pending', $result);
    }
}
