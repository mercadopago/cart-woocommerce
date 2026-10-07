<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use MercadoPago\Woocommerce\Order\RefundReconciliationLock;
use PHPUnit\Framework\TestCase;

class RefundReconciliationLockTest extends TestCase
{
    public function testOnlyOneWorkerCanOwnAnOrderPaymentAtATime(): void
    {
        $database = new RefundReconciliationLockDatabase();
        $first = new RefundReconciliationLock($database);
        $second = new RefundReconciliationLock($database);

        $firstLock = $first->acquire(74, '12345');
        $this->assertNotNull($firstLock);
        $this->assertNull($second->acquire(74, '12345'));
        $this->assertNotNull($second->acquire(74, '67890'));

        $this->assertTrue($first->release($firstLock));
        $this->assertNotNull($second->acquire(74, '12345'));
    }

    public function testExpiredOwnerCannotReleaseItsReplacement(): void
    {
        $database = new RefundReconciliationLockDatabase();
        $first = new RefundReconciliationLock($database);
        $second = new RefundReconciliationLock($database);

        $expiredLock = $first->acquire(74, '12345');
        $this->assertNotNull($expiredLock);
        $expiredLock['value'] = '1:expired-owner';
        $database->rows[$expiredLock['name']]['option_value'] = $expiredLock['value'];

        $replacement = $second->acquire(74, '12345');
        $this->assertNotNull($replacement);
        $this->assertFalse($first->release($expiredLock));
        $this->assertNull($first->acquire(74, '12345'));
        $this->assertTrue($second->release($replacement));
    }
}

/** In-memory model of the options table's unique name and conditional writes. */
class RefundReconciliationLockDatabase
{
    public string $options = 'wp_options';

    public string $prefix = 'wp_';

    public array $rows = [];

    public function insert($table, array $data, array $format)
    {
        if (isset($this->rows[$data['option_name']])) {
            return false;
        }
        $this->rows[$data['option_name']] = $data;
        return 1;
    }

    public function prepare($query, $name): string
    {
        return $name;
    }

    public function get_var($name)
    {
        return $this->rows[$name]['option_value'] ?? null;
    }

    public function update($table, array $data, array $where, array $format, array $whereFormat)
    {
        $name = $where['option_name'];
        if (!isset($this->rows[$name]) || $this->rows[$name]['option_value'] !== $where['option_value']) {
            return 0;
        }
        $this->rows[$name]['option_value'] = $data['option_value'];
        return 1;
    }

    public function delete($table, array $where, array $whereFormat)
    {
        $name = $where['option_name'];
        if (!isset($this->rows[$name]) || $this->rows[$name]['option_value'] !== $where['option_value']) {
            return 0;
        }
        unset($this->rows[$name]);
        return 1;
    }
}
