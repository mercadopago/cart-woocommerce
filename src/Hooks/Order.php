<?php

namespace MercadoPago\Woocommerce\Hooks;

use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class Order
{
    /**
     * @var WC_Order
     */
    private $order;

    /**
     * @var Order
     */
    private static $instance = null;

    /**
     * Order constructor
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
     * @param array $args
     * @param string $path
     * @param string $defaultPath
     *
     * @return void
     */
    public function addMetaBox(string $id, string $title, string $name, array $args, string $path, string $defaultPath = '')
    {
        add_meta_box($id, $title, function () use ($name, $args, $path, $defaultPath) {
            wc_get_template($name, $args, $path, $defaultPath);
        });
    }

    /**
     * Register order actions
     *
     * @param array $action
     *
     * @return void
     */
    public function registerOrderActions(array $action): void
    {
        add_action('woocommerce_order_actions', function ($actions) use ($action) {
            $actions[] = $action;
            return $actions;
        });
    }

    /**
     * Register order status transition
     *
     * @param string $toStatus
     * @param $callback
     *
     * @return void
     */
    public function registerOrderStatusTransitionTo(string $toStatus, $callback): void
    {
        add_action('woocommerce_order_status_' . $toStatus, $callback);
    }

    /**
     * Register order status transition
     *
     * @param string $fromStatus
     * @param string $toStatus
     * @param $callback
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
     * @param $callback
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
     * @param $callback
     *
     * @return void
     */
    public function registerEmailBeforeOrderTable($callback): void
    {
        add_action('woocommerce_email_before_order_table', $callback);
    }
}
