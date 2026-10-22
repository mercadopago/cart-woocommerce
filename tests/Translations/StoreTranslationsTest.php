<?php

namespace MercadoPago\Woocommerce\Tests\Translations;

use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class StoreTranslationsTest extends TestCase
{
    private const SUPER_TOKEN_CONTEXT = 'super token';

    private const CARD_CHECKOUT_CONTEXT = 'card checkout';

    public function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('__')->andReturnArg(0);
        WP_Mock::userFunction('_x')->andReturnUsing(fn($text, $context) => "$context|$text");
        WP_Mock::userFunction('get_locale')->andReturn('es_CO');
        WP_Mock::onFilter('plugin_locale')->with('es_CO', 'woocommerce-mercadopago')->reply('es_CO');
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testGivenStoreTranslationsWhenBuiltThenEverySuperTokenCopyUsesTheSuperTokenContext(): void
    {
        $translations = $this->buildStoreTranslations();

        $this->assertNotEmpty($translations->superTokenCheckout);
        foreach ($translations->superTokenCheckout as $key => $value) {
            $this->assertStringStartsWith(self::SUPER_TOKEN_CONTEXT, $value, "'$key' must be translated with a Super Token context");
        }
    }

    public function testGivenStoreTranslationsWhenBuiltThenSuperTokenV21CopyIsAvailable(): void
    {
        $translations = $this->buildStoreTranslations();

        foreach (['saved_cards_title', 'saved_card_title', 'mp_methods_title', 'account_money_balance_text', 'saved_payment_method_title'] as $key) {
            $this->assertArrayHasKey($key, $translations->superTokenCheckout);
        }
    }

    public function testGivenStoreTranslationsWhenBuiltThenCardCheckoutCopyUsesTheCardCheckoutContext(): void
    {
        $translations = $this->buildStoreTranslations();

        $cardCheckoutCopy = array_merge(
            [
                $translations->customCheckout['card_installments_label'],
                $translations->customCheckout['interest_free_option_text'],
                $translations->customCheckout['mercadopago_privacy_policy'],
                $translations->commonMessages['cho_form_error'],
                $translations->buyerRefusedMessages['buyer_cc_rejected_invalid_installments'],
            ],
            array_values($translations->checkoutErrorMessages),
            array_values($translations->checkoutErrorMessagesV2)
        );

        foreach ($cardCheckoutCopy as $value) {
            $this->assertStringStartsWith(self::CARD_CHECKOUT_CONTEXT, $value);
        }
        $this->assertStringStartsNotWith(self::CARD_CHECKOUT_CONTEXT, $translations->buyerRefusedMessages['buyer_yape_default']);
    }

    public function testGivenSpanishStoreLocaleWhenBuiltThenSdkLocaleFollowsTheStoreRegion(): void
    {
        $translations = $this->buildStoreTranslations();

        $this->assertSame('es-CO', $translations->customCheckout['locale']);
    }

    private function buildStoreTranslations(): StoreTranslations
    {
        $links = Mockery::mock(Links::class);
        $links->shouldReceive('getLinks')->andReturn([]);

        return new StoreTranslations($links);
    }
}
