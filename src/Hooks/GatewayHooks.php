<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class GatewayHooks
{
    /**
     * @var GatewayHooks
     */
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): GatewayHooks
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerGateway($gateway): void
    {
        add_filter('woocommerce_payment_gateways', function ($methods) use ($gateway) {
            $methods[] = $gateway;
            return $methods;
        });
    }
}
