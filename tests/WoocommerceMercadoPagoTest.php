<?php

namespace MercadoPago\Woocommerce\Tests;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Funnel\Funnel;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use Mockery;
use Mockery\MockInterface;
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
        Mockery::close();
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

    /**
     * @dataProvider localeCatalogProvider
     */
    public function testLoadPluginTextDomainUsesCountrySpecificOrGenericCatalog(string $wordpressLocale, string $catalogLocale): void
    {
        if (!defined('MP_PLUGIN_FILE')) {
            define('MP_PLUGIN_FILE', '/tmp/woocommerce-mercadopago.php');
        }

        WP_Mock::userFunction('get_plugin_data', [
            'return' => [
                'TextDomain' => 'woocommerce-mercadopago',
                'DomainPath' => 'i18n/languages',
            ],
        ]);
        WP_Mock::userFunction('unload_textdomain', [
            'args' => ['woocommerce-mercadopago'],
        ]);
        WP_Mock::userFunction('get_locale', ['return' => $wordpressLocale]);
        WP_Mock::onFilter('plugin_locale')
            ->with($wordpressLocale, 'woocommerce-mercadopago')
            ->reply($wordpressLocale);
        $loaded = false;
        WP_Mock::userFunction('load_textdomain', [
            'args' => [
                'woocommerce-mercadopago',
                dirname(\MP_PLUGIN_FILE) . "/i18n/languages/woocommerce-mercadopago-$catalogLocale.mo",
            ],
            'return' => function () use (&$loaded) {
                $loaded = true;
                return true;
            },
        ]);

        $this->newPluginWithoutConstructor()->loadPluginTextDomain();

        $this->assertTrue($loaded);
    }

    public function localeCatalogProvider(): array
    {
        return [
            'Chile uses the Chilean catalog'    => ['es_CL', 'es_CL'],
            'Mexico uses the Mexican catalog'   => ['es_MX', 'es_MX'],
            'Argentina uses the generic catalog' => ['es_AR', 'es'],
        ];
    }

    /**
     * Builds the plugin with only the collaborators activatePlugin() touches. Both are
     * public properties, so no reflection is needed beyond skipping the constructor.
     *
     * @param MockInterface|Funnel $funnel
     * @param MockInterface|Store $store
     */
    private function newPluginWiredTo($funnel, $store): WoocommerceMercadoPago
    {
        $plugin = $this->newPluginWithoutConstructor();
        $plugin->funnel = $funnel;
        $plugin->storeConfig = $store;

        return $plugin;
    }

    /**
     * The seller contact step reaches production through exactly one line — the callback
     * activatePlugin() hands to create(). Every other test drives updateStepSellerContact()
     * directly, so dropping or reordering that callback keeps the whole suite green while
     * the feature silently stops running.
     */
    public function testGivenFunnelNotCreatedWhenPluginActivatesThenContactStepRunsRightAfterTheFlagIsCleared(): void
    {
        $calls = [];

        $funnel = Mockery::mock(Funnel::class);
        $funnel->shouldReceive('created')->once()->andReturn(false);
        $funnel->shouldReceive('updateStepActivate')->never();
        $funnel->shouldReceive('create')->once()->andReturnUsing(
            function (\Closure $after) use (&$calls) {
                $calls[] = 'create';
                $after();
            }
        );
        $funnel->shouldReceive('updateStepSellerContact')->once()->andReturnUsing(
            function () use (&$calls) {
                $calls[] = 'seller-contact';
            }
        );

        $store = Mockery::mock(Store::class);
        $store->shouldReceive('setExecuteActivate')->once()->with(false)->andReturnUsing(
            function () use (&$calls) {
                $calls[] = 'activation-flag-off';
            }
        );

        $this->newPluginWiredTo($funnel, $store)->activatePlugin();

        // Order matters: the flag has to be cleared before the second HTTP call, so a failure
        // there cannot leave the activation armed and fire the whole funnel again.
        $this->assertSame(['create', 'activation-flag-off', 'seller-contact'], $calls);
    }

    public function testGivenFunnelAlreadyCreatedWhenPluginActivatesThenNeitherCreateNorContactRuns(): void
    {
        $flagCleared = false;

        $funnel = Mockery::mock(Funnel::class);
        $funnel->shouldReceive('created')->once()->andReturn(true);
        $funnel->shouldReceive('create')->never();
        $funnel->shouldReceive('updateStepSellerContact')->never();
        $funnel->shouldReceive('updateStepActivate')->once()->andReturnUsing(
            function (\Closure $after) {
                $after();
            }
        );

        $store = Mockery::mock(Store::class);
        $store->shouldReceive('setExecuteActivate')->once()->with(false)->andReturnUsing(
            function () use (&$flagCleared) {
                $flagCleared = true;
            }
        );

        $this->newPluginWiredTo($funnel, $store)->activatePlugin();

        $this->assertTrue($flagCleared, 'an already created funnel must still disarm the activation flag');
    }
}
