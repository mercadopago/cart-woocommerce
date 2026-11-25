<?php

namespace MercadoPago\Woocommerce\Order;

use MercadoPago\Woocommerce\Exceptions\OrderStatusLockException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Per-order mutual exclusion for Pix status processing, backed by the database
 * named locks (GET_LOCK). The lock belongs to the DB connection, so it is released
 * when the owning PHP process dies and it never expires while hooks are still running.
 *
 * It is cooperative: it protects the Mercado Pago code paths that go through
 * OrderStatus::processStatus(), not arbitrary third-party code.
 *
 * The same mechanism, with another name prefix, serializes the Core refund accumulation of an order
 * (CoreNotification): different prefixes never contend with each other.
 */
class PixOrderStatusLock
{
    public const NAME_PREFIX        = 'mp_pix_status_';
    public const REFUND_NAME_PREFIX = 'mp_refund_acc_';

    private string $namePrefix;

    /**
     * Keys held by this PHP process. Only used to refuse re-entrancy; exclusion
     * between requests always comes from the database.
     *
     * @var array<string, int>
     */
    private static array $held = [];

    /** @var \wpdb */
    private $database;

    public function __construct($database = null, string $namePrefix = self::NAME_PREFIX)
    {
        $this->namePrefix = $namePrefix;

        if ($database === null) {
            global $wpdb;
            $database = $wpdb;
        }
        $this->database = $database;
    }

    /**
     * Whether this PHP process already holds the lock of the order (re-entrant call from a hook).
     */
    public function isHeld(int $orderId): bool
    {
        return isset(self::$held[$this->name($orderId)]);
    }

    /**
     * @param int $waitSeconds how long to wait for a worker that owns it (0 = do not wait)
     * @return array{name: string, connection_id: int}
     * @throws OrderStatusLockException busy when another worker owns it, unavailable when the DB cannot tell.
     */
    public function acquire(int $orderId, int $waitSeconds = 0): array
    {
        $name = $this->name($orderId);

        if (isset(self::$held[$name])) {
            throw new OrderStatusLockException(OrderStatusLockException::REASON_BUSY);
        }

        $acquired = $this->scalar('SELECT GET_LOCK(%s, ' . max(0, $waitSeconds) . ')', $name);
        if ($acquired === null) {
            throw new OrderStatusLockException(OrderStatusLockException::REASON_UNAVAILABLE);
        }
        if ((string) $acquired !== '1') {
            throw new OrderStatusLockException(OrderStatusLockException::REASON_BUSY);
        }

        $connectionId = $this->scalar('SELECT CONNECTION_ID()');
        if (!is_numeric($connectionId)) {
            // Cannot prove ownership later: give the lock back and refuse to enter the critical section.
            $this->scalar('SELECT RELEASE_LOCK(%s)', $name);
            throw new OrderStatusLockException(OrderStatusLockException::REASON_UNAVAILABLE);
        }

        self::$held[$name] = (int) $connectionId;

        return ['name' => $name, 'connection_id' => (int) $connectionId];
    }

    /**
     * Confirms that this connection still owns the lock (e.g. no silent reconnection happened).
     *
     * @param array{name: string, connection_id: int} $lock
     * @throws OrderStatusLockException
     */
    public function assertOwned(array $lock): void
    {
        $owner = $this->scalar('SELECT IS_USED_LOCK(%s)', $lock['name']);

        if (!is_numeric($owner) || (int) $owner !== $lock['connection_id']) {
            throw new OrderStatusLockException(OrderStatusLockException::REASON_LOST);
        }
    }

    /**
     * @param array{name: string, connection_id: int} $lock
     * @return bool false when the database did not confirm the release
     */
    public function release(array $lock): bool
    {
        unset(self::$held[$lock['name']]);

        return (string) $this->scalar('SELECT RELEASE_LOCK(%s)', $lock['name']) === '1';
    }

    /**
     * Scoped to site + order, shorter than the 64 chars MySQL accepts.
     */
    private function name(int $orderId): string
    {
        $dbName = defined('DB_NAME') ? (string) DB_NAME : '';

        return $this->namePrefix . substr(
            hash('sha256', $dbName . ':' . $this->database->prefix . ':' . $orderId),
            0,
            40
        );
    }

    /**
     * @param string ...$args first is the SQL (with %s placeholders), the rest are values.
     * @return mixed|null null when the query failed or returned NULL
     */
    private function scalar(string $sql, string ...$args)
    {
        $query = $args ? $this->database->prepare($sql, ...$args) : $sql;

        return $this->database->get_var($query);
    }
}
