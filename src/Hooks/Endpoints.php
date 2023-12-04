<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Endpoints
{
    /**
     * Register AJAX endpoints
     *
     * @param string $endpoint
     * @param mixed  $callback
     *
     * @return void
     */
    public function registerAjaxEndpoint(string $endpoint, $callback): void
    {
        add_action('wp_ajax_' . $endpoint, $callback);
    }

    /**
     * Register WC API endpoints
     *
     * @param string $endpoint
     * @param mixed  $callback
     *
     * @return void
     */
    public function registerApiEndpoint(string $endpoint, $callback): void
    {
        add_action('woocommerce_api_' . strtolower($endpoint), $callback);
    }

    /**
     * Register WC_AJAX endpoints
     *
     * @param string $endpoint
     * @param mixed  $callback
     *
     * @return void
     */
    public function registerWCAjaxEndpoint(string $endpoint, $callback): void
    {
        add_action('wc_ajax_' . $endpoint, $callback);
    }
}
