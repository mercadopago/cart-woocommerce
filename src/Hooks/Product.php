<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Product
{
    /**
     * Register before add to cart form hook
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerBeforeAddToCartForm($callback): void
    {
        add_action('woocommerce_before_add_to_cart_form', $callback);
    }
}
