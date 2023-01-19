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
    public const GATEWAY_ICON_FILTER = 'woo_mercado_pago_icon';

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
     * Get gateway icon
     *
     * @param string $fileName
     *
     * @return string
     */
    public function getGatewayIcon(string $fileName): string
    {
        $path = plugins_url("../assets/images/icons/{$fileName}.png", plugin_dir_path(__FILE__));

        return apply_filters(self::GATEWAY_ICON_FILTER, $path);
    }
}
