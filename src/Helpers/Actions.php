<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!defined('ABSPATH')) {
    exit;
}

final class Actions
{
    /**
     * Register action when gateway is not called on page
     *
     * @param WoocommerceMercadoPago $mercadopago
     * @param string $hook
     * @param string $hookMethod
     * @param string $gateway
     * @param string $gatewayMethod
     *
     * @return void
     */
    public function registerActionWhenGatewayIsNotCalled(
        WoocommerceMercadoPago $mercadopago,
        string $hook,
        string $hookMethod,
        string $gateway,
        string $gatewayMethod
    ): void {
        if (method_exists($mercadopago->{$hook}, $hookMethod) && class_exists($gateway) && method_exists($gateway, $gatewayMethod)) {
            $mercadopago->{$hook}->{$hookMethod}(function () use ($gateway, $gatewayMethod) {
                (new $gateway)->{$gatewayMethod}();
            });
        }
    }
}
