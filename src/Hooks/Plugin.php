<?php

namespace MercadoPago\Woocommerce\Hooks;

class Plugin
{
    /**
     * @const
     */
    public const UPDATE_CREDENTIALS_ACTION = 'mercadopago_plugin_credentials_updated';

    /**
     * @const
     */
    public const UPDATE_STORE_INFO_ACTION = 'mercadopago_plugin_store_info_updated';

    /**
     * @const
     */
    public const UPDATE_TEST_MODE_ACTION = 'mercadopago_plugin_test_mode_updated';

    /**
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Get Plugin Hooks instance
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register to plugin update event
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOnPluginCredentialsUpdate($callback): void
    {
        add_action(self::UPDATE_CREDENTIALS_ACTION, $callback);
    }

    /**
     * Register to plugin store info update event
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOnPluginStoreInfoUpdate($callback): void
    {
        add_action(self::UPDATE_STORE_INFO_ACTION, $callback);
    }

    /**
     * Register to plugin test mode update event
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOnPluginTestModeUpdate($callback): void
    {
        add_action(self::UPDATE_TEST_MODE_ACTION, $callback);
    }
}
