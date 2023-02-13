<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Checkout
{
    /**
     * @var Scripts
     */
    private $scripts;

    /**
     * Checkout constructor
     */
    public function __construct(Scripts $scripts)
    {
        $this->scripts = $scripts;
    }

    /**
     * Register before checkout form hook
     *
     * @param string $location
     *
     * @return void
     */
    public function registerBeforeCheckoutForm(string $location)
    {
        add_action('woocommerce_before_checkout_form', function () use ($location) {
            $this->scripts->registerMelidataStoreScript($location);
        });
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
     * @param string $id
     * @param mixed $callback
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
     * @param string $location
     *
     * @return void
     */
    public function registerBeforePay(string $location)
    {
        add_action('before_woocommerce_pay', function () use ($location) {
            $this->scripts->registerMelidataStoreScript($location);
        });
    }

    /**
     * Register pay order before submit hook
     *
     * @param string $location
     *
     * @return void
     */
    public function registerPayOrderBeforeSubmit(string $location)
    {
        add_action('woocommerce_pay_order_before_submit', function () use ($location) {
            $this->scripts->registerMelidataStoreScript($location);
        });
    }
}
