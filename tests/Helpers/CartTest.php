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
}
