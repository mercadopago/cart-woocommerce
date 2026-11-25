<?php

namespace MercadoPago\Woocommerce\Order;

use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reloads an order in place from the active data store (legacy posts or HPOS),
 * dropping every cache layer that could hand back a stale copy.
 *
 * wc_get_order() alone may return a cached object and read_meta_data(true) alone
 * does not re-read the status, so both are needed under the Pix status lock.
 */
class FreshOrderReader
{
    /**
     * Contract: the order must have no unsaved changes. apply_changes() followed by read() silently
     * discards whatever the caller changed in memory and did not save, so persist first (the
     * sync path does: OrderMetadata::updatePaymentCustomFields() ends with $order->save()) or apply
     * the change after OrderStatus::processStatus() returns.
     */
    public function refresh(WC_Order $order): void
    {
        $orderId = (int) $order->get_id();
        if ($orderId <= 0) {
            return;
        }

        $this->invalidateCaches($order, $orderId);

        // Fold pending in-memory changes into the data array first: read() must overwrite
        // them with the persisted state instead of re-applying them on a later save().
        $order->apply_changes();
        $order->set_object_read(false);

        $dataStore = $order->get_data_store();
        $dataStore->read($order);

        $order->read_meta_data(true);
    }

    private function invalidateCaches(WC_Order $order, int $orderId): void
    {
        // Legacy storage (CPT): post and post meta object cache.
        if (function_exists('clean_post_cache')) {
            clean_post_cache($orderId);
        }
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($orderId, 'posts');
            wp_cache_delete($orderId, 'post_meta');
            // HPOS keeps its own order cache group.
            wp_cache_delete('order-' . $orderId, 'orders');
            wp_cache_delete($orderId, 'orders');
        }

        // HPOS data store. $order->get_data_store() is a WC_Data_Store wrapper whose __call silently
        // ignores methods the concrete store lacks, so check the concrete class before calling.
        $dataStore = $order->get_data_store();
        $concrete  = method_exists($dataStore, 'get_current_class_name')
            ? $dataStore->get_current_class_name()
            : get_class($dataStore);

        if (method_exists($concrete, 'clear_cached_data')) {
            $dataStore->clear_cached_data([$orderId]);
        } elseif (method_exists($concrete, 'clear_caches')) {
            $dataStore->clear_caches($order);
        }
    }
}
