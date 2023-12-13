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
     * @const
     */
    public const LOADED_PLUGIN_ACTION = 'mercadopago_main_plugin_loaded';

    /**
     * @const
     */
    public const ENABLE_CREDITS_ACTION = 'mp_enable_credits_action';

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

    /**
     * Register to plugin loaded event
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOnPluginLoaded($callback): void
    {
        add_action(self::LOADED_PLUGIN_ACTION, $callback);
    }

    /**
     * Register to credits activate event
     * @param mixed $callback
     *
     * @return void
     */
    public function registerEnableCreditsAction($callback)
    {
        add_action(self::ENABLE_CREDITS_ACTION, $callback);
    }

    /**
     * Execute credits activate event
     *
     * @return void
     */
    public function executeCreditsAction(): void
    {
        do_action(self::ENABLE_CREDITS_ACTION);
    }

    /**
     * Execute plugin loaded event
     *
     * @return void
     */
    public function executePluginLoadedAction(): void
    {
        do_action(self::LOADED_PLUGIN_ACTION);
    }

    /**
     * Execute credential update event
     *
     * @return void
     */
    public function executeUpdateCredentialAction(): void
    {
        do_action(self::UPDATE_CREDENTIALS_ACTION);
    }

    /**
     * Execute store info event
     *
     * @return void
     */
    public function executeUpdateStoreInfoAction(): void
    {
        do_action(self::UPDATE_STORE_INFO_ACTION);
    }

    /**
     * Execute test mode update event
     *
     * @return void
     */
    public function executeUpdateTestModeAction(): void
    {
        do_action(self::UPDATE_TEST_MODE_ACTION);
    }
}
