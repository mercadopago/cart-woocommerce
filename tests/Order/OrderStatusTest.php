<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;

class OrderStatusTest extends TestCase
{
    private OrderStatus $orderStatus;

    private StoreTranslations $storeTranslationsMock;

    public function setUp(): void
    {
        WP_Mock::setUp();

        $this->storeTranslationsMock = Mockery::mock(StoreTranslations::class);
        $this->storeTranslationsMock->orderStatus = [
            'cho_approved' => 'Payment Approved',
            'cho_pending' => 'Payment Pending',
            'cho_default' => 'Default Message'
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
}
