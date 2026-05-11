<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Helpers\Form;

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
        return isset($GLOBALS['wp_query']) && is_checkout();
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
        // Static flag: all gateways share the same callback logic via getAvailablePaymentGateways(),
        // so a single registration covers all of them. Prevents 7x duplicate hook registrations.
        static $registered = false;
        if ($registered) {
            return;
        }

        // Classic themes: hook fires within the order-pay PHP template.
        // Block-based themes (e.g. Twenty Twenty-Five): before_woocommerce_pay is not fired.
        // Detection on wp action (query vars resolved); registration at wp_enqueue_scripts
        // priority 20 runs after prioritizeMelidataStoreScriptEarly (priority 10) so
        // wp_localize_script overwrites /checkout with /woocommerce_pay.
        add_action('before_woocommerce_pay', $callback);
        add_action('wp', function () use ($callback) {
            if (!is_checkout_pay_page()) {
                return;
            }
            add_action('wp_enqueue_scripts', function () use ($callback) {
                $callback();
            }, 20);
        });

        $registered = true;
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
        // Static flag: all gateways share the same callback logic via getAvailablePaymentGateways(),
        // so a single registration covers all of them. Prevents 7x duplicate hook registrations.
        static $registered = false;
        if ($registered) {
            return;
        }

        // Classic themes: hook fires within the order-pay PHP template before the submit button.
        // Block-based themes: woocommerce_pay_order_before_submit is not fired.
        // pay_for_order=true controls what WooCommerce renders: with it, the payment form
        // (checkouts) is shown → /pay_order; without it, only the pending summary is shown
        // → registerBeforePay (priority 20) wins with /woocommerce_pay.
        // Priority 21 ensures this runs after registerBeforePay (priority 20).
        add_action('woocommerce_pay_order_before_submit', $callback);
        add_action('wp', function () use ($callback) {
            if (!is_checkout_pay_page() || Form::sanitizedGetData('pay_for_order') === '') {
                return;
            }
            add_action('wp_enqueue_scripts', function () use ($callback) {
                $callback();
            }, 21);
        });

        $registered = true;
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
