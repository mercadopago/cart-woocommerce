<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Hooks\Plugin;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

class PluginTest extends TestCase
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

    /** @dataProvider pluginEventProvider */
    public function testRegistrationAndDispatchUseTheSameLiteralEventContract(string $registerMethod, string $executeMethod, string $event): void
    {
        $plugin = new Plugin();
        $callback = [new Plugin(), 'executePluginLoadedAction'];
        $registered = false;
        $dispatched = false;
        WP_Mock::onActionAdded($event)->with($callback, 10, 1)
            ->perform(function () use (&$registered) {
                $registered = true;
            });
        // WP_Mock observes the native API call; it does not manufacture a WordPress dispatcher.
        WP_Mock::onAction($event)->with(null)
            ->perform(function () use (&$dispatched) {
                $dispatched = true;
            });

        $plugin->$registerMethod($callback);
        $plugin->$executeMethod();

        $this->assertTrue($registered, 'The original callback must be registered under the literal integration event.');
        $this->assertTrue($dispatched, 'The real Plugin hook must dispatch that same event without arguments.');
    }

    public static function pluginEventProvider(): array
    {
        return [
            'credentials update' => ['registerOnPluginCredentialsUpdate', 'executeUpdateCredentialAction', 'mercadopago_plugin_credentials_updated'],
            'store info update' => ['registerOnPluginStoreInfoUpdate', 'executeUpdateStoreInfoAction', 'mercadopago_plugin_store_info_updated'],
            'test mode update' => ['registerOnPluginTestModeUpdate', 'executeUpdateTestModeAction', 'mercadopago_plugin_test_mode_updated'],
            'plugin loaded' => ['registerOnPluginLoaded', 'executePluginLoadedAction', 'mercadopago_main_plugin_loaded'],
            'credits enabled' => ['registerEnableCreditsAction', 'executeCreditsAction', 'mp_enable_credits_action'],
            'activation' => ['registerActivatePlugin', 'executeActivatePluginAction', 'mp_execute_activate'],
        ];
    }
}
