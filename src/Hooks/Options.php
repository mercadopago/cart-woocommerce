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
        '_mp_category_id',
        '_mp_store_identificator',
        '_mp_integrator_id',
        '_mp_custom_domain',
        'checkout_country',
        'mp_statement_descriptor',
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
     * @param mixed $default
     *
     * @return mixed
     */
    public function getGatewayOption(AbstractGateway $gateway, string $optionName, $default = '')
    {
        if (in_array($optionName, self::COMMON_CONFIGS, true)) {
            return $this->get($optionName, $default);
        }

        $option = $gateway->get_option($optionName, $default);

        if (!empty($option)) {
            return $option;
        }

        return $this->get($optionName, $default);
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
    public function setGatewayOption(AbstractGateway $gateway, string $optionName, $value): bool
    {
        return $gateway->update_option($optionName, $value);
    }
}
