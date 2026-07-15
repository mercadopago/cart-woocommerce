<?php

// Namespace override: PHP resolves unqualified add_action() in the same namespace as Gateway.php first.
// This unified version is backward-compatible with ScriptsTest's capture mechanism so that whichever
// file is autoloaded first by PHPUnit, ScriptsTest's expectations continue to work.
namespace MercadoPago\Woocommerce\Hooks;

if (!function_exists('MercadoPago\\Woocommerce\\Hooks\\add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        // ScriptsTest backward compat: capture wp_enqueue_scripts p.20 closure when trigger is set
        if (isset($GLOBALS['__test_capture_wp_enqueue_scripts']) && $tag === 'wp_enqueue_scripts' && $priority === 20) {
            $GLOBALS['__test_captured_wp_enqueue_closure'] = $callback;
        }
        // New hook tests: capture all actions indexed by tag
        $GLOBALS['__captured_actions'][$tag][] = ['callback' => $callback, 'priority' => $priority];

        \WP_Mock::onActionAdded($tag)->react($callback, $priority, $acceptedArgs);
        return true;
    }
}

if (!function_exists('MercadoPago\\Woocommerce\\Hooks\\add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['__captured_filters'][$tag][] = ['callback' => $callback, 'priority' => $priority];
        return true;
    }
}

namespace MercadoPago\Woocommerce\Tests\Hooks;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Hooks\Checkout;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Hooks\Template;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\Funnel\Funnel;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class GatewayTest extends TestCase
{
    private Gateway $gateway;

    /** @var Mockery\MockInterface|Checkout */
    private $checkoutMock;

    /** @var Mockery\MockInterface|StoreTranslations */
    private $storeTranslationsMock;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $GLOBALS['__captured_actions'] = [];
        $GLOBALS['__captured_filters'] = [];

        // AbstractGateway extends WC_Payment_Gateway; define the stub so it can be mocked.
        if (!class_exists('WC_Payment_Gateway')) {
            Mockery::mock('WC_Payment_Gateway');
        }

        $this->checkoutMock = Mockery::mock(Checkout::class);
        $this->storeTranslationsMock = Mockery::mock(StoreTranslations::class);

        $this->gateway = new Gateway(
            Mockery::mock(Options::class),
            Mockery::mock(Template::class),
            Mockery::mock(Store::class),
            $this->checkoutMock,
            $this->storeTranslationsMock,
            Mockery::mock(Url::class),
            Mockery::mock(Funnel::class)
        );
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
        unset($GLOBALS['__captured_actions']);
        unset($GLOBALS['__captured_filters']);
        unset($GLOBALS['mercadopago']);
    }

    // -------------------------------------------------------------------------
    // registerBeforeThankYou — classic hook
    // -------------------------------------------------------------------------

    public function testRegisterBeforeThankYouAddsClassicHook(): void
    {
        $callback = function ($orderId) {
        };

        WP_Mock::expectActionAdded('woocommerce_before_thankyou', $callback);
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->gateway->registerBeforeThankYou($callback);

        $this->assertSame($callback, $GLOBALS['__captured_actions']['woocommerce_before_thankyou'][0]['callback']);
    }

    // -------------------------------------------------------------------------
    // registerBeforeThankYou — block theme wp hook
    // -------------------------------------------------------------------------

    public function testRegisterBeforeThankYouAddsWpHookForBlockThemes(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->gateway->registerBeforeThankYou(function ($orderId) {
        });

        $this->assertIsCallable($GLOBALS['__captured_actions']['wp'][0]['callback']);
    }

    // -------------------------------------------------------------------------
    // registerBeforeThankYou — static flag deduplication
    // -------------------------------------------------------------------------

    public function testRegisterBeforeThankYouStaticFlagPreventsSecondRegistration(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'), 10, 1);
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'), 10, 1);

        $this->gateway->registerBeforeThankYou(function ($orderId) {
        });
        $this->gateway->registerBeforeThankYou(function ($orderId) {
        }); // must be no-op

        $this->assertCount(1, $GLOBALS['__captured_actions']['woocommerce_before_thankyou']);
        $this->assertCount(1, $GLOBALS['__captured_actions']['wp']);
    }

    // -------------------------------------------------------------------------
    // registerBeforeThankYou — block theme wp callback inner logic
    // -------------------------------------------------------------------------

    public function testRegisterBeforeThankYouBlockCallbackSkipsWhenNotOrderReceivedEndpoint(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->gateway->registerBeforeThankYou(function ($orderId) {
        });

        WP_Mock::userFunction('is_wc_endpoint_url', ['args' => ['order-received'], 'return' => false]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertArrayNotHasKey('wp_enqueue_scripts', $GLOBALS['__captured_actions']);
    }

    public function testRegisterBeforeThankYouBlockCallbackSkipsWhenOrderIdIsZero(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->gateway->registerBeforeThankYou(function ($orderId) {
        });

        WP_Mock::userFunction('is_wc_endpoint_url', ['args' => ['order-received'], 'return' => true]);
        WP_Mock::userFunction('get_query_var', ['args' => ['order-received'], 'return' => '0']);
        WP_Mock::userFunction('absint', ['args' => ['0'], 'return' => 0]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertArrayNotHasKey('wp_enqueue_scripts', $GLOBALS['__captured_actions']);
    }

    public function testBuildTitleWithOnlyDiscountDecimal(): void
    {
        WP_Mock::userFunction('wc_price')->with(0.5)->andReturn('R$&nbsp;0,50');
        WP_Mock::userFunction('wc_price')->with(0.0)->andReturn('R$&nbsp;0,00');
        WP_Mock::userFunction('wp_strip_all_tags')->andReturnUsing(fn($v) => strip_tags($v));

        $this->setCommonCheckoutTranslations(['text_concatenation' => 'e']);

        $result = $this->gateway->buildTitleWithDiscountAndCommission(0.5, 0.0, 'Desconto', 'Comissão');

        $this->assertStringContainsString('Desconto', $result);
        $this->assertStringNotContainsString('Comissão', $result);
    }

    public function testBuildTitleWithOnlyCommissionDecimal(): void
    {
        WP_Mock::userFunction('wc_price')->with(0.0)->andReturn('R$&nbsp;0,00');
        WP_Mock::userFunction('wc_price')->with(1.5)->andReturn('R$&nbsp;1,50');
        WP_Mock::userFunction('wp_strip_all_tags')->andReturnUsing(fn($v) => strip_tags($v));

        $this->setCommonCheckoutTranslations(['text_concatenation' => 'e']);

        $result = $this->gateway->buildTitleWithDiscountAndCommission(0.0, 1.5, 'Desconto', 'Comissão');

        $this->assertStringContainsString('Comissão', $result);
        $this->assertStringNotContainsString('Desconto', $result);
    }

    public function testBuildTitleWithBothDecimalValues(): void
    {
        WP_Mock::userFunction('wc_price')->with(0.5)->andReturn('R$&nbsp;0,50');
        WP_Mock::userFunction('wc_price')->with(1.5)->andReturn('R$&nbsp;1,50');
        WP_Mock::userFunction('wp_strip_all_tags')->andReturnUsing(fn($v) => strip_tags($v));

        $this->setCommonCheckoutTranslations(['text_concatenation' => 'e']);

        $result = $this->gateway->buildTitleWithDiscountAndCommission(0.5, 1.5, 'Desconto', 'Comissão');

        $this->assertStringContainsString('Desconto', $result);
        $this->assertStringContainsString('Comissão', $result);
    }

    public function testBuildTitleWithBothWholeNumberValues(): void
    {
        WP_Mock::userFunction('wc_price')->with(10.0)->andReturn('R$&nbsp;10,00');
        WP_Mock::userFunction('wc_price')->with(5.0)->andReturn('R$&nbsp;5,00');
        WP_Mock::userFunction('wp_strip_all_tags')->andReturnUsing(fn($v) => strip_tags($v));

        $this->setCommonCheckoutTranslations(['text_concatenation' => 'e']);

        $result = $this->gateway->buildTitleWithDiscountAndCommission(10.0, 5.0, 'Desconto', 'Comissão');

        $this->assertStringContainsString('Desconto', $result);
        $this->assertStringContainsString('Comissão', $result);
    }

    public function testBuildTitleReturnEmptyWhenBothAreZero(): void
    {
        WP_Mock::userFunction('wc_price')->andReturn('R$&nbsp;0,00');
        WP_Mock::userFunction('wp_strip_all_tags')->andReturnUsing(fn($v) => strip_tags($v));

        $this->setCommonCheckoutTranslations(['text_concatenation' => 'e']);

        $result = $this->gateway->buildTitleWithDiscountAndCommission(0.0, 0.0, 'Desconto', 'Comissão');

        $this->assertSame('', $result);
    }

    public function testRegisterGatewayTitleKeepsPlainTitleForNonMercadoPagoGateway(): void
    {
        $paymentGateway = Mockery::mock(AbstractGateway::class)->makePartial();
        $paymentGateway->id = 'stripe';

        $this->gateway->registerGatewayTitle($paymentGateway);
        $filter = $this->getGatewayTitleFilter();

        $this->assertSame('Stripe', $filter('Stripe', 'stripe'));
    }

    public function testRegisterGatewayTitleKeepsPlainTitleWhenBothFeesAreZero(): void
    {
        $paymentGateway = Mockery::mock(AbstractGateway::class)->makePartial();
        $paymentGateway->id         = 'woo-mercado-pago-custom';
        $paymentGateway->commission = 0.0;
        $paymentGateway->discount   = 0.0;

        $this->checkoutMock->shouldReceive('isCheckout')->andReturn(true);

        $this->gateway->registerGatewayTitle($paymentGateway);
        $filter = $this->getGatewayTitleFilter();

        $this->assertSame('Cartão de crédito', $filter('Cartão de crédito', 'woo-mercado-pago-custom'));
    }

    public function testRegisterGatewayTitleKeepsPlainTitleWhenFeesAreNegative(): void
    {
        // admin form enforces min=0; defensive guard covers values below zero
        $paymentGateway = Mockery::mock(AbstractGateway::class)->makePartial();
        $paymentGateway->id         = 'woo-mercado-pago-custom';
        $paymentGateway->commission = -1.0;
        $paymentGateway->discount   = 0.0;

        $this->checkoutMock->shouldReceive('isCheckout')->andReturn(true);

        $this->gateway->registerGatewayTitle($paymentGateway);
        $filter = $this->getGatewayTitleFilter();

        $this->assertSame('Cartão de crédito', $filter('Cartão de crédito', 'woo-mercado-pago-custom'));
    }

    public function testRegisterGatewayTitleAppendsSuffixWhenDiscountIsPositive(): void
    {
        $paymentGateway = Mockery::mock(AbstractGateway::class)->makePartial();
        $paymentGateway->id         = 'woo-mercado-pago-custom';
        $paymentGateway->commission = 0.0;
        $paymentGateway->discount   = 0.5;

        $this->checkoutMock->shouldReceive('isCheckout')->andReturn(true);

        global $mercadopago;
        $mercadopago = Mockery::mock(\MercadoPago\Woocommerce\WoocommerceMercadoPago::class);
        $cart = Mockery::mock(\MercadoPago\Woocommerce\Helpers\Cart::class);
        $cart->shouldReceive('calculateSubtotalWithDiscount')->with($paymentGateway)->andReturn(0.5);
        $cart->shouldReceive('calculateSubtotalWithCommission')->with($paymentGateway)->andReturn(0.0);
        $helpers = Mockery::mock(\MercadoPago\Woocommerce\Helpers::class);
        $helpers->cart = $cart;
        $mercadopago->helpers = $helpers;

        $this->storeTranslationsMock->commonCheckout = [
            'discount_title'     => 'Desconto',
            'fee_title'          => 'Taxa',
            'text_concatenation' => 'e',
        ];

        WP_Mock::userFunction('wc_price')->andReturnUsing(fn($v) => 'R$ ' . number_format($v, 2, ',', '.'));
        WP_Mock::userFunction('wp_strip_all_tags')->andReturnUsing(fn($v) => $v);

        $this->gateway->registerGatewayTitle($paymentGateway);
        $filter = $this->getGatewayTitleFilter();

        $result = $filter('Cartão de crédito', 'woo-mercado-pago-custom');

        $this->assertStringContainsString('Desconto', $result);
        $this->assertStringContainsString('R$ 0,50', $result);
        $this->assertStringNotContainsString('Taxa', $result);
    }

    private function getGatewayTitleFilter(): callable
    {
        return $GLOBALS['__captured_filters']['woocommerce_gateway_title'][0]['callback'];
    }

    /**
     * Sets commonCheckout translations on the gateway's translations mock via reflection.
     */
    private function setCommonCheckoutTranslations(array $commonCheckout): void
    {
        $translations = (new \ReflectionClass($this->gateway))->getProperty('translations');
        $translations->setAccessible(true);
        $translations->getValue($this->gateway)->commonCheckout = $commonCheckout;
    }

    public function testRegisterBeforeThankYouBlockCallbackRegistersEnqueueAtPriority20AndInvokesCallback(): void
    {
        $invoked  = false;
        $callback = function ($orderId) use (&$invoked) {
            $this->assertSame(99, $orderId);
            $invoked = true;
        };

        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp_enqueue_scripts', Mockery::type('callable'), 20, 1);

        $this->gateway->registerBeforeThankYou($callback);

        WP_Mock::userFunction('is_wc_endpoint_url', ['args' => ['order-received'], 'return' => true]);
        WP_Mock::userFunction('get_query_var', ['args' => ['order-received'], 'return' => '99']);
        WP_Mock::userFunction('absint', ['args' => ['99'], 'return' => 99]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertSame(20, $GLOBALS['__captured_actions']['wp_enqueue_scripts'][0]['priority']);

        ($GLOBALS['__captured_actions']['wp_enqueue_scripts'][0]['callback'])();

        $this->assertTrue($invoked);
    }
}
