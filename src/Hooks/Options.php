<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Options
{
    /**
     * Get option
     *
     * @param string       $optionName
     * @param mixed|string $default
     *
     * @return mixed|string
     */
    public function get(string $optionName, string $default = '')
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
}
