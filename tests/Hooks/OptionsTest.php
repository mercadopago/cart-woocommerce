<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Tests\Mocks\CartGatewayStub;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

class OptionsTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider commonOptionProvider */
    public function testCommonOptionsUseGlobalValuesDespiteDifferentLocalSettings(string $option): void
    {
        $gateway = $this->gateway();
        $gateway->settings[$option] = 'local-value';
        WP_Mock::userFunction('get_option', [
            'times' => 1,
            'args' => [$option, 'fallback'],
            'return' => 'global-value',
        ]);

        $this->assertSame('global-value', (new Options())->getGatewayOption($gateway, $option, 'fallback'));
    }

    public static function commonOptionProvider(): array
    {
        return [
            'test public key' => ['_mp_public_key_test'],
            'test access token' => ['_mp_access_token_test'],
            'production public key' => ['_mp_public_key_prod'],
            'production access token' => ['_mp_access_token_prod'],
            'category' => ['_mp_category_id'],
            'store identifier' => ['_mp_store_identificator'],
            'integrator' => ['_mp_integrator_id'],
            'domain' => ['_mp_custom_domain'],
            'country' => ['checkout_country'],
            'descriptor' => ['mp_statement_descriptor'],
        ];
    }

    /** @dataProvider localValueProvider */
    public function testNonEmptyGatewayOptionsHavePrecedenceWithoutReadingGlobalOption($value): void
    {
        $gateway = $this->gateway();
        $gateway->settings['binary_mode'] = $value;
        WP_Mock::userFunction('get_option', ['times' => 0]);

        $this->assertSame($value, (new Options())->getGatewayOption($gateway, 'binary_mode', 'fallback'));
    }

    public static function localValueProvider(): array
    {
        return ['no remains a value' => ['no'], 'positive number' => [3], 'nonempty array' => [['setting' => 'value']]];
    }

    /** @dataProvider emptyValueProvider */
    public function testEmptyGatewayOptionFallsBackToGlobalValue($value): void
    {
        $gateway = $this->gateway();
        $gateway->settings['success_url'] = $value;
        WP_Mock::userFunction('get_option', [
            'times' => 1,
            'args' => ['success_url', 'fallback'],
            'return' => 'https://example.test/success',
        ]);

        $this->assertSame('https://example.test/success', (new Options())->getGatewayOption($gateway, 'success_url', 'fallback'));
    }

    public static function emptyValueProvider(): array
    {
        return ['empty string' => [''], 'false' => [false], 'empty array' => [[]]];
    }

    public function testAbsentGatewayOptionUsesDefaultWithoutGlobalFallbackWhenDefaultIsNonEmpty(): void
    {
        WP_Mock::userFunction('get_option', ['times' => 0]);

        $this->assertSame('no', (new Options())->getGatewayOption($this->gateway(), 'binary_mode', 'no'));
    }

    public function testMissingGlobalOptionReceivesAndReturnsCallerDefault(): void
    {
        $gateway = $this->gateway();
        $gateway->settings['success_url'] = '';
        $arguments = null;
        WP_Mock::userFunction('get_option', [
            'return' => function ($name, $default) use (&$arguments) {
                $arguments = [$name, $default];
                return $default;
            },
        ]);

        $this->assertSame('fallback', (new Options())->getGatewayOption($gateway, 'success_url', 'fallback'));
        $this->assertSame(['success_url', 'fallback'], $arguments);
    }

    public function testGetPreservesGlobalOptionNameDefaultAndFalseValue(): void
    {
        $arguments = null;
        WP_Mock::userFunction('get_option', [
            'return' => function ($name, $default) use (&$arguments) {
                $arguments = [$name, $default];
                return false;
            },
        ]);

        $this->assertFalse((new Options())->get('fixture-option', ['default']));
        $this->assertSame(['fixture-option', ['default']], $arguments);
    }

    /** @dataProvider writeResultProvider */
    public function testSetPreservesValueAndWordPressWriteResult(bool $writeResult): void
    {
        $arguments = null;
        WP_Mock::userFunction('update_option', [
            'return' => function ($name, $value) use (&$arguments, $writeResult) {
                $arguments = [$name, $value];
                return $writeResult;
            },
        ]);

        $this->assertSame($writeResult, (new Options())->set('fixture-option', ['mode' => 'test']));
        $this->assertSame(['fixture-option', ['mode' => 'test']], $arguments);
    }

    /** @dataProvider writeResultProvider */
    public function testSetGatewayOptionPreservesWooCommerceWriteResult(bool $writeResult): void
    {
        // Only the inherited WooCommerce Settings API method is doubled.
        $gateway = Mockery::mock($this->gateway());
        $arguments = null;
        $gateway->shouldReceive('update_option')->once()
            ->andReturnUsing(function ($name, $value) use (&$arguments, $writeResult) {
                $arguments = [$name, $value];
                return $writeResult;
            });

        $this->assertSame($writeResult, (new Options())->setGatewayOption($gateway, 'enabled', 'yes'));
        $this->assertSame(['enabled', 'yes'], $arguments);
    }

    public static function writeResultProvider(): array
    {
        return ['changed' => [true], 'unchanged or failed' => [false]];
    }

    private function gateway(): CartGatewayStub
    {
        // Skip business initialization; get_option runs the shared WC_Settings_API stub unchanged.
        return (new ReflectionClass(CartGatewayStub::class))->newInstanceWithoutConstructor();
    }
}
