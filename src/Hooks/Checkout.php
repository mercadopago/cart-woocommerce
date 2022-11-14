<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Checkout
{
    /**
     * @var Checkout
     */
    private static $instance = null;

    /**
     * Checkout constructor
     */
    public function __construct()
    {
    }

    /**
     * Get Checkout Hooks instance
     *
     * @return Checkout
     */
    public static function getInstance(): Checkout
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register after checkout form hook
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerAfterCheckoutForm($callback)
    {
        add_action('woocommerce_after_checkout_form', $callback);
    }

    /**
     * Register before checkout form hook
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerBeforeCheckoutForm($callback)
    {
        add_action('woocommerce_before_checkout_form', $callback);
    }

    /**
     * Register review order before payment hook
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerReviewOrderBeforePayment($callback)
    {
        add_action('woocommerce_review_order_before_payment', $callback);
    }

    /**
     * Register receipt hook
     *
     * @param mixed $callback
     * @param string $id
     *
     * @return void
     */
    public function registerReceipt(string $id, $callback)
    {
        add_action('woocommerce_receipt_' . $id, $callback);
    }

    /**
     * Register before woocommerce pay
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerBeforePay($callback)
    {
        add_action('before_woocommerce_pay', $callback);
    }

    /**
     * Register pay order before submit hook
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerPayOrderBeforeSubmit($callback)
    {
        add_action('woocommerce_pay_order_before_submit', $callback);
    }
}
