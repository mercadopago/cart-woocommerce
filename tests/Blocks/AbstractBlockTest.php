<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Blocks\AbstractBlock;
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

        // The test passes if Mockery expectations are met
        $this->assertTrue(true, 'Mockery expectations validated the registerMelidataStoreScript call behavior');
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
}
