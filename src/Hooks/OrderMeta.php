<?php

namespace MercadoPago\Woocommerce\Hooks;

use WC_Meta_Data;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class OrderMeta
{
    /**
     * Get meta
     *
     * @param WC_Order $order
     * @param string $metaKey
     * @param bool $single
     *
     * @return mixed
     */
    public function get(WC_Order $order, string $metaKey, bool $single = true)
    {
        return $order->get_meta($metaKey, $single);
    }

    /**
     * Get the value of the earliest-persisted row for a meta key, resolving
     * ties deterministically when a concurrent writer left more than one row
     * behind. `get($order, $key, true)` cannot do this on its own: which row
     * a data store treats as "the" value depends on the order its own read
     * query returns rows in, and that order is only explicitly sorted by
     * insertion (`ORDER BY meta_id`) for the legacy postmeta store -- HPOS's
     * meta query has no `ORDER BY` and is not guaranteed to match insertion
     * order. Reading every row for the key and sorting locally by
     * `WC_Meta_Data::$id` (the row's real, monotonically increasing primary
     * key) is deterministic regardless of which data store is active.
     *
     * @param WC_Order $order
     * @param string $metaKey
     *
     * @return mixed
     */
    public function getEarliest(WC_Order $order, string $metaKey)
    {
        $entries = $order->get_meta($metaKey, false);

        if (empty($entries)) {
            return '';
        }

        usort($entries, static function (WC_Meta_Data $first, WC_Meta_Data $second): int {
            return ($first->id ?? PHP_INT_MAX) <=> ($second->id ?? PHP_INT_MAX);
        });

        return reset($entries)->value;
    }

    /**
     * Add metadata
     *
     * @param WC_Order $order
     * @param string $metaKey
     * @param mixed $value
     * @param bool $unique
     *
     * @return void
     */
    public function add(WC_Order $order, string $metaKey, $value, bool $unique = false): void
    {
        $order->add_meta_data($metaKey, $value, $unique);
    }

    /**
     * Set metadata
     *
     * @param WC_Order $order
     * @param string $metaKey
     * @param string|array $value
     *
     * @return void
     */
    public function update(WC_Order $order, string $metaKey, $value): void
    {
        $order->update_meta_data($metaKey, $value);
        $order->save();
    }

    /**
     * @param array<string, string> $metadata
     *
     * This boundary uses one WooCommerce CRUD save and does not claim a
     * database transaction spanning multiple metadata rows.
     */
    public function updateMany(WC_Order $order, array $metadata): void
    {
        foreach ($metadata as $metaKey => $value) {
            $order->update_meta_data($metaKey, $value);
        }

        $order->save();
    }

    /**
     * @param array<string> $metaKeys
     */
    public function deleteMany(WC_Order $order, array $metaKeys): void
    {
        foreach ($metaKeys as $metaKey) {
            $order->delete_meta_data($metaKey);
        }

        $order->save();
    }
}
