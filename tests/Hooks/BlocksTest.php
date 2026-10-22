<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Hooks\Blocks;
use MercadoPago\Woocommerce\Blocks\AbstractBlock;
use MercadoPago\Woocommerce\Blocks\BasicBlock;
use MercadoPago\Woocommerce\Helpers;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

class BlocksTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testCheckoutScriptsActionPreservesTheCallbackAndPlatformDefaults(): void
    {
        $callback = [$this->block(), 'resetCheckoutSession'];
        $registered = false;
        WP_Mock::onActionAdded('woocommerce_blocks_enqueue_checkout_block_scripts_before')
            ->with($callback, 10, 1)
            ->perform(function () use (&$registered) {
                $registered = true;
            });

        (new Blocks())->registerBlocksEnqueueCheckoutScriptsBefore($callback);

        $this->assertTrue($registered, 'The real Blocks hook must register before checkout scripts are enqueued.');
    }

    public function testStoreApiRegistrationPreservesNamespaceAndInvocableRealCallback(): void
    {
        $callback = [$this->block(), 'updateCartToRegisterDiscountAndCommission'];
        $registration = null;
        WP_Mock::userFunction('woocommerce_store_api_register_update_callback', [
            'return' => function ($receivedRegistration) use (&$registration) {
                $registration = $receivedRegistration;
            },
        ]);

        (new Blocks())->registerBlocksUpdated('mercadopago_blocks_update_cart', $callback);

        $this->assertSame(['namespace' => 'mercadopago_blocks_update_cart', 'callback' => $callback], $registration);
        $writes = [];
        $session = Mockery::mock('WC_Session');
        $session->shouldReceive('set')->twice()
            ->andReturnUsing(function ($key, $value) use (&$writes) {
                $writes[] = [$key, $value];
            });
        WP_Mock::userFunction('WC', ['return' => (object) ['session' => $session]]);

        // Execute the actual production callback through real AbstractBlock and Session code.
        ($registration['callback'])(['action' => 'add', 'gateway' => 'woo-mercado-pago-basic']);

        $this->assertSame([
            ['mercadopago_blocks_action', 'add'],
            ['mercadopago_blocks_gateway', 'woo-mercado-pago-basic'],
        ], $writes);
    }

    private function block(): BasicBlock
    {
        // Skip unrelated constructor wiring; the callback and session helper remain real.
        $helpers = (new ReflectionClass(Helpers::class))->newInstanceWithoutConstructor();
        $helpers->session = new Session();
        $mercadopago = (new ReflectionClass(WoocommerceMercadoPago::class))->newInstanceWithoutConstructor();
        $mercadopago->helpers = $helpers;
        $block = (new ReflectionClass(BasicBlock::class))->newInstanceWithoutConstructor();
        $property = new ReflectionProperty(AbstractBlock::class, 'mercadopago');
        $property->setAccessible(true);
        $property->setValue($block, $mercadopago);

        return $block;
    }
}
