<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class SubscriptionsHelperTest extends TestCase
{
    /**
     * @var SubscriptionsHelper
     */
    private $helper;

    /**
     * @var Mockery\MockInterface|StoreTranslations
     */
    private $storeTranslationsMock;

    public function setUp(): void
    {
        WP_Mock::setUp();
        $this->storeTranslationsMock = Mockery::mock(StoreTranslations::class);
        $this->storeTranslationsMock->subscriptionsErrorMessages = [
            'InvalidToken'         => 'We could not process this card. Please try again.',
            'PaymentRejected'      => 'Payment was declined by the card issuer.',
            'ThreeDsFailed'        => '3D Secure authentication failed. Please try another card.',
            'CardCustomerMismatch' => 'A technical error occurred. Please contact support.',
            'CardExpired'          => 'The card linked to this subscription has expired. Please update the card.',
            'CustomerNotFound'     => 'A technical error occurred. Please contact support.',
            'SubscriptionNotFound' => 'A technical error occurred. Please contact support.',
            'PaymentMethodNotFound' => '',
            'LastPaymentMethod'    => '',
            'CannotRemoveDefault'  => '',
            'AlreadyDefault'       => '',
            'SaveCardFailed'       => 'We could not save the card. Please try again.',
            'IdempotencyKeyReused' => 'A technical error occurred. Please contact support.',
            'CPP_TAAP_0602002'     => 'We could not complete the operation. Please try another card.',
            'http_unavailable'     => 'Service temporarily unavailable. Please try again in a moment.',
            'generic'              => 'A technical error occurred. Please contact support.',
        ];

        $this->helper = new SubscriptionsHelper($this->storeTranslationsMock);
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /* ───────────────────────── isSubscriptionOrder ───────────────────────── */

    public function testIsSubscriptionOrderReturnsFalseForNonObject(): void
    {
        $this->assertFalse($this->helper->isSubscriptionOrder(null));
        $this->assertFalse($this->helper->isSubscriptionOrder('not-an-order'));
        $this->assertFalse($this->helper->isSubscriptionOrder(123));
    }

    public function testIsSubscriptionOrderReturnsFalseForNonWcOrderObject(): void
    {
        $this->assertFalse($this->helper->isSubscriptionOrder(new \stdClass()));
    }

    /* ───────────────────────── getSubscriptionMeta ───────────────────────── */

    public function testGetSubscriptionMetaReturnsDefaultForNonObject(): void
    {
        $this->assertSame('fallback', $this->helper->getSubscriptionMeta(null, 'key', 'fallback'));
        $this->assertNull($this->helper->getSubscriptionMeta('not-a-subscription', 'key'));
    }

    public function testGetSubscriptionMetaReturnsStoredValue(): void
    {
        $subscription = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_meta'])
            ->getMock();
        $subscription->method('get_meta')
            ->with('_mp_subscription_id', true)
            ->willReturn('CPP-WSUB-abc-123');

        $this->assertSame(
            'CPP-WSUB-abc-123',
            $this->helper->getSubscriptionMeta($subscription, '_mp_subscription_id')
        );
    }

    public function testGetSubscriptionMetaReturnsDefaultWhenEmpty(): void
    {
        $subscription = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_meta'])
            ->getMock();
        $subscription->method('get_meta')->willReturn('');

        $this->assertSame('default', $this->helper->getSubscriptionMeta($subscription, 'missing', 'default'));
    }

    /* ───────────────────────── setSubscriptionMeta ───────────────────────── */

    public function testSetSubscriptionMetaNoOpsForNonObject(): void
    {
        // Should not throw — just return silently
        $this->helper->setSubscriptionMeta(null, 'key', 'value');
        $this->helper->setSubscriptionMeta('not-a-subscription', 'key', 'value');
        $this->assertTrue(true);
    }

    public function testSetSubscriptionMetaPersistsValue(): void
    {
        $subscription = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['update_meta_data', 'save'])
            ->getMock();
        $subscription->expects($this->once())
            ->method('update_meta_data')
            ->with('_mp_payment_id', 'PAY-123');
        $subscription->expects($this->once())
            ->method('save');

        $this->helper->setSubscriptionMeta($subscription, '_mp_payment_id', 'PAY-123');
    }

    /* ───────────────────────── generateIdempotencyKey ───────────────────────── */

    public function testGenerateIdempotencyKeyIsDeterministic(): void
    {
        $seed = 'cit:12345:1747843200';
        $key1 = $this->helper->generateIdempotencyKey($seed);
        $key2 = $this->helper->generateIdempotencyKey($seed);

        $this->assertSame($key1, $key2);
    }

    public function testGenerateIdempotencyKeyDistinctSeedsProduceDistinctKeys(): void
    {
        $key1 = $this->helper->generateIdempotencyKey('cit:12345:1747843200');
        $key2 = $this->helper->generateIdempotencyKey('cit:12345:1747843201');

        $this->assertNotSame($key1, $key2);
    }

    public function testGenerateIdempotencyKeyMatchesUuidV4Format(): void
    {
        $key = $this->helper->generateIdempotencyKey('any-seed');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $key
        );
    }

    /* ───────────────────────── buildCitSeed / buildMitSeed / buildAddPaymentMethodSeed ───────────────────────── */

    public function testBuildCitSeedFollowsSpecFormula(): void
    {
        $order = $this->mockOrder(12345, 1747843200);
        $this->assertSame('cit:12345:1747843200:ff8080814f1f0f3a', $this->helper->buildCitSeed($order, 'ff8080814f1f0f3a014f1f1234567890'));
    }

    public function testBuildCitSeedWithoutTokenFallsBackToEmptySuffix(): void
    {
        $order = $this->mockOrder(12345, 1747843200);
        $this->assertSame('cit:12345:1747843200:', $this->helper->buildCitSeed($order));
    }

    public function testBuildCitSeedTruncatesTokenTo16Chars(): void
    {
        $order = $this->mockOrder(12345, 1747843200);
        $seed  = $this->helper->buildCitSeed($order, 'tok_1234567890abcdef_extra_data');
        $this->assertSame('cit:12345:1747843200:tok_1234567890ab', $seed);
    }

    public function testBuildMitSeedFollowsSpecFormula(): void
    {
        $renewal = $this->mockOrder(99887, 1747900000);
        $this->assertSame('mit:99887:1747900000', $this->helper->buildMitSeed($renewal));
    }

    public function testBuildSeedFallsBackToZeroWhenDateMissing(): void
    {
        $order = Mockery::mock();
        $order->shouldReceive('get_id')->andReturn(42);
        $order->shouldReceive('get_date_created')->andReturn(null);

        $this->assertSame('cit:42:0:', $this->helper->buildCitSeed($order));
        $this->assertSame('mit:42:0', $this->helper->buildMitSeed($order));
    }

    public function testBuildAddPaymentMethodSeedTruncatesTokenTo16Chars(): void
    {
        $seed = $this->helper->buildAddPaymentMethodSeed('CPP-WSUB-abc', 'tok_1234567890abcdef_more');
        $this->assertSame('pm-add:CPP-WSUB-abc:tok_1234567890ab', $seed);
    }

    /* ───────────────────────── mapApiErrorToUserMessage — 13 codes ───────────────────────── */

    /**
     * @dataProvider symbolicErrorProvider
     */
    public function testMapApiErrorToUserMessageCoversAllSymbolicCodes(string $error, string $expected): void
    {
        $this->assertSame($expected, $this->helper->mapApiErrorToUserMessage(422, $error));
    }

    public function symbolicErrorProvider(): array
    {
        return [
            'InvalidToken'         => ['InvalidToken', 'We could not process this card. Please try again.'],
            'PaymentRejected'      => ['PaymentRejected', 'Payment was declined by the card issuer.'],
            'ThreeDsFailed'        => ['ThreeDsFailed', '3D Secure authentication failed. Please try another card.'],
            'CardCustomerMismatch' => ['CardCustomerMismatch', 'A technical error occurred. Please contact support.'],
            'CardExpired'          => ['CardExpired', 'The card linked to this subscription has expired. Please update the card.'],
            'CustomerNotFound'     => ['CustomerNotFound', 'A technical error occurred. Please contact support.'],
            'SubscriptionNotFound' => ['SubscriptionNotFound', 'A technical error occurred. Please contact support.'],
            'PaymentMethodNotFound silent' => ['PaymentMethodNotFound', ''],
            'LastPaymentMethod silent'     => ['LastPaymentMethod', ''],
            'CannotRemoveDefault silent'   => ['CannotRemoveDefault', ''],
            'AlreadyDefault silent'        => ['AlreadyDefault', ''],
            'SaveCardFailed'       => ['SaveCardFailed', 'We could not save the card. Please try again.'],
            'IdempotencyKeyReused' => ['IdempotencyKeyReused', 'A technical error occurred. Please contact support.'],
        ];
    }

    public function testMapApiErrorToUserMessageStableCodeTakesPriorityOverError(): void
    {
        // Stable code lookup should match before symbolic error
        $message = $this->helper->mapApiErrorToUserMessage(422, 'PaymentRejected', 'CPP_TAAP_0602002');

        $this->assertSame(
            'We could not complete the operation. Please try another card.',
            $message
        );
    }

    public function testMapApiErrorToUserMessageHttp5xxReturnsUnavailable(): void
    {
        $this->assertSame(
            'Service temporarily unavailable. Please try again in a moment.',
            $this->helper->mapApiErrorToUserMessage(503, null)
        );
        $this->assertSame(
            'Service temporarily unavailable. Please try again in a moment.',
            $this->helper->mapApiErrorToUserMessage(500, null)
        );
    }

    public function testMapApiErrorToUserMessageUnknownReturnsGeneric(): void
    {
        $this->assertSame(
            'A technical error occurred. Please contact support.',
            $this->helper->mapApiErrorToUserMessage(401, 'SomethingBrandNew')
        );
        $this->assertSame(
            'A technical error occurred. Please contact support.',
            $this->helper->mapApiErrorToUserMessage(null, null, null)
        );
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /**
     * Builds a minimal WC_Order-like stub returning the given id and date timestamp.
     *
     * @return Mockery\MockInterface
     */
    /* ───────────────────────── resolveAccessToken ───────────────────────── */

    public function testResolveAccessTokenReturnsProdTokenInProduction(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isProductionMode')->andReturn(true);

        WP_Mock::userFunction('get_option', [
            'args'   => ['woocommerce_woo-mercado-pago-custom_settings', []],
            'return' => ['subscriptions_access_token_prod' => 'PROD-TOKEN', 'subscriptions_access_token_test' => ''],
        ]);

        $this->assertSame('PROD-TOKEN', $this->helper->resolveAccessToken($store));
    }

    public function testResolveAccessTokenReturnsTestTokenInSandbox(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isProductionMode')->andReturn(false);

        WP_Mock::userFunction('get_option', [
            'args'   => ['woocommerce_woo-mercado-pago-custom_settings', []],
            'return' => ['subscriptions_access_token_prod' => '', 'subscriptions_access_token_test' => ' TEST-TOKEN '],
        ]);

        $this->assertSame('TEST-TOKEN', $this->helper->resolveAccessToken($store));
    }

    public function testResolveAccessTokenReturnsEmptyWhenNotConfigured(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isProductionMode')->andReturn(true);

        WP_Mock::userFunction('get_option', [
            'args'   => ['woocommerce_woo-mercado-pago-custom_settings', []],
            'return' => [],
        ]);

        $this->assertSame('', $this->helper->resolveAccessToken($store));
    }

    /* ───────────────────────── resolvePublicKey ───────────────────────── */

    public function testResolvePublicKeyReturnsProdKeyInProduction(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isProductionMode')->andReturn(true);

        WP_Mock::userFunction('get_option', [
            'args'   => ['woocommerce_woo-mercado-pago-custom_settings', []],
            'return' => ['subscriptions_public_key_prod' => 'APP_USR-prod-key', 'subscriptions_public_key_test' => ''],
        ]);

        $this->assertSame('APP_USR-prod-key', $this->helper->resolvePublicKey($store));
    }

    public function testResolvePublicKeyReturnsTestKeyInSandbox(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isProductionMode')->andReturn(false);

        WP_Mock::userFunction('get_option', [
            'args'   => ['woocommerce_woo-mercado-pago-custom_settings', []],
            'return' => ['subscriptions_public_key_prod' => '', 'subscriptions_public_key_test' => ' TEST-pub-key '],
        ]);

        $this->assertSame('TEST-pub-key', $this->helper->resolvePublicKey($store));
    }

    public function testResolvePublicKeyReturnsEmptyWhenNotConfigured(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isProductionMode')->andReturn(true);

        WP_Mock::userFunction('get_option', [
            'args'   => ['woocommerce_woo-mercado-pago-custom_settings', []],
            'return' => [],
        ]);

        $this->assertSame('', $this->helper->resolvePublicKey($store));
    }

    private function mockOrder(int $id, int $timestamp)
    {
        $date = Mockery::mock();
        $date->shouldReceive('getTimestamp')->andReturn($timestamp);

        $order = Mockery::mock();
        $order->shouldReceive('get_id')->andReturn($id);
        $order->shouldReceive('get_date_created')->andReturn($date);

        return $order;
    }
}
