<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Endpoints
{
    /**
     * @var Endpoints
     */
    private static $instance = null;

    /**
     * Get Endpoints Hooks instance
     *
     * @return Endpoints
     */
    public static function getInstance(): Endpoints
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

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
        add_action('woocommerce_api_' . $endpoint, $callback);
    }
}
