<?php

namespace MercadoPago\Woocommerce\Hooks;


if (!defined('ABSPATH')) {
    exit;
}

class Actions
{


    /**
     * Get option
     *
     * @param string $hookName
     * @param callable $callback
     *
     */
    public function set(string $hookName, callable $callback): void
    {
        add_action($hookName, $callback);
    }

}
