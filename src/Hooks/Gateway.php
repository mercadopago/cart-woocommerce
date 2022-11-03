<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Gateway
{
    /**
     * @var Gateway
     */
    private static $instance = null;

    /**
     * Get a Gateway Hooks instance
     *
     * @return Gateway
     */
    public static function getInstance(): Gateway
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register hooks
     *
     * @param string $gateway
     *
     * @return void
     */
    public function registerGateway(string $gateway): void
    {
        add_filter('woocommerce_payment_gateways', function ($methods) use ($gateway) {
            $methods[] = $gateway;
            return $methods;
        });
    }
}
