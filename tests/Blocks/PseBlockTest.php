<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use MercadoPago\Woocommerce\Blocks\PseBlock;
use MercadoPago\Woocommerce\Gateways\PseGateway;

if (!defined('ABSPATH')) {
    exit;
}

class PseBlockTest extends BlockTestCase
{
    protected string $blockClass = PseBlock::class;
    protected string $gatewayClass = PseGateway::class;
    protected string $siteId = 'MCO';

    public function testPsePreservesRealInstitutionCurrencyAndPayerData(): void
    {
        $params = $this->assertIdentityAndDataEnvelope('woo-mercado-pago-pse');
        $this->assertSame('MCO', $params['site_id']);
        $this->assertSame(100.0, $params['amount']);
        $this->assertSame(1.25, $params['currency_ratio']);
        $this->assertSame('COP', $params['woocommerce_currency']);
        $this->assertSame('COP', $params['account_currency']['currency']);
        $this->assertSame('', $params['payer_email']);
        $this->assertSame([['id' => 'fixture-bank', 'description' => 'Fixture bank']], json_decode($params['financial_institutions'], true));
        $this->assertSame('https://fixture.test/method.svg', $params['checkout_blocks_row_image_src']);
        $this->assertSame('https://www.mercadopago.com.co/ayuda/terminos-y-politicas_194', $params['terms_and_conditions_link_src']);
        $this->assertArrayNotHasKey('content', $params);
    }

    public function testPseScriptUsesItsOwnAsset(): void
    {
        $this->assertScriptContract('wc_mercadopago_pse_blocks', 'pse.block.js', 'pse.block.asset.php');
    }

    /** @dataProvider countryProvider */
    public function testActivityUsesRealColombiaRestriction(string $site, bool $active): void
    {
        $this->setSite($site);
        $this->assertSame($active, $this->block->is_active());
    }

    public function countryProvider(): array
    {
        return ['MCO' => ['MCO', true], 'MLA' => ['MLA', false], 'MLB' => ['MLB', false],
            'MLM' => ['MLM', false], 'MLC' => ['MLC', false], 'MLU' => ['MLU', false], 'MPE' => ['MPE', false]];
    }

    public function testMissingGatewayDoesNotRegisterScript(): void
    {
        $this->assertGatewayAbsenceDisablesBlock();
    }

    /** @dataProvider credentialProvider */
    public function testMissingCredentialDisablesPse(string $option, bool $testMode): void
    {
        $this->assertMissingCredentialDisablesBlock($option, $testMode);
    }

    public function testEmptyInstitutionListRemainsAnEmptyJsonArray(): void
    {
        $this->options['_payment_methods_pse'] = [['id' => 'pse', 'financial_institutions' => []]];
        $params = $this->block->getScriptParams();
        $this->assertSame('[]', $params['financial_institutions']);
        $this->assertSame(100.0, $params['amount']);
    }
}
