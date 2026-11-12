<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixCheckoutRecovery;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixCheckoutRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    /** @dataProvider phaseProvider */
    public function testOnlyAnOwnedConfirmedCreationIsRecovered(string $phase, bool $expected): void
    {
        $session = Mockery::mock();
        $session->shouldReceive('get')->with(SmartPixCheckoutRecovery::SESSION_KEY)->andReturn(321);
        $session->shouldReceive('__unset')->with(SmartPixCheckoutRecovery::SESSION_KEY);
        $session->shouldNotReceive('__unset')->with('order_awaiting_payment');
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_payment_method')->andReturn($phase === 'card' ? 'woo-mercado-pago-custom' : PixGateway::ID);
        $order->shouldReceive('get_customer_id')->andReturn($phase === 'other_customer' ? 99 : 0);
        $order->shouldReceive('get_status')->andReturn($phase === 'paid' ? 'processing' : ($phase === 'linked_on_hold' ? 'on-hold' : 'pending'));
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::META_KEY, true, 'edit')
            ->andReturn($phase === 'not_started' ? '' : str_repeat('a', 64));
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::FAILURE_META_KEY, true, 'edit')
            ->andReturn($phase === 'validation_failed' ? str_repeat('a', 64) . ':validation' : '');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn($phase === 'legacy' ? 'payments_v1' : 'orders_smart_pix_v1');
        $reference = in_array($phase, ['linked', 'linked_on_hold', 'other_customer', 'legacy', 'card', 'paid'], true)
            ? 'PPORD_SYNTHETIC_001' : ($phase === 'invalid_reference' ? '../untrusted' : '');
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn($reference);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => $order]);
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $guard = new SmartPixCheckoutRecovery($metadata);
        $this->assertSame($expected ? $order : null, $guard->recover($session, 0));
    }

    public static function phaseProvider(): array
    {
        return [['linked', true], ['linked_on_hold', true], ['dispatching', false],
            ['not_started', false], ['other_customer', false], ['legacy', false], ['card', false],
            ['paid', false], ['validation_failed', false], ['invalid_reference', false]];
    }

    public function testNoBuyerSuppliedOrderIdOrMissingSessionIsUsed(): void
    {
        $session = Mockery::mock();
        $session->shouldReceive('get')->with(SmartPixCheckoutRecovery::SESSION_KEY)->andReturn(null);
        $metadata = Mockery::mock(OrderMetadata::class);
        $this->assertNull((new SmartPixCheckoutRecovery($metadata))->recover($session, 0));
    }

    public function testTheSessionBindingIsPublishedBeforeReturningToTheCreateTransport(): void
    {
        $session = Mockery::mock('WC_Session_Handler');
        $session->shouldReceive('set')->with(SmartPixCheckoutRecovery::SESSION_KEY, 321)->once()->globally()->ordered();
        $session->shouldReceive('set')->with('order_awaiting_payment', 321)->once()->globally()->ordered();
        $session->shouldReceive('save_data')->once()->globally()->ordered();
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        WP_Mock::userFunction('WC', ['return' => (object) ['session' => $session]]);
        SmartPixCheckoutRecovery::bindSession($order);
        $this->addToAssertionCount(1);
    }

    /** @dataProvider acknowledgedOrderProvider */
    public function testAcknowledgingALinkedOrderOnlyReleasesItsOwnSession(int $sessionOrderId, bool $cleared): void
    {
        $session = Mockery::mock('WC_Session');
        $session->shouldReceive('get')->with(SmartPixCheckoutRecovery::SESSION_KEY)->andReturn($sessionOrderId);
        if ($cleared) {
            $session->shouldReceive('__unset')->with(SmartPixCheckoutRecovery::SESSION_KEY)->once();
        } else {
            $session->shouldNotReceive('__unset');
        }
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        WP_Mock::userFunction('WC', ['return' => (object) ['session' => $session]]);
        SmartPixCheckoutRecovery::acknowledge($order);
        $this->addToAssertionCount(1);
    }

    public static function acknowledgedOrderProvider(): array
    {
        return [[321, true], [123, false]];
    }
}
