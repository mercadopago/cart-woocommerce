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
    public function registerBlocksEnqueueCheckoutScriptsBefore($callback): void
    {
        add_action('woocommerce_blocks_enqueue_checkout_block_scripts_before', $callback);
    }

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
