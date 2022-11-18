<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Product
{
    /**
     * @var Product
     */
    private static $instance = null;

    /**
     * Get Product Hooks instance
     *
     * @return Product
     */
    public static function getInstance(): Product
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

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
