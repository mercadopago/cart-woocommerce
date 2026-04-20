<?php

/**
 * Stubs for WooCommerce Blocks classes not available in the unit test environment.
 * These mirror the real WooCommerce class signatures so AbstractBlock can be instantiated.
 */

namespace Automattic\WooCommerce\Blocks\Payments;

if (!interface_exists('Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface')) {
    interface PaymentMethodTypeInterface
    {
        public function initialize();

        public function is_active();

        public function get_payment_method_script_handles();

        public function get_payment_method_data();
    }
}

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface;

if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
    abstract class AbstractPaymentMethodType implements PaymentMethodTypeInterface
    {
        protected $name = '';

        protected $settings = [];

        protected function get_setting($name, $default = '')
        {
            return isset($this->settings[$name]) ? $this->settings[$name] : $default;
        }

        public function get_name()
        {
            return $this->name;
        }

        public function is_active(): bool
        {
            return true;
        }

        public function get_payment_method_script_handles(): array
        {
            return [];
        }

        public function get_supported_features(): array
        {
            return ['products'];
        }

        public function get_payment_method_data(): array
        {
            return [];
        }
    }
}
