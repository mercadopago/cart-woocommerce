<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use MercadoPago\Woocommerce\Blocks\PixBlock;
use MercadoPago\Woocommerce\Gateways\PixGateway;

if (!defined('ABSPATH')) {
    exit;
}

class PixBlockTest extends BlockTestCase
{
    protected string $blockClass = PixBlock::class;
    protected string $gatewayClass = PixGateway::class;
    protected string $siteId = 'MLB';

    /** @dataProvider modeProvider */
    public function testPixPreservesRealPaymentFields(bool $testMode): void
    {
        $this->options['checkbox_checkout_test_mode'] = $testMode ? 'yes' : 'no';
        $params = $this->assertIdentityAndDataEnvelope('woo-mercado-pago-pix');
        $this->assertArrayHasKey('amount', $params);
        $this->assertSame(100.0, $params['amount']);
        $this->assertSame($testMode, $params['test_mode']);
        $this->assertSame('https://fixture.test/method.svg', $params['icon']);
        $this->assertSame('https://fixture.test/plugin/assets/images/checkouts/pix/pix.png?ver=' . MP_VERSION, $params['pix_template_src']);
        $this->assertNotSame('', $params['pix_template_title']);
        $this->assertSame('https://www.mercadopago.com.br/ajuda/termos-e-politicas_194', $params['terms_and_conditions_link_src']);
        $this->assertArrayHasKey('message_error_amount', $params);
        $this->assertArrayNotHasKey('content', $params);
    }

    public function testPixScriptUsesItsOwnAsset(): void
    {
        $this->assertScriptContract('wc_mercadopago_pix_blocks', 'pix.block.js', 'pix.block.asset.php');
    }

    /** @dataProvider countryProvider */
    public function testActivityUsesRealBrazilRestriction(string $site, bool $active): void
    {
        $this->setSite($site);
        $this->assertSame($active, $this->block->is_active());
    }

    public function countryProvider(): array
    {
        return ['MLB' => ['MLB', true], 'MLA' => ['MLA', false], 'MLM' => ['MLM', false],
            'MLC' => ['MLC', false], 'MLU' => ['MLU', false], 'MCO' => ['MCO', false], 'MPE' => ['MPE', false]];
    }

    public function modeProvider(): array
    {
        return ['production' => [false], 'test' => [true]];
    }

    public function testMissingGatewayDoesNotRegisterScript(): void
    {
        $this->assertGatewayAbsenceDisablesBlock();
    }

    /** @dataProvider credentialProvider */
    public function testMissingCredentialDisablesPix(string $option, bool $testMode): void
    {
        $this->assertMissingCredentialDisablesBlock($option, $testMode);
    }
}
