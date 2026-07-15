<?php

/**
 * Minimal WC_Settings_API / WC_Payment_Gateway stubs for unit tests.
 * Defined before Mockery runs so xdebug can attribute coverage to
 * subclasses (CustomGateway) correctly.
 */

if (!class_exists('WC_Settings_API')) {
    abstract class WC_Settings_API
    {
        public array $settings = [];

        public function get_option(string $key, $empty_value = null)
        {
            return $this->settings[$key] ?? $empty_value;
        }
    }
}

if (!class_exists('WC_Subscription')) {
    class WC_Subscription extends WC_Settings_API
    {
        public function get_id(): int { return 0; }
        public function get_status(): string { return 'active'; }
        public function get_billing_interval(): int { return 1; }
        public function get_billing_period(): string { return 'month'; }
        public function get_meta(string $key, bool $single = true) { return ''; }
        public function update_meta_data(string $key, $value): void {}
        public function save(): void {}
    }
}

if (!class_exists('WC_Payment_Gateway')) {
    abstract class WC_Payment_Gateway extends WC_Settings_API
    {
        public array $supports = [];
        public string $id      = '';

        public function get_return_url($order = null): string
        {
            return '';
        }
    }
}
