<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

if (!defined('ABSPATH')) {
    exit;
}

class Options
{
    /**
     * @const
     */
    public const COMMON_CONFIGS = [
        '_mp_public_key_test',
        '_mp_access_token_test',
        '_mp_public_key_prod',
        '_mp_access_token_prod',
        'checkout_country',
        'mp_statement_descriptor',
        '_mp_category_id',
        '_mp_store_identificator',
        '_mp_integrator_id',
        '_mp_custom_domain',
        'installments',
        'auto_return',
    ];

    /**
     * Get option
     *
     * @param string $optionName
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $optionName, $default = false)
    {
        return get_option($optionName, $default);
    }

    /**
     * Set option
     *
     * @param string $optionName
     * @param mixed  $value
     *
     * @return bool
     */
    public function set(string $optionName, $value): bool
    {
        return update_option($optionName, $value);
    }

    /**
     * Get Mercado Pago gateway option
     *
     * @param AbstractGateway $gateway
     * @param string $optionName
     * @param string $default
     *
     * @return mixed|string
     */
    public function getMercadoPago(AbstractGateway $gateway, string $optionName, string $default = '')
    {
        $wordpressConfigs = self::COMMON_CONFIGS;
        if (in_array($optionName, $wordpressConfigs, true)) {
            return $this->get($optionName, $default);
        }

        $option = $gateway->get_option($optionName, $default);

        return !$option ? $option : $this->get($optionName, $default);
    }


    /**
     * Set Mercado Pago gateway option
     *
     * @param AbstractGateway $gateway
     * @param string $optionName
     * @param $value
     *
     * @return bool
     */
    public function setMercadoPago(AbstractGateway $gateway, string $optionName, $value): bool
    {
        return $gateway->update_option($optionName, $value);
    }
}
