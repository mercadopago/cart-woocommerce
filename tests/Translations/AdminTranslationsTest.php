<?php

namespace MercadoPago\Woocommerce\Tests\Translations;

use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class AdminTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('__')->andReturnArg(0);
        WP_Mock::userFunction('_x')->andReturnArg(0);
        WP_Mock::userFunction('wp_get_current_user')->andReturn((object) ['display_name' => 'Fixture seller']);
        WP_Mock::userFunction('get_woocommerce_currency')->andReturn('BRL');
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testEnglishSecondCreditsBannerPreservesLearnMoreAndOtherOptions(): void
    {
        $links = Mockery::mock(Links::class);
        $links->shouldReceive('getLinks')->andReturn(array_fill_keys([
            'docs_ipn_notification', 'docs_developers_program', 'mercadopago_credentials',
            'mercadopago_test_user', 'docs_test_cards', 'store_visit', 'docs_support_faq', 'mercadopago_support',
        ], 'https://fixture.test/'));

        $copy = (new AdminTranslations($links))->creditsGatewaySettings;

        $this->assertSame('<b>Buy now, pay later</b> with Mercado Pago. Learn more', $copy['tooltip_component_option2']);
        $this->assertSame('<b>Up to 12 installments</b> through {credits_promotion_name}. Learn more', $copy['tooltip_component_option1']);
        $this->assertSame('With {credits_alternative_name}, <b>get it now and pay month by month</b>.  Learn more', $copy['tooltip_component_option3']);
        $this->assertSame('<b>Pay in up to 12 installments</b> without credit card. Learn more', $copy['tooltip_component_option4']);
        $this->assertSame('Buy now, pay later', $copy['gateway_title']);
    }
}
