<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Order
{
    /**
     * @var Order
     */
    private static $instance = null;

    /**
     * Order Hooks constructor
     */
    private function __construct()
    {
    }

    /**
     * Get Order instance
     *
     * @return Order
     */
    public static function getInstance(): Order
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register meta box addition on order page
     *
     * @param $id
     * @param $title
     * @param string $name
     * @param array $args
     * @param string $path
     *
     * @return void
     */
    public function registerMetaBox($id, $title, string $name, array $args, string $path): void
    {
        add_action('add_meta_boxes_shop_order', function () use ($id, $title, $name, $args, $path) {
            $this->addMetaBox($id, $title, $name, $args, $path);
        });
    }

    /**
     * Add a meta box to screen
     *
     * @param string $id
     * @param string $title
     * @param string $name
     * @param array  $args
     * @param string $path
     *
     * @return void
     */
    public function addMetaBox(string $id, string $title, string $name, array $args, string $path):void
    {
        add_meta_box($id, $title, function () use ($name, $args, $path) {
            $this->registerTemplate($name, $args, $path);
        });
    }

    /**
     * Register template
     *
     * @param string $name
     * @param array  $args
     * @param string $path
     *
     * @return void
     */
    public function registerTemplate(string $name, array $args, string $path): void
    {
        wc_get_template($name, $args, $path);
    }

    /**
     * Register order actions
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOrderActions($callback): void
    {
        add_action('woocommerce_order_actions', $callback);
    }

    /**
     * Register order status transition
     *
     * @param string $toStatus
     * @param mixed  $callback
     *
     * @return void
     */
    public function registerOrderStatusTransitionTo(string $toStatus, $callback): void
    {
        add_action('woocommerce_order_status_' . $toStatus , $callback);
    }

    /**
     * Register order status transition
     *
     * @param string $fromStatus
     * @param string $toStatus
     * @param mixed  $callback
     *
     * @return void
     */
    public function registerOrderStatusTransitionFromTo(string $fromStatus, string $toStatus, $callback): void
    {
        add_action('woocommerce_order_status_' . $fromStatus . '_to_' . $toStatus, $callback);
    }

    /**
     * Register order details after order table
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOrderDetailsAfterOrderTable($callback): void
    {
        add_action('woocommerce_order_details_after_order_table', $callback);
    }

    /**
     * Register email before order table
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerEmailBeforeOrderTable($callback): void
    {
        add_action('woocommerce_email_before_order_table', $callback);
    }
}

