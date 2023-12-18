<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Cart
{
    /**
     * Register WC_Cart calculate fees
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerCartCalculateFees($callback)
    {
        add_action('woocommerce_cart_calculate_fees', $callback);
    }
}
