<?php

/**
 * Minimal stateful WC_Order for tests that must really call payment_complete().
 *
 * The unit-test bootstrap has no WooCommerce: WC_Order only exists as an empty Mockery class, so
 * OrderStatus' method_exists() guards make payment_complete() unreachable with plain mocks.
 * Declaring this class globally would change every other test, so it is required only from tests
 * that run in their own process (@runTestsInSeparateProcesses).
 */
if (!class_exists('WC_Order', false)) {
    class WC_Order
    {
        public array $state;
        public array $notes = [];
        public int $paymentCompleteCalls = 0;
        public $onPaymentComplete = null;

        public function __construct(array $state = [])
        {
            $this->state = $state + ['status' => 'pending', 'flag' => '', 'paid' => false, 'method' => 'woo-mercado-pago-pix', 'id' => 28];
        }

        public function get_id() { return $this->state['id']; }
        public function get_payment_method() { return $this->state['method']; }
        public function get_status($context = 'view') { return $this->state['status']; }
        public function get_meta($key = '', $single = true, $context = 'view') { return $key === 'pix_payment_approved' ? $this->state['flag'] : ($this->state['meta'][$key] ?? ''); }
        public function get_date_paid($context = 'view') { return $this->state['paid'] ? new \stdClass() : null; }
        public function update_meta_data($key, $value, $meta_id = 0) { if ($key === 'pix_payment_approved') { $this->state['flag'] = $value; } else { $this->state['meta'][$key] = $value; } }
        public function save_meta_data() { return true; }
        public function get_meta_data() { $all = []; foreach ($this->state['meta'] ?? [] as $key => $value) { $all[] = new class ($key, $value) { private array $data; public function __construct($key, $value) { $this->data = ['key' => $key, 'value' => $value]; } public function get_data() { return $this->data; } }; } return $all; }
        public function delete_meta_data($key) { unset($this->state['meta'][$key]); }
        public function save() { return $this->state['id']; }
        public function needs_processing() { return true; }
        public function add_order_note($note, $is_customer_note = 0) { $this->notes[] = $note; return 1; }
        public function update_status($status, $note = '', $manual = false)
        {
            if (!empty($this->state['block_update_status'])) {
                return false;
            }
            $this->state['status'] = $status;
            return true;
        }

        public function payment_complete($transaction_id = '')
        {
            $this->paymentCompleteCalls++;
            if (is_callable($this->onPaymentComplete)) {
                return ($this->onPaymentComplete)($this);
            }
            $this->state['status'] = 'processing';
            $this->state['paid'] = true;
            return true;
        }
    }
}
