<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixEnvironment;
use Mockery;
use PHPUnit\Framework\TestCase;

class SmartPixEnvironmentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /** @dataProvider storeModeProvider */
    public function testDefaultIsProductionRegardlessOfWooCommerceMode(bool $testMode): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->never()->andReturn($testMode);
        $this->assertSame(SmartPixEnvironment::PRODUCTION, SmartPixEnvironment::resolve($store));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider storeModeProvider
     */
    public function testExplicitDevelopmentOverrideSelectsHomologationRegardlessOfStoreMode(bool $testMode): void
    {
        define('MP_SMART_PIX_ENV', SmartPixEnvironment::HOMOLOGATION);
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->never()->andReturn($testMode);
        $this->assertSame(SmartPixEnvironment::HOMOLOGATION, SmartPixEnvironment::resolve($store));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider storeModeProvider
     */
    public function testExplicitProductionOverrideSelectsProductionRegardlessOfStoreMode(bool $testMode): void
    {
        define('MP_SMART_PIX_ENV', SmartPixEnvironment::PRODUCTION);
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->never()->andReturn($testMode);
        $this->assertSame(SmartPixEnvironment::PRODUCTION, SmartPixEnvironment::resolve($store));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider storeModeProvider
     */
    public function testUnrecognizedServerOverrideFallsBackToProductionRegardlessOfStoreMode(bool $testMode): void
    {
        define('MP_SMART_PIX_ENV', 'https://untrusted.example');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->never()->andReturn($testMode);
        $this->assertSame(SmartPixEnvironment::PRODUCTION, SmartPixEnvironment::resolve($store));
    }

    /** @dataProvider storeModeProvider */
    public function testMetricEnvironmentTagsATestModeStoreWithoutChangingTheOrdersRoute(bool $testMode): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andReturn($testMode);
        $this->assertSame($testMode ? 'homol' : 'prod', SmartPixEnvironment::metricEnvironment($store));
        $this->assertSame(SmartPixEnvironment::PRODUCTION, SmartPixEnvironment::resolve($store));
    }

    public function testUnreadableStoreModeKeepsTheProductionMetricEnvironment(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andThrow(new \RuntimeException('SYNTHETIC_UNREADABLE_OPTION'));
        $this->assertSame('prod', SmartPixEnvironment::metricEnvironment($store));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHomologationOverrideTagsMetricsAsHomologationEvenInProductionMode(): void
    {
        define('MP_SMART_PIX_ENV', SmartPixEnvironment::HOMOLOGATION);
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->never();
        $this->assertSame('homol', SmartPixEnvironment::metricEnvironment($store));
    }

    public static function storeModeProvider(): array
    {
        return ['test mode' => [true], 'production mode' => [false]];
    }
}
