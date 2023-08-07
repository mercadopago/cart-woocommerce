<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Checkout
{
    /**
     * Validate if the actual page belongs to the checkout section
     *
     * @return bool
     */
    public function isCheckout(): bool
    {
        return is_checkout();
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

    /**
     * Register receipt hook
     *
     * @param string $id
     * @param mixed $callback
     *
     * @return void
     */
    public function registerReceipt(string $id, $callback)
    {
        add_action('woocommerce_receipt_' . $id, $callback);
    }
}
