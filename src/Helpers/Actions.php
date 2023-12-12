<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Actions
{
    /**
     * Register action when gateway is not called on page
     *
     * @param mixed $hook
     * @param string $hookMethod
     * @param string $gateway
     * @param string $gatewayMethod
     *
     * @return void
     */
    public function registerActionWhenGatewayIsNotCalled($hook, string $hookMethod, string $gateway, string $gatewayMethod): void
    {
        if (method_exists($hook, $hookMethod) && class_exists($gateway) && method_exists($gateway, $gatewayMethod)) {
            $hook->{$hookMethod}(function () use ($gateway, $gatewayMethod) {
                (new $gateway())->{$gatewayMethod}();
            });
        }
    }
}
