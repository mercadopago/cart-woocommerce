<?php

/**
 * WooCommerce Subscriptions stubs for integration tests.
 *
 * This file provides stub classes and functions that simulate the WooCommerce Subscriptions
 * plugin environment without requiring the actual plugin to be loaded.
 *
 * Usage: require_once with class_exists/function_exists guards in setUp():
 *
 *     if (!class_exists('WC_Subscriptions')) {
 *         require_once __DIR__ . '/../../Mocks/WcsStubs.php';
 *     }
 */

if (!class_exists('WC_Subscriptions')) {
    class WC_Subscriptions
    {
    }
}

if (!class_exists('WC_Subscriptions_Cart')) {
    class WC_Subscriptions_Cart
    {
        public static function cart_contains_subscription(): bool
        {
            return $GLOBALS['__wcs_cart_contains_subscription'] ?? false;
        }
    }
}

if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
    class WC_Subscriptions_Change_Payment_Gateway
    {
        public static $is_request_to_change_payment = false;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $code;
        public $message;

        public function __construct($c = '', $m = '')
        {
            $this->code = $c;
            $this->message = $m;
        }
    }
}

if (!function_exists('wcs_get_subscriptions_for_order')) {
    function wcs_get_subscriptions_for_order($order)
    {
        return $GLOBALS['__wcs_subs_for_order'] ?? $GLOBALS['__wcs_subscriptions'] ?? [];
    }
}

if (!function_exists('wcs_order_contains_subscription')) {
    function wcs_order_contains_subscription($order, $type = 'any')
    {
        return $GLOBALS['__wcs_order_contains_subscription'] ?? true;
    }
}

if (!function_exists('wcs_get_subscription')) {
    function wcs_get_subscription($id)
    {
        return $GLOBALS['__mp_test_subscription'] ?? null;
    }
}
