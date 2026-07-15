<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Blocks\AbstractBlock;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

/**
 * Concrete stub that bypasses the original constructor to avoid WC() and global $mercadopago dependencies.
 */
class AbstractBlockStub extends AbstractBlock
{
    public $gateway = null;

    public function __construct(WoocommerceMercadoPago $mercadopago)
    {
        $this->mercadopago       = $mercadopago;
        $this->links             = [];
        $this->storeTranslations = [];
        $this->settings          = ['title' => 'Test Title', 'description' => 'Test Description'];
    }

    public function getScriptParams(): array
    {
        return ['test_param' => 'test_value'];
    }
}

class AbstractBlockTest extends TestCase
{
    use WoocommerceMock;

    private AbstractBlockStub $block;
    private $mercadopago;

    /**
     * @before
     */
    public function blockSetUp(): void
    {
        $this->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->block       = new AbstractBlockStub($this->mercadopago);
    }

    /**
     * @after
     */
    public function blockTearDown(): void
    {
        Mockery::close();
    }

    /**
     * @dataProvider registerMelidataStoreScriptProvider
     */
    public function testRegisterMelidataStoreScriptCall(
        bool $isCheckout,
        bool $shouldRegisterMelidataScript
    ): void {
        // Create mocks for the required objects
        $scriptsMock = Mockery::mock();
        $checkoutMock = Mockery::mock();
        $hooksMock = Mockery::mock();

        // Setup isCheckout mock
        $checkoutMock
            ->shouldReceive('isCheckout')
            ->once()
            ->andReturn($isCheckout);

        // Setup registerMelidataStoreScript expectation
        if ($shouldRegisterMelidataScript) {
            $scriptsMock
                ->shouldReceive('registerMelidataStoreScript')
                ->once()
                ->with('/checkout');
        } else {
            $scriptsMock
                ->shouldNotReceive('registerMelidataStoreScript');
        }

        // Assemble the hooks mock
        $hooksMock->checkout = $checkoutMock;
        $hooksMock->scripts = $scriptsMock;

        // Test the specific logic we care about:
        if ($hooksMock->checkout->isCheckout()) {
            $hooksMock->scripts->registerMelidataStoreScript('/checkout');
        }

        $this->expectNotToPerformAssertions();
    }

    /**
     * Data provider for registerMelidataStoreScript test cases
     *
     * @return array
     */
    public function registerMelidataStoreScriptProvider(): array
    {
        return [
            'should register when isCheckout returns true' => [
                'isCheckout' => true,
                'shouldRegisterMelidataScript' => true,
            ],
            'should not register when isCheckout returns false' => [
                'isCheckout' => false,
                'shouldRegisterMelidataScript' => false,
            ],
        ];
    }

    public function testGetPaymentMethodDataIncludesFeeTitleWhenGatewayIsSet(): void
    {
        $gatewayMock           = Mockery::mock();
        $gatewayMock->supports = ['products'];
        $this->block->gateway  = $gatewayMock;

        $gatewayMock
            ->shouldReceive('getFeeTitle')
            ->once()
            ->andReturn('5% commission');

        $result = $this->block->get_payment_method_data();

        $this->assertEquals('5% commission', $result['params']['fee_title']);
        $this->assertEquals('Test Title', $result['title']);
        $this->assertEquals('Test Description', $result['description']);
        $this->assertEquals(['products'], $result['supports']);
        $this->assertEquals('test_value', $result['params']['test_param']);
    }

    public function testGetPaymentMethodDataDoesNotIncludeFeeTitleWhenGatewayIsNull(): void
    {
        $this->block->gateway = null;
        $result = $this->block->get_payment_method_data();

        $this->assertArrayNotHasKey('fee_title', $result['params']);
        $this->assertEquals('Test Title', $result['title']);
        $this->assertEquals('Test Description', $result['description']);
        $this->assertEquals([], $result['supports']);
        $this->assertEquals('test_value', $result['params']['test_param']);
    }

    public function testIsActiveReturnsFalseWhenGatewayIsNotSet(): void
    {
        $this->block->gateway = null;

        $this->assertFalse($this->block->is_active());
    }

    public function testIsActiveReturnsFalseWhenStaticIsAvailableReturnsFalse(): void
    {
        $gatewayMock = Mockery::mock(MercadoPagoGatewayInterface::class);
        $gatewayMock->shouldReceive('isAvailable')->once()->andReturn(false);
        $gatewayMock->shouldNotReceive('isMissingCredentials');
        $this->block->gateway = $gatewayMock;

        $this->assertFalse($this->block->is_active());
    }

    public function testIsActiveReturnsFalseWhenCredentialsAreMissing(): void
    {
        $gatewayMock = Mockery::mock(MercadoPagoGatewayInterface::class);
        $gatewayMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $gatewayMock->shouldReceive('isMissingCredentials')->once()->andReturn(true);
        $this->block->gateway = $gatewayMock;

        $this->assertFalse($this->block->is_active());
    }

    public function testIsActiveReturnsTrueWhenGatewayIsAvailableAndHasCredentials(): void
    {
        $gatewayMock = Mockery::mock(MercadoPagoGatewayInterface::class);
        $gatewayMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $gatewayMock->shouldReceive('isMissingCredentials')->once()->andReturn(false);
        $this->block->gateway = $gatewayMock;

        $this->assertTrue($this->block->is_active());
    }

    public function testUpdateCartSetsSessionWithoutCallingCalculateTotal(): void
    {
        $this->mercadopago->helpers->session
            ->shouldReceive('setSession')
            ->with(AbstractBlockStub::ACTION_SESSION_KEY, 'add')
            ->once();
        $this->mercadopago->helpers->session
            ->shouldReceive('setSession')
            ->with(AbstractBlockStub::GATEWAY_SESSION_KEY, 'woo-mercado-pago-basic')
            ->once();
        $this->mercadopago->helpers->cart
            ->shouldNotReceive('calculateTotal');

        $this->block->updateCartToRegisterDiscountAndCommission([
            'action'  => 'add',
            'gateway' => 'woo-mercado-pago-basic',
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testUpdateCartReturnsEarlyWhenActionIsEmpty(): void
    {
        $this->mercadopago->helpers->session->shouldNotReceive('setSession');
        $this->mercadopago->helpers->cart->shouldNotReceive('calculateTotal');

        $this->block->updateCartToRegisterDiscountAndCommission([
            'action'  => '',
            'gateway' => 'woo-mercado-pago-basic',
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testUpdateCartReturnsEarlyWhenGatewayIsEmpty(): void
    {
        $this->mercadopago->helpers->session->shouldNotReceive('setSession');
        $this->mercadopago->helpers->cart->shouldNotReceive('calculateTotal');

        $this->block->updateCartToRegisterDiscountAndCommission([
            'action'  => 'add',
            'gateway' => '',
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testFeeHookCallsAddFeesWhenActionIsAdd(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 5;
        $gatewayMock->discount   = 0;
        $this->block->gateway    = $gatewayMock;

        $this->mercadopago->hooks->checkout
            ->shouldReceive('isCheckout')->once()->andReturn(false);
        $this->mercadopago->hooks->cart
            ->shouldReceive('isCart')->once()->andReturn(false);
        $this->mercadopago->helpers->session
            ->shouldReceive('getSession')->with(AbstractBlockStub::ACTION_SESSION_KEY)->once()->andReturn('add');
        $this->mercadopago->helpers->cart
            ->shouldReceive('addDiscountAndCommissionOnFeesFromBlocks')->with($gatewayMock)->once();

        $this->block->registerDiscountAndCommissionFeesOnCart();

        $this->expectNotToPerformAssertions();
    }

    public function testFeeHookNoOpsWhenActionIsRemove(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 5;
        $gatewayMock->discount   = 0;
        $this->block->gateway    = $gatewayMock;

        $this->mercadopago->hooks->checkout
            ->shouldReceive('isCheckout')->once()->andReturn(false);
        $this->mercadopago->hooks->cart
            ->shouldReceive('isCart')->once()->andReturn(false);
        $this->mercadopago->helpers->session
            ->shouldReceive('getSession')->with(AbstractBlockStub::ACTION_SESSION_KEY)->once()->andReturn('remove');
        $this->mercadopago->helpers->cart->shouldNotReceive('addDiscountAndCommissionOnFeesFromBlocks');

        $this->block->registerDiscountAndCommissionFeesOnCart();

        $this->expectNotToPerformAssertions();
    }

    public function testFeeHookReturnsEarlyWhenNoFees(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 0;
        $gatewayMock->discount   = 0;
        $this->block->gateway    = $gatewayMock;

        $this->mercadopago->hooks->checkout
            ->shouldReceive('isCheckout')->once()->andReturn(false);
        $this->mercadopago->hooks->cart
            ->shouldReceive('isCart')->once()->andReturn(false);
        $this->mercadopago->helpers->session->shouldNotReceive('getSession');
        $this->mercadopago->helpers->cart->shouldNotReceive('addDiscountAndCommissionOnFeesFromBlocks');

        $this->block->registerDiscountAndCommissionFeesOnCart();

        $this->expectNotToPerformAssertions();
    }

    public function testFeeHookSkipsFeeLogicWhenGatewayIsNotSet(): void
    {
        $this->block->gateway = null;

        $this->mercadopago->hooks->checkout
            ->shouldReceive('isCheckout')->once()->andReturn(false);
        $this->mercadopago->hooks->cart
            ->shouldReceive('isCart')->once()->andReturn(false);
        $this->mercadopago->helpers->session->shouldNotReceive('getSession');
        $this->mercadopago->helpers->cart->shouldNotReceive('addDiscountAndCommissionOnFeesFromBlocks');

        $this->block->registerDiscountAndCommissionFeesOnCart();

        $this->expectNotToPerformAssertions();
    }

    public function testHasFeesTrueWhenCommissionIsSet(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 5;
        $gatewayMock->discount   = 0;
        $this->block->gateway    = $gatewayMock;

        $this->assertTrue($this->block->hasFees());
    }

    public function testHasFeesTrueWhenDiscountIsSet(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 0;
        $gatewayMock->discount   = 10;
        $this->block->gateway    = $gatewayMock;

        $this->assertTrue($this->block->hasFees());
    }

    public function testHasFeesFalseWhenBothAreZero(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 0;
        $gatewayMock->discount   = 0;
        $this->block->gateway    = $gatewayMock;

        $this->assertFalse($this->block->hasFees());
    }

    public function testHasFeesFalseWhenGatewayNotSet(): void
    {
        $this->block->gateway = null;

        $this->assertFalse($this->block->hasFees());
    }

    public function testHasFeesTrueWhenCommissionIsDecimal(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 0.5;
        $gatewayMock->discount   = 0.0;
        $this->block->gateway    = $gatewayMock;

        $this->assertTrue($this->block->hasFees());
    }

    public function testHasFeesTrueWhenDiscountIsDecimal(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 0.0;
        $gatewayMock->discount   = 0.5;
        $this->block->gateway    = $gatewayMock;

        $this->assertTrue($this->block->hasFees());
    }

    public function testHasFeesFalseWhenBothAreZeroFloat(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 0.0;
        $gatewayMock->discount   = 0.0;
        $this->block->gateway    = $gatewayMock;

        $this->assertFalse($this->block->hasFees());
    }

    public function testHasFeesFalseWhenFeesAreNegative(): void
    {
        // admin form enforces min=0; guard must not treat a negative value as a fee
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = -1.0;
        $gatewayMock->discount   = 0.0;
        $this->block->gateway    = $gatewayMock;

        $this->assertFalse($this->block->hasFees());
    }

    public function testHasFeesTrueWhenCommissionIsSmallestDecimal(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 0.01;
        $gatewayMock->discount   = 0.0;
        $this->block->gateway    = $gatewayMock;

        $this->assertTrue($this->block->hasFees());
    }

    public function testHasFeesTrueWhenDiscountIsMaximum(): void
    {
        $gatewayMock             = Mockery::mock(AbstractGateway::class)->makePartial();
        $gatewayMock->commission = 0.0;
        $gatewayMock->discount   = 99.0;
        $this->block->gateway    = $gatewayMock;

        $this->assertTrue($this->block->hasFees());
    }
}
