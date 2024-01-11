<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class Gateway
{
    /**
     * @const
     */
    public const GATEWAY_ICON_FILTER = 'woo_mercado_pago_icon';

    /**
     * @var Options
     */
    private $options;

    /**
     * @var Template
     */
    private $template;

    /**
     * @var Store
     */
    private $store;

    /**
     * @var Checkout
     */
    private $checkout;

    /**
     * @var StoreTranslations
     */
    private $translations;

    /**
     * @var Url
     */
    private $url;

    /**
     * Gateway constructor
     *
     * @param Options $options
     * @param Template $template
     * @param Store $store
     * @param Checkout $checkout
     * @param StoreTranslations $translations
     * @param Url $url
     */
    public function __construct(
        Options $options,
        Template $template,
        Store $store,
        Checkout $checkout,
        StoreTranslations $translations,
        Url $url
    ) {
        $this->options      = $options;
        $this->template     = $template;
        $this->store        = $store;
        $this->checkout     = $checkout;
        $this->translations = $translations;
        $this->url          = $url;
    }

    /**
     * Verify if gateway is enabled and available
     *
     * @param AbstractGateway $gateway
     *
     * @return bool
     */
    public function isEnabled(AbstractGateway $gateway): bool
    {
        return $gateway->is_available();
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
            $this->store->addAvailablePaymentGateway($gateway);

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

            if ($gateway->id !== $id) {
                return $title;
            }

            if (!$this->checkout->isCheckout() && !(defined('DOING_AJAX') && DOING_AJAX)) {
                return $title;
            }

            if ($gateway->commission == 0 && $gateway->discount == 0) {
                return $title;
            }

            if (!is_numeric($gateway->discount) || $gateway->discount > 99) {
                return $title;
            }

            if (!is_numeric($gateway->commission) || $gateway->commission > 99) {
                return $title;
            }

            global $mercadopago;

            $discount   = $mercadopago->helpers->cart->calculateSubtotalWithDiscount($gateway);
            $commission = $mercadopago->helpers->cart->calculateSubtotalWithCommission($gateway);

            $title .= $this->buildTitleWithDiscountAndCommission(
                $discount,
                $commission,
                $this->translations->commonCheckout['discount_title'],
                $this->translations->commonCheckout['fee_title']
            );

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
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function registerUpdateOptions(AbstractGateway $gateway): void
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

            $this->options->set($optionKey, $sanitizedFields);
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

            if ('mp_actionable_input' === $field['type'] && !isset($formFields[$key . '_checkbox'])) {
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
        $paymentMethods = [];

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
        $paymentMethods = [];

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
     * Get gateway icon
     *
     * @param string $iconName
     *
     * @return string
     */
    public function getGatewayIcon(string $iconName): string
    {
        $path = $this->url->getPluginFileUrl("assets/images/icons/$iconName", '.png', true);
        return apply_filters(self::GATEWAY_ICON_FILTER, $path);
    }

    /**
     * Build title for gateways with discount and commission
     *
     * @param float $discount
     * @param float $commission
     * @param string $strDiscount
     * @param string $strCommission
     *
     * @return string
     */
    public function buildTitleWithDiscountAndCommission(float $discount, float $commission, string $strDiscount, string $strCommission): string
    {
        $treatedDiscount   = wp_strip_all_tags(wc_price($discount));
        $treatedCommission = wp_strip_all_tags(wc_price($commission));
        $textConcatenation = $this->translations->commonCheckout['text_concatenation'];

        if ($discount > 0 && $commission > 0) {
            return " ($strDiscount $treatedDiscount $textConcatenation $strCommission $treatedCommission)";
        }

        if ($discount > 0) {
            return " ($strDiscount $treatedDiscount)";
        }

        if ($commission > 0) {
            return " ($strCommission $treatedCommission)";
        }

        return '';
    }
}
