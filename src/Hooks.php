<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Blocks;
use MercadoPago\Woocommerce\Hooks\Cart;
use MercadoPago\Woocommerce\Hooks\Checkout;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Hooks\Product;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Hooks\Template;

if (!defined('ABSPATH')) {
    exit;
}

class Hooks
{
    public Admin $admin;

    public Blocks $blocks;

    public Cart $cart;

    public Checkout $checkout;

    public Endpoints $endpoints;

    public Gateway $gateway;

    public Options $options;

    public Order $order;

    public OrderMeta $orderMeta;

    public Plugin $plugin;

    public Product $product;

    public Scripts $scripts;

    public Template $template;

    public function __construct(
        Admin $admin,
        Blocks $blocks,
        Cart $cart,
        Checkout $checkout,
        Endpoints $endpoints,
        Gateway $gateway,
        Options $options,
        Order $order,
        OrderMeta $orderMeta,
        Plugin $plugin,
        Product $product,
        Scripts $scripts,
        Template $template
    ) {
        $this->admin     = $admin;
        $this->blocks    = $blocks;
        $this->cart      = $cart;
        $this->checkout  = $checkout;
        $this->endpoints = $endpoints;
        $this->gateway   = $gateway;
        $this->options   = $options;
        $this->order     = $order;
        $this->orderMeta = $orderMeta;
        $this->plugin    = $plugin;
        $this->product   = $product;
        $this->scripts   = $scripts;
        $this->template  = $template;
    }
}
