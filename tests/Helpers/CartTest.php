<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use Exception;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;
use MercadoPago\Woocommerce\Blocks\AbstractBlock;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Cart;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\Tests\Mocks\CartGatewayStub;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class CartTest extends TestCase
{
    use WoocommerceMock;
    private Cart $cart;

    private $woocommerce;
    private $wcCart;
    private $country;
    private $currency;
    private $session;
    private $storeTranslations;

    public function setUp(): void
    {
        parent::setUp();

        $this->country  = Mockery::mock(Country::class);
        $this->currency = Mockery::mock(Currency::class);
        $this->session  = Mockery::mock(Session::class);

        $this->storeTranslations = Mockery::mock(StoreTranslations::class)->makePartial();
        $this->storeTranslations->commonCheckout = [
            'cart_discount'   => 'Mercado Pago Discount',
            'cart_commission' => 'Mercado Pago Commission',
        ];

        $this->wcCart = Mockery::mock('WC_Cart');

        $this->woocommerce = Mockery::mock('WooCommerce');
        $this->woocommerce->cart = $this->wcCart;

        $GLOBALS['woocommerce'] = $this->woocommerce;

        $this->cart = new Cart(
            $this->country,
            $this->currency,
            $this->session,
            $this->storeTranslations
        );
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @dataProvider convertedSubtotals
     */
    public function testConvertedSubtotalIncludesTaxAndUsesCountryRatio(
        string $method,
        string $percentageProperty,
        float $percentage,
        string $currency,
        float $ratio,
        float $expected
    ): void {
        $gateway = Mockery::mock(CartGatewayStub::class)->makePartial();
        $gateway->{$percentageProperty} = $percentage;
        $this->wcCart->shouldReceive('get_cart_contents_total')->once()->andReturn(100.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->once()->andReturn(20.0);
        $this->currency->shouldReceive('getRatio')->once()->with($gateway)->andReturn($ratio);
        $this->country->shouldReceive('getCountryConfigs')->once()->andReturn(['currency' => $currency]);

        $this->assertSame($expected, $this->cart->{$method}($gateway));
    }

    public function convertedSubtotals(): array
    {
        return [
            'BRL discount includes tax and ratio' => ['calculateSubtotalWithDiscount', 'discount', 10.0, 'BRL', 2.0, 24.0],
            'BRL commission includes tax and ratio' => ['calculateSubtotalWithCommission', 'commission', 5.0, 'BRL', 2.0, 12.0],
            'CLP discount rounds converted amount' => ['calculateSubtotalWithDiscount', 'discount', 10.0, 'CLP', 1.375, 17.0],
            'COP commission rounds converted amount' => ['calculateSubtotalWithCommission', 'commission', 5.0, 'COP', 1.75, 11.0],
        ];
    }

    public function testConvertedTotalUsesWooCommerceTotalCountryAndRatio(): void
    {
        $gateway = Mockery::mock(CartGatewayStub::class)->makePartial();
        $this->wcCart->shouldReceive('__get')->once()->with('total')->andReturn(150.0);
        $this->wcCart->shouldNotReceive('get_cart_contents_total');
        $this->wcCart->shouldNotReceive('get_cart_contents_tax');
        $this->currency->shouldReceive('getRatio')->once()->with($gateway)->andReturn(1.5);
        $this->country->shouldReceive('getCountryConfigs')->once()->andReturn(['currency' => 'CLP']);

        $this->assertSame(225.0, $this->cart->calculateTotalWithDiscountAndCommission($gateway));
    }

    /**
     * @dataProvider conversionMethods
     */
    public function testConversionFailurePropagatesBeforeReadingCartAmount(string $method): void
    {
        $gateway = Mockery::mock(CartGatewayStub::class)->makePartial();
        $failure = new Exception('Exchange rate unavailable');
        $this->currency->shouldReceive('getRatio')->once()->with($gateway)->andThrow($failure);
        $this->country->shouldNotReceive('getCountryConfigs');
        $this->wcCart->shouldNotReceive('__get');
        $this->wcCart->shouldNotReceive('get_cart_contents_total');
        $this->wcCart->shouldNotReceive('get_cart_contents_tax');

        try {
            $this->cart->{$method}($gateway);
            $this->fail('Expected the original exchange failure to propagate.');
        } catch (Exception $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function conversionMethods(): array
    {
        return [
            'discount' => ['calculateSubtotalWithDiscount'],
            'commission' => ['calculateSubtotalWithCommission'],
            'total' => ['calculateTotalWithDiscountAndCommission'],
        ];
    }

    // -------------------------------------------------------------------------
    // addDiscountOnFees — only adds when discount > 0
    // -------------------------------------------------------------------------

    /**
     * @throws Exception
     */
    public function testAddDiscountOnFeesCallsAddFeeWhenDiscountIsPositive(): void
    {
        $gateway           = Mockery::mock(AbstractGateway::class)->makePartial();
        $gateway->discount = 10;

        $this->wcCart->shouldReceive('get_cart_contents_total')->once()->andReturn(100.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->once()->andReturn(0.0);
        $this->wcCart->shouldReceive('add_fee')
            ->once()
            ->with('Mercado Pago Discount', -10.0, true);

        $this->cart->addDiscountOnFees($gateway);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testAddDiscountOnFeesWithDecimalDiscount(): void
    {
        $gateway           = Mockery::mock(AbstractGateway::class)->makePartial();
        $gateway->discount = 0.5;

        $this->wcCart->shouldReceive('get_cart_contents_total')->once()->andReturn(100.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->once()->andReturn(0.0);
        $this->wcCart->shouldReceive('add_fee')
            ->once()
            ->with('Mercado Pago Discount', -0.5, true);

        $this->cart->addDiscountOnFees($gateway);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testAddDiscountOnFeesDoesNotCallAddFeeWhenDiscountIsZero(): void
    {
        $gateway           = Mockery::mock(AbstractGateway::class)->makePartial();
        $gateway->discount = 0;

        $this->wcCart->shouldReceive('get_cart_contents_total')->once()->andReturn(100.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->once()->andReturn(0.0);
        $this->wcCart->shouldNotReceive('add_fee');

        $this->cart->addDiscountOnFees($gateway);

        $this->expectNotToPerformAssertions();
    }

    // -------------------------------------------------------------------------
    // addCommissionOnFees — only adds when commission > 0
    // -------------------------------------------------------------------------

    /**
     * @throws Exception
     */
    public function testAddCommissionOnFeesCallsAddFeeWhenCommissionIsPositive(): void
    {
        $gateway             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gateway->commission = 5;

        $this->wcCart->shouldReceive('get_cart_contents_total')->once()->andReturn(100.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->once()->andReturn(0.0);
        $this->wcCart->shouldReceive('add_fee')
            ->once()
            ->with('Mercado Pago Commission', 5.0, true);

        $this->cart->addCommissionOnFees($gateway);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testAddCommissionOnFeesWithDecimalCommission(): void
    {
        $gateway             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gateway->commission = 1.5;

        $this->wcCart->shouldReceive('get_cart_contents_total')->once()->andReturn(200.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->once()->andReturn(0.0);
        $this->wcCart->shouldReceive('add_fee')
            ->once()
            ->with('Mercado Pago Commission', 3.0, true);

        $this->cart->addCommissionOnFees($gateway);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testAddCommissionOnFeesDoesNotCallAddFeeWhenCommissionIsZero(): void
    {
        $gateway             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gateway->commission = 0;

        $this->wcCart->shouldReceive('get_cart_contents_total')->once()->andReturn(100.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->once()->andReturn(0.0);
        $this->wcCart->shouldNotReceive('add_fee');

        $this->cart->addCommissionOnFees($gateway);

        $this->expectNotToPerformAssertions();
    }

    // -------------------------------------------------------------------------
    // addDiscountAndCommissionOnFeesFromBlocks — gateway-ID guard
    // -------------------------------------------------------------------------

    /**
     * @throws Exception
     */
    public function testAddFeesFromBlocksSkipsWhenGatewayIdDoesNotMatch(): void
    {
        $gateway             = Mockery::mock(CartGatewayStub::class)->makePartial();
        $gateway->discount   = 10;
        $gateway->commission = 5;

        $this->session
            ->shouldReceive('getSession')
            ->with(AbstractBlock::GATEWAY_SESSION_KEY)
            ->once()
            ->andReturn('woo-mercado-pago-other');

        $this->wcCart->shouldNotReceive('add_fee');

        $this->cart->addDiscountAndCommissionOnFeesFromBlocks($gateway);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testAddFeesFromBlocksAddsFeesWhenGatewayIdMatches(): void
    {
        $gateway             = Mockery::mock(CartGatewayStub::class)->makePartial();
        $gateway->discount   = 10;
        $gateway->commission = 5;

        $this->session
            ->shouldReceive('getSession')
            ->with(AbstractBlock::GATEWAY_SESSION_KEY)
            ->once()
            ->andReturn(CartGatewayStub::ID);

        $this->wcCart->shouldReceive('get_cart_contents_total')->twice()->andReturn(100.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->twice()->andReturn(0.0);
        $this->wcCart->shouldReceive('add_fee')
            ->once()
            ->with('Mercado Pago Discount', -10.0, true);
        $this->wcCart->shouldReceive('add_fee')
            ->once()
            ->with('Mercado Pago Commission', 5.0, true);

        $this->cart->addDiscountAndCommissionOnFeesFromBlocks($gateway);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testAddFeesFromBlocksSkipsWhenSessionIsNull(): void
    {
        $gateway             = Mockery::mock(CartGatewayStub::class)->makePartial();
        $gateway->discount   = 10;
        $gateway->commission = 5;

        $this->session
            ->shouldReceive('getSession')
            ->with(AbstractBlock::GATEWAY_SESSION_KEY)
            ->once()
            ->andReturnNull();

        $this->wcCart->shouldNotReceive('add_fee');

        $this->cart->addDiscountAndCommissionOnFeesFromBlocks($gateway);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testAddDiscountAndCommissionOnFeesClassicWithDecimalValues(): void
    {
        $gateway             = Mockery::mock(CartGatewayStub::class)->makePartial();
        $gateway->discount   = 0.5;
        $gateway->commission = 1.5;

        $this->session
            ->shouldReceive('getSession')
            ->with('chosen_payment_method')
            ->once()
            ->andReturn(CartGatewayStub::ID);

        $this->wcCart->shouldReceive('get_cart_contents_total')->twice()->andReturn(100.0);
        $this->wcCart->shouldReceive('get_cart_contents_tax')->twice()->andReturn(0.0);
        $this->wcCart->shouldReceive('add_fee')
            ->once()
            ->with('Mercado Pago Discount', -0.5, true);
        $this->wcCart->shouldReceive('add_fee')
            ->once()
            ->with('Mercado Pago Commission', 1.5, true);

        $this->cart->addDiscountAndCommissionOnFees($gateway);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testAddDiscountAndCommissionOnFeesClassicSkipsWhenGatewayIdDoesNotMatch(): void
    {
        $gateway             = Mockery::mock(CartGatewayStub::class)->makePartial();
        $gateway->discount   = 0.5;
        $gateway->commission = 1.5;

        $this->session
            ->shouldReceive('getSession')
            ->with('chosen_payment_method')
            ->once()
            ->andReturn('woo-mercado-pago-other');

        $this->wcCart->shouldNotReceive('add_fee');

        $this->cart->addDiscountAndCommissionOnFees($gateway);

        $this->expectNotToPerformAssertions();
    }
}
