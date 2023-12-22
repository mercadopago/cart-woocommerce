<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Blocks
{
    /**
     * Register cart block update event
     *
     * @param string $namespace
     * @param mixed $callback
     *
     * @return void
     */
    public function registerBlocksUpdated(string $namespace, $callback): void
    {
        woocommerce_store_api_register_update_callback([
            'namespace' => $namespace,
            'callback'  => $callback,
        ]);
    }
}
