<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use MercadoPago\Woocommerce\Blocks\PixBlock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use Mockery;
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    exit;
}

class PixBlockCurrencyRatioStub extends PixBlock
{
    public $gateway = null;

    public function __construct(WoocommerceMercadoPago $mercadopago)
    {
        $this->mercadopago = $mercadopago;
        $this->name        = 'woo-mercado-pago-pix';
    }

    protected function getCurrencyRatio(?string $gateway_id = '')
    {
        return 1.5;
    }
}

class PixBlockCurrencyRatioTest extends TestCase
{
    private PixBlockCurrencyRatioStub $block;

    /**
     * @before
     */
    public function blockSetUp(): void
    {
        $this->block = new PixBlockCurrencyRatioStub(MercadoPagoMock::getWoocommerceMercadoPagoMock());
    }

    /**
     * @after
     */
    public function blockTearDown(): void
    {
        Mockery::close();
    }

    public function testGetScriptParamsPreservesGatewayFieldsAndAddsCurrencyRatio(): void
    {
        $gateway = Mockery::mock();
        $gateway
            ->shouldReceive('getPaymentFieldsParams')
            ->once()
            ->andReturn(['amount' => '100.00']);
        $this->block->gateway = $gateway;

        $this->assertSame([
            'amount'         => '100.00',
            'currencyRatio'  => 1.5,
        ], $this->block->getScriptParams());
    }
}
