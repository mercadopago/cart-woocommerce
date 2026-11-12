<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce;

use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/** Request-local scope shared only by the validated applicator and the native save guard. */
final class SmartPixNativeEffects
{
    private ?WC_Order $order = null;

    public function isApplying(WC_Order $order): bool
    {
        return $this->order === $order;
    }

    /** Reconcile an outer merchant save after another native object applied the decision. */
    public function refresh(WC_Order $order): void
    {
        $this->clearTransition($order);
        $changes = $order->get_changes();
        unset($changes['status'], $changes['date_paid'], $changes['date_completed'], $changes['transaction_id']);
        // Legacy CRUD writes post_status together with these props. Replaying them from
        // a blocked save could overwrite another initiator's terminal decision.
        unset($changes['date_created'], $changes['date_modified'], $changes['parent_id'], $changes['customer_note']);
        $order->get_data_store()->read($order);
        $order->set_props($changes);
    }

    /** Clear before fallible reads: WC_Order::save emits queued hooks even after CRUD errors. */
    public function clearTransition(WC_Order $order): void
    {
        // CRUD read resets changes but not WC_Order's queued status hooks. There is no
        // public reset API; restrict this compatibility bridge to that protected field.
        $clearTransition = \Closure::bind(static function (WC_Order $candidate): void {
            $candidate->status_transition = false;
        }, null, WC_Order::class);
        $clearTransition($order);
    }

    /** The applicator invokes this only after response, linkage and transition validation. */
    public function cancel(WC_Order $order, callable $effect): bool
    {
        $previous = $this->order;
        $this->order = $order;
        try {
            return $effect() === true;
        } finally {
            $this->order = $previous;
        }
    }
}
