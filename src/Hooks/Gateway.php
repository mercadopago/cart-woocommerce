<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Gateway
{
    /**
     * @var Gateway
     */
    private static $instance = null;

    /**
     * Get Gateway Hooks instance
     *
     * @return Gateway
     */
    public static function getInstance(): Gateway
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register gateway on Woocommerce
     *
     * @param string $gateway
     *
     * @return void
     */
    public function registerGateway(string $gateway): void
    {
        add_filter('woocommerce_payment_gateways', function ($methods) use ($gateway) {
            $methods[] = $gateway;
            return $methods;
        });
    }

    /**
     * Register gateway on Woocommerce
     *
     * @param int $priority
     * @param int $acceptedArgs
     * @param $callback
     *
     * @return void
     */
    public function registerGatewayTitle(int $priority, int $acceptedArgs, $callback): void
    {
        add_filter('woocommerce_gateway_title', $callback, $priority, $acceptedArgs);
    }

    /**
     * Register available payment gateways
     *
     * @return void
     */
    public function registerAvailablePaymentGateway(): void
    {
        add_filter('woocommerce_available_payment_gateways', function ($methods) {
            return $methods;
        });
    }

    /**
     * Register update options payment gateway
     *
     * @param string $id
     * @param $callback
     *
     * @return void
     */
    public function registerUpdateOptions(string $id, $callback): void
    {
        add_action('woocommerce_update_options_payment_gateways_' . $id, $callback);
    }

    /**
     * Register thank you page
     *
     * @param string $id
     * @param $callback
     *
     * @return void
     */
    public function registerThankYouPage(string $id, $callback): void
    {
        add_action('woocommerce_thankyou_' . $id, $callback);
    }

    /**
     * Register before thank you page
     *
     * @param $callback
     *
     * @return void
     */
    public function registerBeforeThankYou($callback): void
    {
        add_action('woocommerce_before_thankyou', $callback);
    }

    /**
     * Register after settings checkout
     *
     * @param string $name
     * @param array $args
     * @param string $path
     * @param string $defaultPath
     *
     * @return void
     */
    public function registerAfterSettingsCheckout(string $name, array $args, string $path, string $defaultPath = ''): void
    {
        add_action('woocommerce_after_settings_checkout', function () use ($name, $args, $path, $defaultPath) {
            foreach ($args as $arg) {
                wc_get_template($name, $arg, $path, $defaultPath);
            }
        });
    }

    /**
     * Register wp head
     *
     * @param $callback
     *
     * @return void
     */
    public function registerWpHead($callback): void
    {
        add_action('wp_head', $callback);
    }

    /**
     * Register query vars
     *
     * @param string $var
     *
     * @return void
     */
    public function registerQueryVars(string $var): void
    {
        add_filter('query_vars', function ($vars) use ($var) {
            $vars [] = $var;
            return $vars;
        });
    }
}
