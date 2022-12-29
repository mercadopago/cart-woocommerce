<?php

namespace MercadoPago\Woocommerce\Hooks;

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
     * Gateway constructor
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
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
     * Register gateway title
     *
     * @return void
     */
    public function registerGatewayTitle(): void
    {
        add_filter('woocommerce_gateway_title', function ($title) {
            return $title;
        });
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
     * @param string $id
     * @param mixed  $gateway
     *
     * @return void
     */
    public function registerUpdateOptions(string $id, $gateway): void
    {
        add_action('woocommerce_update_options_payment_gateways_' . $id, function () use ($gateway) {
            $gateway->init_settings();
            $postData   = $gateway->get_post_data();
            $formFields = $this->getCustomFormFields($gateway);

            foreach ($formFields as $key => $field) {
                if ('title' !== $gateway->get_field_type($field)) {
                    $value = $gateway->get_field_value($key, $field, $postData);
                    $commonConfigs = $gateway->get_common_configs();

                    if (in_array($key, $commonConfigs, true)) {
                        $this->options->set($key, $value);
                    }

                    $gateway->settings[$key] = $value;
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
                $formFields[$key . '_checkbox'] = array(
                    'type' => 'checkbox',
                );
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
     * @param mixed  $callback
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
     * @param array  $args
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
     * @param mixed $callback
     *
     * @return void
     */
    public function registerWpHead($callback): void
    {
        add_action('wp_head', $callback);
    }
}
