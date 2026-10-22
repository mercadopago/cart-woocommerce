<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use MercadoPago\Woocommerce\Blocks\YapeBlock;
use MercadoPago\Woocommerce\Gateways\YapeGateway;

if (!defined('ABSPATH')) {
    exit;
}

class YapeBlockTest extends BlockTestCase
{
    protected string $blockClass = YapeBlock::class;
    protected string $gatewayClass = YapeGateway::class;
    protected string $siteId = 'MPE';

    public function testYapePreservesRealPhoneCodeAndNoticeParameters(): void
    {
        $params = $this->assertIdentityAndDataEnvelope('woo-mercado-pago-yape');
        $this->assertSame('https://fixture.test/method.svg', $params['checkout_blocks_row_image_src']);
        $this->assertSame('https://fixture.test/plugin/assets/images/checkouts/yape/yape-tooltip-icon.svg?ver=' . MP_VERSION, $params['input_code_icon']);
        $this->assertSame('https://fixture.test/plugin/assets/images/checkouts/yape/checkout-notice-icon.png?ver=' . MP_VERSION, $params['checkout_notice_icon_one']);
        $this->assertSame('Cell phone linked to Yape', $params['input_field_label']);
        $this->assertSame('Approval code', $params['input_code_label']);
        foreach (['input_field_label', 'input_code_label', 'yape_title', 'yape_subtitle',
            'yape_input_code_error_message1', 'yape_input_code_error_message2',
            'yape_phone_number_error_message1', 'yape_phone_number_error_message2'] as $key) {
            $this->assertArrayHasKey($key, $params);
            $this->assertNotSame('', $params[$key]);
        }
        $this->assertSame('https://www.mercadopago.com.pe/ayuda/terminos-y-politicas_194', $params['terms_and_conditions_link_src']);
        $this->assertFalse($params['test_mode']);
        $this->assertArrayNotHasKey('content', $params);
    }

    public function testYapeScriptUsesItsOwnAsset(): void
    {
        $this->assertScriptContract('wc_mercadopago_yape_blocks', 'yape.block.js', 'yape.block.asset.php');
    }

    /** @dataProvider countryProvider */
    public function testActivityUsesRealPeruRestriction(string $site, bool $active): void
    {
        $this->setSite($site);
        $this->assertSame($active, $this->block->is_active());
    }

    public function countryProvider(): array
    {
        return ['MPE' => ['MPE', true], 'MLA' => ['MLA', false], 'MLB' => ['MLB', false],
            'MLM' => ['MLM', false], 'MLC' => ['MLC', false], 'MLU' => ['MLU', false], 'MCO' => ['MCO', false]];
    }

    public function testMissingGatewayDoesNotRegisterScript(): void
    {
        $this->assertGatewayAbsenceDisablesBlock();
    }

    /** @dataProvider credentialProvider */
    public function testMissingCredentialDisablesYape(string $option, bool $testMode): void
    {
        $this->assertMissingCredentialDisablesBlock($option, $testMode);
    }
}
