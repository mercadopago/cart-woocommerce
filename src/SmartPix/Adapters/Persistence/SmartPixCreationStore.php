<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Persistence;

use RuntimeException;
use WC_Order;
use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

/** Immutable admission: no lease expires while a remote creation may still be executing. */
class SmartPixCreationStore
{
    public const META_KEY = '_mp_smart_pix_creation_started';
    public const RESPONSE_META_KEY = '_mp_smart_pix_creation_response_received';
    public const FAILURE_META_KEY = '_mp_smart_pix_creation_failure';
    /** Unix time of the admission; only bounds a merchant's local cancellation of an unlinked attempt. */
    public const STARTED_AT_META_KEY = '_mp_smart_pix_creation_started_at';

    private wpdb $database;
    private string $table;

    public function __construct(wpdb $database)
    {
        $this->database = $database;
        $this->table = (string) $database->options;
        if (preg_match('/^[A-Za-z0-9_]+$/D', $this->table) !== 1) {
            throw new RuntimeException('creation_store_invalid');
        }
    }

    /** Returns false for every later request, including a concurrent submission. */
    public function begin(WC_Order $order, string $attemptKey): bool
    {
        if ($attemptKey === '' || $order->get_id() <= 0) {
            throw new RuntimeException('creation_context_invalid');
        }
        $hash = hash('sha256', $attemptKey);
        $admitted = $this->insertOnce('_mp_smart_pix_creation_' . $order->get_id(), $hash);
        $order->read_meta_data(true);
        // A concurrently changed attempt must not authorize the admitted request.
        if ($order->get_meta('_mp_idempotency_key', true, 'edit') !== $attemptKey) {
            throw new RuntimeException('creation_attempt_changed');
        }
        $order->update_meta_data(self::META_KEY, $hash);
        if (self::startedAt($order) === null) {
            // Written once; a later concurrent request can only keep the first, earlier time.
            $order->update_meta_data(self::STARTED_AT_META_KEY, (string) time());
        }
        $order->save_meta_data();
        $order->read_meta_data(true);
        if ($order->get_meta(self::META_KEY, true, 'edit') !== $hash) {
            throw new RuntimeException('creation_persistence_failed');
        }
        return $admitted;
    }

    /** Records only that the call returned, never a payment status or authorization to retry. */
    public function responseReceived(WC_Order $order): void
    {
        $order->read_meta_data(true);
        $order->update_meta_data(self::RESPONSE_META_KEY, 'yes');
        $order->save_meta_data();
    }

    /** Records a known checkout failure without releasing the financial admission. */
    public function recordFailure(WC_Order $order, string $category): void
    {
        $order->read_meta_data(true);
        if (!self::hasStarted($order) || !in_array($category, ['validation', 'authentication'], true)) {
            throw new RuntimeException('creation_failure_invalid');
        }
        $value = $order->get_meta(self::META_KEY, true, 'edit') . ':' . $category;
        $order->update_meta_data(self::FAILURE_META_KEY, $value);
        $order->save_meta_data();
        $order->read_meta_data(true);
        if (self::failureCategory($order) !== $category) {
            throw new RuntimeException('creation_failure_persistence_failed');
        }
    }

    /** Only a failure bound to the current admitted attempt can change checkout recovery. */
    public static function failureCategory(WC_Order $order): ?string
    {
        $value = $order->get_meta(self::FAILURE_META_KEY, true, 'edit');
        if (!is_string($value) || preg_match('/^([a-f0-9]{64}):(validation|authentication)$/D', $value, $parts) !== 1) {
            return null;
        }
        return $order->get_meta(self::META_KEY, true, 'edit') === $parts[1] ? $parts[2] : null;
    }

    /** Marker is informational only; begin() is the atomic financial admission. */
    public static function hasStarted(WC_Order $order): bool
    {
        $value = $order->get_meta(self::META_KEY, true, 'edit');
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }

    /** Informational like hasStarted(); absent on orders admitted before this marker existed. */
    public static function startedAt(WC_Order $order): ?int
    {
        $value = $order->get_meta(self::STARTED_AT_META_KEY, true, 'edit');
        return is_string($value) && preg_match('/^[1-9][0-9]{0,18}$/D', $value) === 1 ? (int) $value : null;
    }

    private function read(string $name): string
    {
        $stored = $this->database->get_var($this->database->prepare(
            "SELECT option_value FROM `{$this->table}` WHERE option_name = %s", $name
        ));
        if ($this->database->last_error !== '' || !is_string($stored)) {
            throw new RuntimeException('creation_store_unverified');
        }
        return $stored;
    }

    private function insertOnce(string $name, string $value): bool
    {
        if ($this->database->options !== $this->table) {
            throw new RuntimeException('creation_site_changed');
        }
        $previous = $this->database->suppress_errors(true);
        try {
            // A duplicate must never replace the first admission (even with CLIENT_FOUND_ROWS).
            $rows = $this->database->query($this->database->prepare(
                "INSERT IGNORE INTO `{$this->table}` (option_name, option_value, autoload) VALUES (%s, %s, %s)",
                $name, $value, 'no'
            ));
            if (!is_int($rows) || $this->database->last_error !== '') {
                throw new RuntimeException('creation_store_unavailable');
            }
            $stored = $this->read($name);
            if (!hash_equals($value, $stored)) {
                throw new RuntimeException('creation_store_unverified');
            }
            if ($rows === 1) {
                wp_cache_delete($name, 'options');
                wp_cache_delete('notoptions', 'options');
            }
            return $rows === 1;
        } finally {
            $this->database->suppress_errors($previous);
        }
    }
}
