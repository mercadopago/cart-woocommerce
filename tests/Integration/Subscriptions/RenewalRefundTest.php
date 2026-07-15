<?php

namespace MercadoPago\Woocommerce\Tests\Integration\Subscriptions;

use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Refund\RefundHandler;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Integration tests for refund of renewal orders.
 *
 * Verifies that process_refund() in AbstractGateway correctly delegates
 * to RefundHandler for renewal orders. The RefundHandler reads payment
 * metadata (_Mercado_Pago_Payment_IDs) set by the webhook after renewal.
 *
 * @spec feat-001 US-8
 * @covers \MercadoPago\Woocommerce\Gateways\AbstractGateway::process_refund
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RenewalRefundTest extends TestCase
{
    /**
     * @var Mockery\MockInterface|CustomGateway
     */
    private $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        if (!class_exists('WP_Error')) {
            require_once __DIR__ . '/../../Mocks/WcsStubs.php';
        }

        $this->gateway = Mockery::mock(CustomGateway::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $this->gateway->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        MercadoPagoMock::mockTranslations($this->gateway, ['storeTranslations', 'adminTranslations']);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsPublicKey')->byDefault()->andReturn('TEST-public-key');
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsAccessToken')->byDefault()->andReturn('TEST-access-token');
        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('getProductionMode')->byDefault()->andReturn('yes');

        $this->gateway->settings = [
            'currency_conversion' => 'no',
            'enabled'             => 'yes',
            'title'               => 'Test Gateway',
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function makeRenewalOrderMock(int $orderId): Mockery\MockInterface
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_meta')->byDefault()->andReturn('');
        $order->shouldReceive('get_total')->andReturn(49.90);
        return $order;
    }

    /**
     * process_refund() fetches the renewal order (not the parent subscription)
     * and delegates to RefundHandler::processRefund().
     *
     * The ->once()->with($renewalOrderId) expectation on wc_get_order is the
     * explicit assertion that the correct order is fetched — if process_refund()
     * calls wc_get_order with any other ID, or not at all, the test fails.
     */
    public function testRefundOnRenewalOrderDelegatesToHandler(): void
    {
        $renewalOrderId = 401;

        $renewalOrder = $this->makeRenewalOrderMock($renewalOrderId);

        // Asserts that the renewal order (not the parent subscription) is fetched.
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with($renewalOrderId)
            ->andReturn($renewalOrder);

        $refundHandlerMock = Mockery::mock('overload:' . RefundHandler::class);
        $refundHandlerMock->shouldReceive('processRefund')
            ->once()
            ->with(49.90, 'Customer request')
            ->andReturn(true);

        $result = $this->gateway->process_refund($renewalOrderId, 49.90, 'Customer request');

        $this->assertTrue($result);
    }

    /**
     * AC-2: Partial refund works on renewal order.
     *
     * Ensures partial refunds (amount < total) are processed correctly.
     */
    public function testPartialRefundWorksOnRenewalOrder(): void
    {
        $renewalOrderId = 402;
        $partialAmount  = 25.00;

        $renewalOrder = $this->makeRenewalOrderMock($renewalOrderId);

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with($renewalOrderId)
            ->andReturn($renewalOrder);

        $refundHandlerMock = Mockery::mock('overload:' . RefundHandler::class);
        $refundHandlerMock->shouldReceive('processRefund')
            ->once()
            ->with($partialAmount, 'Partial refund')
            ->andReturn(true);

        $result = $this->gateway->process_refund($renewalOrderId, $partialAmount, 'Partial refund');

        $this->assertTrue($result);
    }

    /**
     * AC-3: Refund returns WP_Error when RefundHandler throws (e.g. missing payment metadata).
     */
    public function testRefundReturnsWpErrorWhenHandlerThrows(): void
    {
        $renewalOrderId = 403;

        $renewalOrder = $this->makeRenewalOrderMock($renewalOrderId);

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with($renewalOrderId)
            ->andReturn($renewalOrder);

        // RefundHandler throws exception when payment_id is missing
        $refundHandlerMock = Mockery::mock('overload:' . RefundHandler::class);
        $refundHandlerMock->shouldReceive('processRefund')
            ->once()
            ->andThrow(new \Exception('missing_payment_id'));

        $result = $this->gateway->process_refund($renewalOrderId, 49.90, 'Customer request');

        // Should return WP_Error instance
        $this->assertInstanceOf(\WP_Error::class, $result);
    }
}
