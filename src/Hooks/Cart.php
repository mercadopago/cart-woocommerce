<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Cart
{
    /**
     * Validate if the actual page belongs to the cart section
     *
     * @return bool
     */
    public function isCart(): bool
    {
        return is_cart();
    }

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
