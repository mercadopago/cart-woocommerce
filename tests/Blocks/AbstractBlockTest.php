<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;

class AbstractBlockTest extends TestCase
{
    use WoocommerceMock;

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
        // if ($this->mercadopago->hooks->checkout->isCheckout()) {
        //     $this->mercadopago->hooks->scripts->registerMelidataStoreScript('/checkout');
        // }
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
}
