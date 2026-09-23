<?php
/**
 * Outputs, as JSON, the still-refundable amount of every WooCommerce order line
 * (item + tax + shipping), keyed by the exact admin refund input name
 * (refund_line_total[ID] / refund_line_tax[ID][RATE]).
 *
 * The grand-total #refund_amount field in the admin is read-only — WooCommerce
 * computes it from these per-line inputs — so the E2E refund flow fills them
 * directly. Amounts are net of any previous partial refund.
 *
 * Run via: wp eval-file refund-field-map.php <order_id>
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($args[0])) {
    echo wp_json_encode(['error' => 'order_id argument is required']);
    exit(1);
}

$order = wc_get_order((int) $args[0]);
if (!$order) {
    echo wp_json_encode(['error' => "Order {$args[0]} not found"]);
    exit(1);
}

$fields = [];

$collect = function ($items, $type) use ($order, &$fields) {
    foreach ($items as $id => $item) {
        $remainingTotal = $item->get_total() - $order->get_total_refunded_for_item($id, $type);
        if ($remainingTotal > 0) {
            $fields[] = ['name' => "refund_line_total[$id]", 'value' => round($remainingTotal, 2)];
        }

        foreach ($item->get_taxes()['total'] ?? [] as $rateId => $taxTotal) {
            $remainingTax = (float) $taxTotal - $order->get_tax_refunded_for_item($id, $rateId, $type);
            if ($remainingTax > 0) {
                $fields[] = ['name' => "refund_line_tax[$id][$rateId]", 'value' => round($remainingTax, 2)];
            }
        }
    }
};

$collect($order->get_items(), 'line_item');
$collect($order->get_items('shipping'), 'shipping');

echo wp_json_encode($fields);
