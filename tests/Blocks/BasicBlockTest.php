<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use MercadoPago\Woocommerce\Blocks\BasicBlock;
use MercadoPago\Woocommerce\Gateways\BasicGateway;
use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperty;

if (!defined('ABSPATH')) {
    exit;
}

class BasicBlockTest extends BlockTestCase
{
    use SetNotAccessibleProperty;

    protected string $blockClass = BasicBlock::class;
    protected string $gatewayClass = BasicGateway::class;
    protected string $siteId = 'MLB';

    public function testConstructedBlockSelectsBasicGatewayAndRendersItsConfiguredData(): void
    {
        $params = $this->assertIdentityAndDataEnvelope('woo-mercado-pago-basic');
        $this->assertSame('https://fixture.test/method.svg', $params['icon']);
        $this->assertStringContainsString('id="mp-checkout-pro-root"', $params['content']);
        $this->assertStringContainsString('mp-checkout-pro-redirect-title', $params['content']);
        $this->assertStringContainsString('payment-methods/pix.svg', $params['content']);
        $this->assertStringNotContainsString('<test-mode', $params['content']);
        $this->assertTemplateUsed('/public/checkouts/basic-checkout-container.php');
    }

    public function testBasicScriptUsesItsOwnAssetAndWooCommerceDependencies(): void
    {
        $this->assertScriptContract('wc_mercadopago_basic_blocks', 'basic.block.js', 'basic.block.asset.php');
    }

    public function testGatewayWithCredentialsIsActive(): void
    {
        $this->assertTrue($this->block->is_active());
    }

    public function testMissingGatewayDoesNotRegisterScript(): void
    {
        $this->assertGatewayAbsenceDisablesBlock();
    }

    /** @dataProvider credentialProvider */
    public function testMissingCredentialDisablesBasic(string $option, bool $testMode): void
    {
        $this->assertMissingCredentialDisablesBlock($option, $testMode);
    }

    public function testInitializeUsesEmptyDefaultsWhenSettingsAreAbsent(): void
    {
        unset($this->options['woocommerce_woo-mercado-pago-basic_settings']);
        $this->block->initialize();
        $data = $this->block->get_payment_method_data();
        $this->assertSame('', $data['title']);
        $this->assertSame('', $data['description']);
    }

    public function testModalConfigurationDoesNotRenderRedirectCopy(): void
    {
        $this->gateway->settings['method'] = 'modal';
        $params = $this->block->getScriptParams();
        $this->assertStringContainsString('id="mp-checkout-pro-root"', $params['content']);
        $this->assertStringNotContainsString('mp-checkout-pro-redirect-title', $params['content']);
    }

    /** @dataProvider creditsBenefitProvider */
    public function testCreditsBenefitIsShownOnlyForBrazil(string $site, string $expected): void
    {
        $this->setSite($site);
        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', $this->plugin->helpers->country->getCountryConfigs());
        $params = $this->gateway->getPaymentFieldsParams();
        $this->assertSame($expected, $params['i18n']['second_benefit_description']);
        $this->assertStringContainsString($expected, $this->block->getScriptParams()['content']);
    }

    public function creditsBenefitProvider(): array
    {
        $generic = '<b>Buy safely</b> with your preferred payment method.';
        return [
            'MLB in English' => ['MLB', '<b>You have Buy now, pay later</b> to buy now and pay later.'],
            'MLA' => ['MLA', $generic],
            'MLM' => ['MLM', $generic],
            'MLC' => ['MLC', $generic],
        ];
    }
}
