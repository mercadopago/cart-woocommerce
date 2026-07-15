<?php

namespace MercadoPago\Woocommerce\Tests;

use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WoocommerceMercadoPagoTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    /**
     * Builds the plugin instance without the heavy constructor (defineConstants + registerHooks),
     * so runMigrations() can be exercised in isolation.
     */
    private function newPluginWithoutConstructor(): WoocommerceMercadoPago
    {
        return (new ReflectionClass(WoocommerceMercadoPago::class))->newInstanceWithoutConstructor();
    }

    private function pluginVersion(): string
    {
        return (string) (new ReflectionClass(WoocommerceMercadoPago::class))->getConstant('PLUGIN_VERSION');
    }

    private function mockInstalledVersion(string $installedVersion): void
    {
        WP_Mock::userFunction('get_option', [
            'args'   => ['_mp_installed_version', '0.0.0'],
            'return' => $installedVersion,
        ]);
    }

    /**
     * Stores below 8.8.0 (including the 8.7.x line that still carried the stale ticket cache)
     * must have _all_payment_methods_ticket cleared so the consumer_credits fix (PSW-4081) takes effect.
     *
     * @dataProvider preReleaseVersionsProvider
     */
    public function testRunMigrationsClearsStaleTicketCacheForPreReleaseStores(string $installedVersion): void
    {
        $this->mockInstalledVersion($installedVersion);

        $deleted = false;
        WP_Mock::userFunction('delete_option', [
            'args'   => ['_all_payment_methods_ticket'],
            'return' => function () use (&$deleted) {
                $deleted = true;
                return true;
            },
        ]);
        WP_Mock::userFunction('update_option', ['return' => true]);

        $this->newPluginWithoutConstructor()->runMigrations();

        $this->assertTrue($deleted, "Stale ticket cache should be cleared when upgrading from $installedVersion");
    }

    public function preReleaseVersionsProvider(): array
    {
        return [
            'fresh install (default)'          => ['0.0.0'],
            'old major'                        => ['8.6.0'],
            'just before the fix'              => ['8.7.22'],
            'exactly 8.7.23 (regression case)' => ['8.7.23'],
            'late 8.7.x'                       => ['8.7.99'],
        ];
    }

    /**
     * A store already on the current version short-circuits: no cache clearing, no version write.
     */
    public function testRunMigrationsIsNoOpWhenAlreadyOnCurrentVersion(): void
    {
        $this->mockInstalledVersion($this->pluginVersion());

        $deleted = false;
        $updated = false;
        WP_Mock::userFunction('delete_option', [
            'return' => function () use (&$deleted) {
                $deleted = true;
                return true;
            },
        ]);
        WP_Mock::userFunction('update_option', [
            'return' => function () use (&$updated) {
                $updated = true;
                return true;
            },
        ]);

        $this->newPluginWithoutConstructor()->runMigrations();

        $this->assertFalse($deleted, 'Cache must not be cleared when already on the current version');
        $this->assertFalse($updated, 'Version must not be re-written when already on the current version');
    }

    /**
     * A store already above the release version still records the version but must not
     * re-clear the cache (the fix is already applied there).
     */
    public function testRunMigrationsDoesNotClearCacheForStoresAboveRelease(): void
    {
        $this->mockInstalledVersion('9.0.0');

        $deleted = false;
        $updated = false;
        WP_Mock::userFunction('delete_option', [
            'return' => function () use (&$deleted) {
                $deleted = true;
                return true;
            },
        ]);
        WP_Mock::userFunction('update_option', [
            'args'   => ['_mp_installed_version', $this->pluginVersion()],
            'return' => function () use (&$updated) {
                $updated = true;
                return true;
            },
        ]);

        $this->newPluginWithoutConstructor()->runMigrations();

        $this->assertFalse($deleted, 'Cache must not be cleared for stores already above the release version');
        $this->assertTrue($updated, 'Installed version should still be recorded');
    }
}
