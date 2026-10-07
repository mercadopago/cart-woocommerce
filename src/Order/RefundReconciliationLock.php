<?php

namespace MercadoPago\Woocommerce\Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database-backed lock for the short per-payment reconciliation write.
 * The options table has a unique option_name on both legacy and HPOS stores.
 */
class RefundReconciliationLock
{
    private const LEASE_SECONDS = 600;

    /** @var \wpdb */
    private $database;

    public function __construct($database = null)
    {
        if ($database === null) {
            global $wpdb;
            $database = $wpdb;
        }
        $this->database = $database;
    }

    /**
     * @return array{name: string, value: string}|null Null means another worker owns the lock or the database failed.
     */
    public function acquire(int $orderId, string $paymentId): ?array
    {
        $name = $this->name($orderId, $paymentId);
        $value = (time() + self::LEASE_SECONDS) . ':' . bin2hex(random_bytes(16));
        $lock = ['name' => $name, 'value' => $value];

        $inserted = $this->database->insert(
            $this->database->options,
            ['option_name' => $name, 'option_value' => $value, 'autoload' => 'no'],
            ['%s', '%s', '%s']
        );
        if ($inserted === 1) {
            return $lock;
        }

        $existing = $this->database->get_var($this->database->prepare(
            "SELECT option_value FROM {$this->database->options} WHERE option_name = %s",
            $name
        ));
        if (!is_string($existing) || (int) strtok($existing, ':') > time()) {
            return null;
        }

        // A crashed worker can leave a row behind. Replace only the exact expired value;
        // another contender that already replaced it must keep ownership.
        $replaced = $this->database->update(
            $this->database->options,
            ['option_value' => $value],
            ['option_name' => $name, 'option_value' => $existing],
            ['%s'],
            ['%s', '%s']
        );
        return $replaced === 1 ? $lock : null;
    }

    /** @param array{name: string, value: string} $lock */
    public function release(array $lock): bool
    {
        return $this->database->delete(
            $this->database->options,
            ['option_name' => $lock['name'], 'option_value' => $lock['value']],
            ['%s', '%s']
        ) === 1;
    }

    private function name(int $orderId, string $paymentId): string
    {
        return 'mp_refund_reconcile_' . substr(
            hash('sha256', $this->database->prefix . ':' . $orderId . ':' . $paymentId),
            0,
            32
        );
    }
}
