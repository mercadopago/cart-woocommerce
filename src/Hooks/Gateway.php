<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Gateway
{
    /**
     * @var ?GatewayHooks
     */
    private static ?GatewayHooks $instance = null;

    /**
     * GatewayHooks constructor
     */
    private function __construct()
    {
    }

    /**
     * Get a GatewayHooks instance
     *
     * @return GatewayHooks
     */
    public static function getInstance(): GatewayHooks
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
