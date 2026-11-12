<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRepaymentGuard;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixRepaymentGuardTest extends TestCase
{
    private const ORDER_RECEIVED_URL = 'https://store.test/checkout/order-received/321/?key=wc_order_SYNTHETIC';

    private const ORDER_PAY_URL = 'https://store.test/checkout/order-pay/321/?pay_for_order=true&key=wc_order_SYNTHETIC';

    private const REPAYMENT_FILTERS = [
        'woocommerce_valid_order_statuses_for_payment' => 'filterValidStatusesForPayment',
        'woocommerce_order_needs_payment'              => 'filterNeedsPayment',
        'woocommerce_get_checkout_payment_url'         => 'filterCheckoutPaymentUrl',
    ];

    /** @var OrderMetadata|\Mockery\MockInterface */
    private $metadata;

    private SmartPixRepaymentGuard $guard;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $this->guard = new SmartPixRepaymentGuard($this->metadata);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testGivenThePluginBootsWhenRegisteringTheGuardThenItFiltersEveryRepaymentEntryPoint(): void
    {
        foreach (self::REPAYMENT_FILTERS as $hook => $method) {
            WP_Mock::expectFilterAdded($hook, [WP_Mock\Functions::type(SmartPixRepaymentGuard::class), $method], 20, 2);
        }
        WP_Mock::expectActionAdded(
            'woocommerce_before_checkout_process',
            [WP_Mock\Functions::type(SmartPixRepaymentGuard::class), 'releaseRejectedOrderFromSession'],
            20,
            0
        );
        WP_Mock::expectActionAdded(
            'woocommerce_resume_order',
            [WP_Mock\Functions::type(SmartPixRepaymentGuard::class), 'stopProcessingOrderResume'],
            20,
            1
        );

        SmartPixRepaymentGuard::register($this->metadata);

        WP_Mock::assertHooksAdded();
        $this->addToAssertionCount(1);
    }

    public function testGivenARejectedSmartPixOrderWhenWooCommerceAsksForPaymentThenRepaymentIsBlocked(): void
    {
        $order = $this->order(PixGateway::ID, 'failed', OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, 'ORDER_SYNTHETIC_001');

        $this->assertTrue($this->guard->isRejectedSmartPixOrder($order));
        $this->assertSame(['pending'], $this->guard->filterValidStatusesForPayment(['pending', 'failed'], $order));
        $this->assertFalse($this->guard->filterNeedsPayment(true, $order));
        $this->assertSame(self::ORDER_RECEIVED_URL, $this->guard->filterCheckoutPaymentUrl(self::ORDER_PAY_URL, $order));
    }

    public function testGivenASmartPixOrderStillProcessingWhenWooCommerceAsksForPaymentThenPayLinksLeadToItsStatus(): void
    {
        $order = $this->order(PixGateway::ID, 'pending', OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, 'PPORDSYNTHETIC001');

        $this->assertTrue($this->guard->isProcessingSmartPixOrder($order));
        $this->assertFalse($this->guard->isRejectedSmartPixOrder($order));
        $this->assertSame(['failed'], $this->guard->filterValidStatusesForPayment(['pending', 'failed'], $order));
        $this->assertFalse($this->guard->filterNeedsPayment(true, $order));
        $this->assertSame(self::ORDER_RECEIVED_URL, $this->guard->filterCheckoutPaymentUrl(self::ORDER_PAY_URL, $order));
    }

    public function testGivenASmartPixOrderStillProcessingWhenClassicCheckoutResumesItThenTheCheckoutStopsBeforeCompletingIt(): void
    {
        $order = $this->order(PixGateway::ID, 'pending', OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, 'PPORDSYNTHETIC001');
        $order->shouldNotReceive('payment_complete', 'remove_order_items');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('We are processing your Pix via Open Finance payment. Your order remains pending until the payment is confirmed.');

        $this->guard->stopProcessingOrderResume(321);
    }

    public function testGivenARejectedSmartPixOrderInTheSessionWhenClassicCheckoutStartsThenWooCommerceCreatesANewOrder(): void
    {
        $order = $this->order(PixGateway::ID, 'failed', OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, 'PPORDSYNTHETIC001');
        $order->shouldNotReceive('payment_complete');
        $session = Mockery::mock(\WC_Session::class);
        $session->shouldReceive('get')->once()->with('order_awaiting_payment')->andReturn(321);
        $session->shouldReceive('set')->once()->with('order_awaiting_payment', false);
        $woocommerce = new \stdClass();
        $woocommerce->session = $session;
        WP_Mock::userFunction('WC', ['return' => $woocommerce]);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $this->guard->releaseRejectedOrderFromSession();

        $this->addToAssertionCount(1);
    }

    /** @dataProvider keptSessionOrderProvider */
    public function testGivenASessionOrderThatIsNotARejectedSmartPixOrderWhenClassicCheckoutStartsThenTheSessionIsKept(
        string $paymentMethod,
        string $status,
        string $contract,
        string $reference
    ): void {
        $order = $this->order($paymentMethod, $status, $contract, $reference);
        $session = Mockery::mock(\WC_Session::class);
        $session->shouldReceive('get')->once()->with('order_awaiting_payment')->andReturn(321);
        $session->shouldNotReceive('set');
        $woocommerce = new \stdClass();
        $woocommerce->session = $session;
        WP_Mock::userFunction('WC', ['return' => $woocommerce]);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $this->guard->releaseRejectedOrderFromSession();

        $this->addToAssertionCount(1);
    }

    public static function keptSessionOrderProvider(): array
    {
        $contract = OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1;

        return [
            'processing Smart Pix'     => [PixGateway::ID, 'pending', $contract, 'PPORDSYNTHETIC001'],
            'failed unlinked Smart Pix' => [PixGateway::ID, 'failed', $contract, ''],
            'failed conventional Pix'  => [PixGateway::ID, 'failed', '', ''],
            'failed other gateway'     => ['woo-mercado-pago-custom', 'failed', $contract, 'PPORDSYNTHETIC001'],
        ];
    }

    /**
     * @dataProvider sessionWithoutOrderProvider
     * @param mixed $sessionOrderId
     */
    public function testGivenASessionWithoutAValidOrderWhenClassicCheckoutStartsThenNoOrderIsRead($sessionOrderId): void
    {
        $session = Mockery::mock(\WC_Session::class);
        $session->shouldReceive('get')->once()->with('order_awaiting_payment')->andReturn($sessionOrderId);
        $session->shouldNotReceive('set');
        $woocommerce = new \stdClass();
        $woocommerce->session = $session;
        WP_Mock::userFunction('WC', ['return' => $woocommerce]);
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);

        $this->guard->releaseRejectedOrderFromSession();

        $this->addToAssertionCount(1);
    }

    public static function sessionWithoutOrderProvider(): array
    {
        return ['no order' => [null], 'false' => [false], 'zero' => [0], 'not numeric' => ['abc']];
    }

    /**
     * WC() is defined process-wide once any test mocks it, so this path needs a fresh process.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenWooCommerceIsNotLoadedWhenClassicCheckoutStartsThenNothingIsRead(): void
    {
        $this->assertFalse(function_exists('WC'));
        $this->metadata->shouldNotReceive('getPaymentContract', 'getPpOrderId');
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);

        $this->guard->releaseRejectedOrderFromSession();

        $this->assertFalse(function_exists('WC'));
    }

    public function testGivenNoWooCommerceSessionWhenClassicCheckoutStartsThenNothingIsRead(): void
    {
        $woocommerce = new \stdClass();
        $woocommerce->session = null;
        WP_Mock::userFunction('WC', ['return' => $woocommerce]);
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);

        $this->guard->releaseRejectedOrderFromSession();

        $this->addToAssertionCount(1);
    }

    public function testGivenARejectedOrderThatNoLongerExistsWhenClassicCheckoutStartsThenTheSessionIsKept(): void
    {
        $session = Mockery::mock(\WC_Session::class);
        $session->shouldReceive('get')->once()->with('order_awaiting_payment')->andReturn(321);
        $session->shouldNotReceive('set');
        $woocommerce = new \stdClass();
        $woocommerce->session = $session;
        WP_Mock::userFunction('WC', ['return' => $woocommerce]);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => false]);

        $this->guard->releaseRejectedOrderFromSession();

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider resumableOrderProvider
     * @param mixed $contract
     * @param mixed $reference
     */
    public function testGivenAnOrderWithoutAProcessingSmartPixOrderWhenClassicCheckoutResumesItThenTheCheckoutContinues(
        string $paymentMethod,
        string $status,
        $contract,
        $reference,
        bool $paid
    ): void {
        $order = $this->order($paymentMethod, $status, $contract, $reference);
        $order->shouldReceive('is_paid')->andReturn($paid);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $this->guard->stopProcessingOrderResume(321);

        $this->addToAssertionCount(1);
    }

    public static function resumableOrderProvider(): array
    {
        $contract = OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1;

        return [
            'pending unlinked Smart Pix'      => [PixGateway::ID, 'pending', $contract, '', false],
            'pending conventional Pix'        => [PixGateway::ID, 'pending', '', '', false],
            'pending order of another gateway' => ['woo-mercado-pago-custom', 'pending', $contract, 'PPORDSYNTHETIC001', false],
            'paid linked Smart Pix'           => [PixGateway::ID, 'pending', $contract, 'PPORDSYNTHETIC001', true],
        ];
    }

    /**
     * @dataProvider invalidResumedOrderIdProvider
     * @param mixed $orderId
     */
    public function testGivenAResumeWithoutAValidOrderIdWhenGuardingThenNoOrderIsRead($orderId): void
    {
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $this->metadata->shouldNotReceive('getPaymentContract', 'getPpOrderId');

        $this->guard->stopProcessingOrderResume($orderId);

        $this->addToAssertionCount(1);
    }

    public static function invalidResumedOrderIdProvider(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'not numeric' => ['abc'], 'null' => [null]];
    }

    public function testGivenAResumedOrderThatNoLongerExistsWhenGuardingThenTheCheckoutContinues(): void
    {
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => false]);
        $this->metadata->shouldNotReceive('getPaymentContract', 'getPpOrderId');

        $this->guard->stopProcessingOrderResume(321);

        $this->addToAssertionCount(1);
    }

    /** @dataProvider payableOrderProvider */
    public function testGivenAnOrderOutsideTheRejectedSmartPixCaseWhenWooCommerceAsksForPaymentThenNothingChanges(
        string $paymentMethod,
        string $status,
        $contract,
        $reference
    ): void {
        $order = $this->order($paymentMethod, $status, $contract, $reference);

        $this->assertFalse($this->guard->isRejectedSmartPixOrder($order));
        $this->assertSame(['pending', 'failed'], $this->guard->filterValidStatusesForPayment(['pending', 'failed'], $order));
        $this->assertTrue($this->guard->filterNeedsPayment(true, $order));
        $this->assertSame(self::ORDER_PAY_URL, $this->guard->filterCheckoutPaymentUrl(self::ORDER_PAY_URL, $order));
    }

    public static function payableOrderProvider(): array
    {
        $contract = OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1;

        return [
            'failed conventional Pix'         => [PixGateway::ID, 'failed', '', ''],
            'pending unlinked Smart Pix'      => [PixGateway::ID, 'pending', $contract, ''],
            'pending conventional Pix'        => [PixGateway::ID, 'pending', '', ''],
            'failed unlinked Smart Pix'       => [PixGateway::ID, 'failed', $contract, ''],
            'failed order of another gateway' => ['woo-mercado-pago-custom', 'failed', $contract, 'ORDER_SYNTHETIC_001'],
        ];
    }

    public function testGivenAnAdmittedCreationWithoutALinkedOrderWhenWooCommerceAsksForPaymentThenItStaysPayable(): void
    {
        $order = $this->order(PixGateway::ID, 'pending', OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, '');
        // The creation admission alone never blocks repayment: without a linked Order nothing could release it.
        $order->shouldReceive('get_meta')->with('_mp_smart_pix_creation_started', true, 'edit')
            ->andReturn(str_repeat('a', 64));
        $this->metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_ATTEMPT_KEY');

        $this->assertFalse($this->guard->isProcessingSmartPixOrder($order));
        $this->assertSame(['pending', 'on-hold'], $this->guard->filterValidStatusesForPayment(['pending', 'on-hold'], $order));
        $this->assertTrue($this->guard->filterNeedsPayment(true, $order));
        $this->assertSame(self::ORDER_PAY_URL, $this->guard->filterCheckoutPaymentUrl(self::ORDER_PAY_URL, $order));
    }

    public function testGivenAFilterCalledWithoutAnOrderWhenGuardingThenTheOriginalValueIsKept(): void
    {
        $this->metadata->shouldNotReceive('getPaymentContract');

        $this->assertSame(['pending', 'failed'], $this->guard->filterValidStatusesForPayment(['pending', 'failed']));
        $this->assertTrue($this->guard->filterNeedsPayment(true));
        $this->assertSame(self::ORDER_PAY_URL, $this->guard->filterCheckoutPaymentUrl(self::ORDER_PAY_URL));
    }

    public function testGivenStatusesThatAreNotAListWhenGuardingThenTheyAreKeptWithoutReadingTheOrder(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('get_payment_method', 'get_status');
        $this->metadata->shouldNotReceive('getPaymentContract', 'getPpOrderId');

        $this->assertNull($this->guard->filterValidStatusesForPayment(null, $order));
    }

    /**
     * @param mixed $contract
     * @param mixed $reference
     * @return WC_Order|\Mockery\MockInterface
     */
    private function order(string $paymentMethod, string $status, $contract, $reference)
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_payment_method')->andReturn($paymentMethod);
        $order->shouldReceive('get_status')->andReturn($status);
        $order->shouldReceive('is_paid')->andReturn(false)->byDefault();
        $order->shouldReceive('get_checkout_order_received_url')->andReturn(self::ORDER_RECEIVED_URL);
        $this->metadata->shouldReceive('getPaymentContract')->with($order)->andReturn($contract);
        $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn($reference);

        return $order;
    }
}
