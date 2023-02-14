<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class Gateway
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @var Template
     */
    private $template;

    /**
     * @var StoreTranslations
     */
    private $translations;

    /**
     * Gateway constructor
     */
    public function __construct(Options $options, Template $template, StoreTranslations $translations)
    {
        $this->options            = $options;
        $this->template           = $template;
        $this->translations       = $translations;
    }

    /**
     * Register gateway on Woocommerce if it is valid
     *
     * @param string $gateway
     *
     * @return void
     */
    public function registerGateway(string $gateway): void
    {
        if ($gateway::isAvailable()) {
            add_filter('woocommerce_payment_gateways', function ($methods) use ($gateway) {
                $methods[] = $gateway;
                return $methods;
            });
        }
    }

    /**
     * Register gateway title
     *
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function registerGatewayTitle(AbstractGateway $gateway): void
    {
        add_filter('woocommerce_gateway_title', function ($title, $id) use ($gateway) {
            if (!preg_match('/woo-mercado-pago/', $id)) {
                return $title;
            }

            if ($id !== $gateway->id) {
                return $title;
            }

            if (!is_checkout() && !(defined('DOING_AJAX') && DOING_AJAX)) {
                return $title;
            }

            if ($title !== $gateway->title && (0 === $gateway->commission && 0 === $gateway->discount)) {
                return $title;
            }

            if (!is_numeric($gateway->discount) || $gateway->commission > 99 || $gateway->discount > 99) {
                return $title;
            }

            $total      = (float) WC()->cart->subtotal;
            $discount   = $total * ($gateway->discount / 100);
            $commission = $total * ($gateway->commission / 100);


            if ($gateway->discount > 0 && $gateway->commission > 0) {
                $title .= ' (' . $this->translations->commonCheckout['discount_title'] . ' ' . wp_strip_all_tags(wc_price($discount)) . $this->translations->commonCheckout['fee_title'] . ' ' . wp_strip_all_tags(wc_price($commission)) . ')';
            } elseif ($gateway->discount > 0) {
                $title .= ' (' . $this->translations->commonCheckout['discount_title'] . ' ' . wp_strip_all_tags(wc_price($discount)) . ')';
            } elseif ($gateway->commission > 0) {
                $title .= ' (' . $this->translations->commonCheckout['fee_title'] . ' ' . wp_strip_all_tags(wc_price($commission)) . ')';
            }

            return $title;
        }, 10, 2);
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
     * Register update options
     *
     * @param \WC_Payment_Gateway $gateway
     *
     * @return void
     */
    public function registerUpdateOptions(\WC_Payment_Gateway $gateway): void
    {
        add_action('woocommerce_update_options_payment_gateways_' . $gateway->id, function () use ($gateway) {
            $gateway->init_settings();

            $postData   = $gateway->get_post_data();
            $formFields = $this->getCustomFormFields($gateway);

            foreach ($formFields as $key => $field) {
                if ($gateway->get_field_type($field) !== 'config_title') {
                    $gateway->settings[$key] = $gateway->get_field_value($key, $field, $postData);
                }
            }

            $optionKey       = $gateway->get_option_key();
            $sanitizedFields = apply_filters('woocommerce_settings_api_sanitized_fields_' . $gateway->id, $gateway->settings);

            return $this->options->set($optionKey, $sanitizedFields);
        });
    }

    /**
     * Handles custom components for better integration with native hooks
     *
     * @param $gateway
     *
     * @return array
     */
    public function getCustomFormFields($gateway): array
    {
        $formFields = $gateway->get_form_fields();

        foreach ($formFields as $key => $field) {
            if ('mp_checkbox_list' === $field['type']) {
                $formFields += $this->separateCheckboxes($formFields[$key]);
                unset($formFields[$key]);
            }

            if ('mp_activable_input' === $field['type'] && !isset($formFields[$key . '_checkbox'])) {
                $formFields[$key . '_checkbox'] = ['type' => 'checkbox'];
            }

            if ('mp_toggle_switch' === $field['type']) {
                $formFields[$key]['type'] = 'checkbox';
            }
        }

        return $formFields;
    }

    /**
     * Separates multiple exPayments checkbox into an array
     *
     * @param array $exPayments
     *
     * @return array
     */
    public function separateCheckboxes(array $exPayments): array
    {
        $paymentMethods = array();

        foreach ($exPayments['payment_method_types'] as $paymentMethodsType) {
            $paymentMethods += $this->separateCheckboxesList($paymentMethodsType['list']);
        }

        return $paymentMethods;
    }

    /**
     * Separates multiple exPayments checkbox into an array
     *
     * @param array $exPaymentsList
     *
     * @return array
     */
    public function separateCheckboxesList(array $exPaymentsList): array
    {
        $paymentMethods = array();

        foreach ($exPaymentsList as $payment) {
            $paymentMethods[$payment['id']] = $payment;
        }

        return $paymentMethods;
    }

    /**
     * Register thank you page
     *
     * @param string $id
     * @param mixed $callback
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
     * @param mixed $callback
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
     *
     * @return void
     */
    public function registerAfterSettingsCheckout(string $name, array $args): void
    {
        add_action('woocommerce_after_settings_checkout', function () use ($name, $args) {
            foreach ($args as $arg) {
                $this->template->getWoocommerceTemplate($name, $arg);
            }
        });
    }

    /**
     * Register wp head
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerWpHead($callback): void
    {
        add_action('wp_head', $callback);
    }
}
