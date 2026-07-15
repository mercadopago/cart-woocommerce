<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;
use MercadoPago\Woocommerce\Blocks\CustomBlock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MP_PLUGIN_FILE')) {
    define('MP_PLUGIN_FILE', dirname(__DIR__, 2) . '/woocommerce-mercadopago.php');
}

class CustomBlockStub extends CustomBlock
{
    public $gateway = null;

    public function __construct(WoocommerceMercadoPago $mercadopago)
    {
        $this->mercadopago       = $mercadopago;
        $this->links             = [];
        $this->storeTranslations = [];
        $this->settings          = [];
        $this->name              = 'woo-mercado-pago-custom';
    }

    protected function getCurrencyRatio(?string $gateway_id = '')
    {
        return 1.0;
    }
}

class CustomBlockTest extends TestCase
{
    use WoocommerceMock;

    private CustomBlockStub $block;

    /**
     * @before
     */
    public function blockSetUp(): void
    {
        $mercadopago   = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->block   = new CustomBlockStub($mercadopago);
    }

    /**
     * @after
     */
    public function blockTearDown(): void
    {
        Mockery::close();
    }

    public function testGetScriptParamsReturnsRequiredKeys(): void
    {
        $gatewayMock = Mockery::mock();
        $gatewayMock->icon = 'https://example.com/icon.png';
        $gatewayMock->shouldReceive('getPaymentFieldsParams')->once()->andReturn([]);
        $this->block->gateway = $gatewayMock;

        WP_Mock::userFunction('wc_get_template_html', [
            'return' => '<div>form</div>',
        ]);

        $result = $this->block->getScriptParams();

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('icon', $result);
        $this->assertArrayHasKey('currencyRatio', $result);
    }

    public function testGetScriptParamsReturnsCorrectIconAndRatio(): void
    {
        $gatewayMock = Mockery::mock();
        $gatewayMock->icon = 'https://example.com/mp-icon.svg';
        $gatewayMock->shouldReceive('getPaymentFieldsParams')->once()->andReturn([]);
        $this->block->gateway = $gatewayMock;

        WP_Mock::userFunction('wc_get_template_html', [
            'return' => '',
        ]);

        $result = $this->block->getScriptParams();

        $this->assertEquals('https://example.com/mp-icon.svg', $result['icon']);
        $this->assertEquals(1.0, $result['currencyRatio']);
    }

    public function testGetScriptParamsContentComesFromTemplate(): void
    {
        $gatewayMock = Mockery::mock();
        $gatewayMock->icon = '';
        $gatewayMock->shouldReceive('getPaymentFieldsParams')->once()->andReturn(['amount' => '100.00']);
        $this->block->gateway = $gatewayMock;

        WP_Mock::userFunction('wc_get_template_html', [
            'return' => '<div class="mp-checkout">checkout</div>',
        ]);

        $result = $this->block->getScriptParams();

        $this->assertEquals('<div class="mp-checkout">checkout</div>', $result['content']);
    }
}
