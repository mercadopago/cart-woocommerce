<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use MercadoPago\Woocommerce\Blocks\CreditsBlock;
use MercadoPago\Woocommerce\Gateways\CreditsGateway;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsBlockTest extends BlockTestCase
{
    protected string $blockClass = CreditsBlock::class;
    protected string $gatewayClass = CreditsGateway::class;
    protected string $siteId = 'MLA';

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeGatewayTitle();
    }

    private function initializeGatewayTitle(string $localizedDefault = 'Buy now, pay later'): void
    {
        $this->gateway->adminTranslations = ['gateway_title' => $localizedDefault];
        $settings = $this->options['woocommerce_woo-mercado-pago-credits_settings'];
        if (array_key_exists('title', $settings)) {
            $this->gateway->settings['title'] = $settings['title'];
        } else {
            unset($this->gateway->settings['title']);
        }
        $configuredTitle = new \ReflectionMethod(CreditsGateway::class, 'getConfiguredCheckoutTitle');
        $configuredTitle->setAccessible(true);
        $this->gateway->title = $configuredTitle->invoke($this->gateway);
    }

    /** @dataProvider localizedTitleProvider */
    public function testClassicAndBlocksUseTheTranslatedDefaultRegardlessOfSite(string $site, string $name): void
    {
        $this->setSite($site);
        $this->options['woocommerce_woo-mercado-pago-credits_settings']['title'] = 'Checkout without card';
        $this->initializeGatewayTitle($name);
        $this->block->initialize();

        $this->assertSame($name, $this->gateway->title);
        $this->assertSame($name, $this->block->get_payment_method_data()['title']);
        $this->assertSame('Checkout without card', $this->options['woocommerce_woo-mercado-pago-credits_settings']['title']);
    }

    public function localizedTitleProvider(): array
    {
        $cases = [];
        foreach (['MLA', 'MLM', 'MLB'] as $site) {
            foreach (['es' => 'Mercado Crédito', 'es_MX' => 'Mercado Crédito', 'pt_BR' => 'Mercado Parcelado', 'en_US' => 'Buy now, pay later'] as $locale => $name) {
                $cases["$site/$locale"] = [$site, $name];
            }
        }
        return $cases;
    }

    /**
     * @testWith ["Checkout without card", "Buy now, pay later"]
     *           ["Mercado Parcelado", "Mercado Parcelado"]
     *           ["", "Buy now, pay later"]
     *           [null, "Buy now, pay later"]
     *           [["unexpected"], "Buy now, pay later"]
     *           ["0", "0"]
     *           ["Custom title", "Custom title"]
     */
    public function testTitleOptionReadsArePresentationOnly($stored, string $expected): void
    {
        $this->gateway->settings['title'] = $stored;
        $before = $this->gateway->settings;
        $this->assertSame($expected, $this->gateway->get_option('title'));
        $this->assertSame($before, $this->gateway->settings);
        $this->assertSame('fallback', $this->gateway->get_option('unrelated_option', 'fallback'));
    }

    public function testCreditsTemplateAndIconComeFromItsRealGateway(): void
    {
        $params = $this->assertIdentityAndDataEnvelope('woo-mercado-pago-credits');
        $this->assertSame('https://fixture.test/method.svg', $params['icon']);
        $this->assertStringContainsString('id="mp-checkout-credits-root"', $params['content']);
        $this->assertStringContainsString('mp-credits-checkout-benefits', $params['content']);
        $this->assertStringNotContainsString('id="mp-checkout-pro-root"', $params['content']);
        $this->assertTemplateUsed('/public/checkouts/credits-checkout-container.php');
    }

    public function testCreditsScriptUsesItsOwnAsset(): void
    {
        $this->assertScriptContract('wc_mercadopago_credits_blocks', 'credits.block.js', 'credits.block.asset.php');
    }

    public function testSavedLegacyTitleIsResolvedForEnglishWithoutChangingOtherBlockData(): void
    {
        $this->options['woocommerce_woo-mercado-pago-credits_settings']['title'] = 'Hasta 12 pagos sin tarjeta con Mercado Pago';
        $this->initializeGatewayTitle();
        $this->block->initialize();
        $data = $this->block->get_payment_method_data();
        $this->assertSame('Buy now, pay later', $data['title']);
        $this->assertSame('Configured method description', $data['description']);
        $this->assertSame(['products', 'refunds'], $data['supports']);
        $this->assertSame('https://fixture.test/method.svg', $data['params']['icon']);
        $this->assertSame('Hasta 12 pagos sin tarjeta con Mercado Pago', $this->options['woocommerce_woo-mercado-pago-credits_settings']['title']);
    }

    public function testEmptySavedTitleDoesNotFallBackToCheckoutCreditsInBlocks(): void
    {
        $this->options['title'] = 'My legacy credit method';
        $this->options['woocommerce_woo-mercado-pago-credits_settings']['title'] = '';
        $this->initializeGatewayTitle();
        $this->block->initialize();
        $this->assertSame('Buy now, pay later', $this->block->get_payment_method_data()['title']);
    }

    /** @dataProvider unexpectedTitleProvider */
    public function testUnexpectedSavedTitleDoesNotBreakClassicOrBlocks($title): void
    {
        $this->options['woocommerce_woo-mercado-pago-credits_settings']['title'] = $title;
        $this->initializeGatewayTitle();
        $this->assertSame('Buy now, pay later', $this->gateway->title);
        $this->block->initialize();
        $this->assertSame('Buy now, pay later', $this->block->get_payment_method_data()['title']);
        $this->assertSame($title, $this->options['woocommerce_woo-mercado-pago-credits_settings']['title']);
    }

    public function unexpectedTitleProvider(): array
    {
        return [
            'null' => [null],
            'array' => [['unexpected value']],
            'object' => [(object) ['unexpected' => 'value']],
        ];
    }

    public function testNumericZeroCustomTitleRemainsConsistentInClassicAndBlocks(): void
    {
        $this->options['woocommerce_woo-mercado-pago-credits_settings']['title'] = '0';
        $this->initializeGatewayTitle();
        $this->assertSame('0', $this->gateway->title);
        $this->block->initialize();
        $this->assertSame('0', $this->block->get_payment_method_data()['title']);
        $this->assertSame('0', $this->options['woocommerce_woo-mercado-pago-credits_settings']['title']);
    }

    public function testLegacyGlobalCustomTitleIsPreservedInClassicAndBlocks(): void
    {
        $this->options['title'] = 'My legacy credit method';
        unset($this->options['woocommerce_woo-mercado-pago-credits_settings']['title']);
        $this->initializeGatewayTitle();
        $this->assertSame('My legacy credit method', $this->gateway->title);
        $this->block->initialize();
        $this->assertSame($this->gateway->title, $this->block->get_payment_method_data()['title']);
        $this->assertArrayNotHasKey('title', $this->options['woocommerce_woo-mercado-pago-credits_settings']);
    }

    public function testConsumerCreditsCapabilityEnablesBlock(): void
    {
        $this->assertTrue($this->block->is_active());
    }

    public function testOtherPaymentCapabilityDoesNotEnableCredits(): void
    {
        $this->options['_site_id_payment_methods'] = [['id' => 'visa']];
        $this->assertFalse($this->block->is_active());
    }

    public function testCapabilityAlsoEnablesCreditsOutsideArgentina(): void
    {
        $this->setSite('MLM');
        $this->assertTrue($this->block->is_active());
    }

    public function testMissingGatewayDoesNotRegisterScript(): void
    {
        $this->assertGatewayAbsenceDisablesBlock();
    }

    /** @dataProvider credentialProvider */
    public function testMissingCredentialDisablesCredits(string $option, bool $testMode): void
    {
        $this->assertMissingCredentialDisablesBlock($option, $testMode);
    }

    public function testTestModeAppearsInTheRealCreditsTemplate(): void
    {
        $this->options['checkbox_checkout_test_mode'] = 'yes';
        $params = $this->block->getScriptParams();
        $this->assertStringContainsString('<test-mode', $params['content']);
        $this->assertStringContainsString('link-src="https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-test"', $params['content']);
    }
}
