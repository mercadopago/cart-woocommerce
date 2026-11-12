<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WC_Order;
use WP_Mock;

class SmartPixAdminPaymentTitleTest extends TestCase
{
    private Order $hooks;
    private OrderMetadata $metadata;
    private bool $hadOrder;
    /** @var mixed */
    private $previousOrder;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->hadOrder = array_key_exists('theorder', $GLOBALS);
        $this->previousOrder = $GLOBALS['theorder'] ?? null;
        unset($GLOBALS['theorder']);
        $reflection = new ReflectionClass(Order::class);
        $this->hooks = $reflection->newInstanceWithoutConstructor();
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $translations = Mockery::mock(StoreTranslations::class);
        $translations->orderStatus = ['smart_transfer' => 'Translated Pix via Open Finance'];
        foreach (['orderMetadata' => $this->metadata, 'storeTranslations' => $translations] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($this->hooks, $value);
        }
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
    }

    protected function tearDown(): void
    {
        if ($this->hadOrder) {
            $GLOBALS['theorder'] = $this->previousOrder;
        } else {
            unset($GLOBALS['theorder']);
        }
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider editorProvider */
    public function testGivenASmartPixOrderWhenEitherEditorReadsItsGatewayTitleThenItUsesTheTranslationRegardlessOfStatus(string $screen): void
    {
        $this->editor($screen);
        $order = $this->nativeOrder();
        $order->shouldNotReceive('get_status', 'is_paid', 'update_status', 'save');
        $this->metadata->shouldReceive('getPaymentContract')->once()->with($order)
            ->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);

        $this->assertSame('Translated Pix via Open Finance', $this->hooks->adminPaymentMethodTitle('Pix', PixGateway::ID));
    }

    public static function editorProvider(): array
    {
        return [['shop_order'], ['woocommerce_page_wc-orders']];
    }

    /** @dataProvider editorProvider */
    public function testGivenConventionalPixWhenEditingTheOrderThenItsConfiguredNameIsPreserved(string $screen): void
    {
        $this->editor($screen);
        $order = $this->nativeOrder();
        $this->metadata->shouldReceive('getPaymentContract')->once()->with($order)->andReturn('');
        $this->assertSame('Configured Pix', $this->hooks->adminPaymentMethodTitle('Configured Pix', PixGateway::ID));
    }

    public function testGivenACardGatewayWhenReadingItsTitleThenSmartPixDoesNotInspectOrRenameIt(): void
    {
        $this->metadata->shouldNotReceive('getPaymentContract');
        $this->assertSame('Configured card', $this->hooks->adminPaymentMethodTitle('Configured card', 'woo-mercado-pago-custom'));
    }

    public function testGivenACardOrderWhenThePixGatewayTitleIsReadThenItKeepsItsName(): void
    {
        $this->editor('woocommerce_page_wc-orders');
        $this->nativeOrder('woo-mercado-pago-custom');
        $this->metadata->shouldNotReceive('getPaymentContract');
        $this->assertSame('Pix', $this->hooks->adminPaymentMethodTitle('Pix', PixGateway::ID));
    }

    /**
     * Another gateway or plugin can hand this shared filter a null title or a non-string ID:
     * the value must pass through untouched instead of raising a TypeError on checkout.
     *
     * @dataProvider untypedFilterArgumentsProvider
     * @param mixed $title
     * @param mixed $gatewayId
     */
    public function testGivenUntypedFilterArgumentsWhenReadingAGatewayTitleThenTheyPassThroughUnchanged($title, $gatewayId): void
    {
        $this->editor('woocommerce_page_wc-orders');
        $this->nativeOrder();
        $this->metadata->shouldNotReceive('getPaymentContract');
        $this->assertSame($title, $this->hooks->adminPaymentMethodTitle($title, $gatewayId));
    }

    public static function untypedFilterArgumentsProvider(): array
    {
        return [
            'null title from another gateway' => [null, 'another-gateway'],
            'null title for Pix' => [null, PixGateway::ID],
            'non-string gateway id' => ['Pix', 42],
            'missing gateway id' => ['Pix', null],
        ];
    }

    /**
     * @dataProvider untypedOrderTitleProvider
     * @param mixed $title
     * @param mixed $order
     */
    public function testGivenUntypedOrderTitleArgumentsWhenFilteringThenTheyPassThroughUnchanged($title, $order): void
    {
        $this->metadata->shouldNotReceive('getPaymentContract');
        $this->assertSame($title, $this->hooks->paymentMethodTitle($title, $order === 'order' ? $this->nativeOrder() : $order));
    }

    public static function untypedOrderTitleProvider(): array
    {
        return [
            'null title of a Smart Pix order' => [null, 'order'],
            'non-order object' => ['Pix', null],
            'refund-like object' => ['Pix', 'not an order'],
        ];
    }

    /** @dataProvider unrelatedContextProvider */
    public function testGivenAnUnrelatedContextWhenReadingThePixTitleThenItKeepsItsName(string $context): void
    {
        WP_Mock::userFunction('is_admin', ['return' => $context !== 'checkout']);
        if ($context !== 'checkout') {
            WP_Mock::userFunction('wp_doing_ajax', ['return' => $context === 'ajax']);
        }
        if (!in_array($context, ['checkout', 'ajax'], true)) {
            $id = $context === 'settings' ? 'woocommerce_page_wc-settings' : 'woocommerce_page_wc-orders';
            WP_Mock::userFunction('get_current_screen', ['return' => $context === 'no screen' ? null : (object) ['id' => $id]]);
        }
        if (!in_array($context, ['no order', 'invalid order'], true)) {
            $this->nativeOrder(PixGateway::ID, $context === 'new order' ? 0 : 205);
        } elseif ($context === 'invalid order') {
            $GLOBALS['theorder'] = new \stdClass();
        }
        $this->metadata->shouldNotReceive('getPaymentContract');
        $this->assertSame('Pix', $this->hooks->adminPaymentMethodTitle('Pix', PixGateway::ID));
    }

    public static function unrelatedContextProvider(): array
    {
        return [['checkout'], ['ajax'], ['no screen'], ['settings'], ['no order'], ['invalid order'], ['new order']];
    }

    private function editor(string $screen): void
    {
        WP_Mock::userFunction('is_admin', ['return' => true]);
        WP_Mock::userFunction('wp_doing_ajax', ['return' => false]);
        WP_Mock::userFunction('get_current_screen', ['return' => (object) ['id' => $screen]]);
    }

    private function nativeOrder(string $gateway = PixGateway::ID, int $id = 205): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($id);
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn($gateway);
        $GLOBALS['theorder'] = $order;
        return $order;
    }
}
