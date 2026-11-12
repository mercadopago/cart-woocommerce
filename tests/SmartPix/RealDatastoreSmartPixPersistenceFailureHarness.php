<?php

use Automattic\WooCommerce\Utilities\OrderUtil;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixAttemptRepository;

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('This Smart Pix persistence-failure harness must run through WP-CLI.');
}

global $wpdb;

if (!$wpdb instanceof wpdb) {
    throw new RuntimeException('The WordPress database adapter is unavailable.');
}

$plugin = $GLOBALS['mercadopago'] ?? null;
if (!is_object($plugin) || !isset($plugin->orderMetadata) || !$plugin->orderMetadata instanceof OrderMetadata) {
    throw new RuntimeException('The Mercado Pago order metadata dependency is unavailable.');
}

$assertSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
};

$order = wc_create_order(['status' => 'pending']);
if (!$order instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not create the persistence-failure harness order.');
}

$orderId              = $order->get_id();
$candidateIdempotency = 'smart_pix_persistence_failure_' . wp_generate_uuid4();
$repository           = new SmartPixAttemptRepository($plugin->orderMetadata);
$failureObserved      = false;
$failureMode          = '';
$transactionStarted   = false;
$rollbackFailed       = false;
$previousSuppression  = $wpdb->suppress_errors(true);

try {
    if ($wpdb->query('START TRANSACTION READ ONLY') === false) {
        throw new RuntimeException('The database could not start a read-only transaction.');
    }

    $transactionStarted = true;

    try {
        $repository->createOrRead($order, $candidateIdempotency);
    } catch (Throwable $exception) {
        if (
            $exception instanceof RuntimeException
            && $exception->getMessage() === 'The Smart Pix attempt persistence could not be verified.'
        ) {
            $failureMode = 'reload_verification';
        } elseif ($exception instanceof WC_Data_Exception) {
            $failureMode = 'woocommerce_crud_exception';
        } else {
            throw new RuntimeException('The denied write produced an unexpected failure type.');
        }

        $failureObserved = true;
    }
} finally {
    if ($transactionStarted && $wpdb->query('ROLLBACK') === false) {
        $rollbackFailed = true;
    }

    $wpdb->suppress_errors($previousSuppression);
}

$assertSame(false, $rollbackFailed, 'The database could not roll back the read-only transaction.');
$assertSame(true, $failureObserved, 'The repository did not fail closed when datastore writes were denied.');

$reloadedOrder = wc_get_order($orderId);
if (!$reloadedOrder instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not reload the persistence-failure harness order.');
}

$assertSame('', (string) $reloadedOrder->get_meta('_mp_payment_contract', true), 'A payment contract was persisted.');
$assertSame('', (string) $reloadedOrder->get_meta('_mp_idempotency_key', true), 'An idempotency key was persisted.');
$assertSame('', (string) $reloadedOrder->get_meta('_mp_pp_order_id', true), 'An Orders ID was persisted.');
$assertSame('pending', $reloadedOrder->get_status(), 'The evidence order status changed unexpectedly.');

$usesHpos      = OrderUtil::custom_orders_table_usage_is_enabled();
$metadataTable = $usesHpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
$orderIdColumn = $usesHpos ? 'order_id' : 'post_id';
$attemptRows   = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$metadataTable}
         WHERE {$orderIdColumn} = %d
           AND meta_key IN (%s, %s)",
        $orderId,
        '_mp_payment_contract',
        '_mp_idempotency_key'
    )
);
$assertSame(0, $attemptRows, 'The failed write left attempt metadata in the authoritative datastore.');

$nonAuthoritativeAttemptRows = 0;
$legacyPlaceholderType       = '';
if ($usesHpos) {
    $legacyPlaceholderType = (string) $wpdb->get_var(
        $wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $orderId)
    );
    $assertSame(
        'shop_order_placehold',
        $legacyPlaceholderType,
        'The HPOS order does not have the expected legacy ID placeholder.'
    );

    $nonAuthoritativeAttemptRows = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->postmeta}
             WHERE post_id = %d
               AND meta_key IN (%s, %s)",
            $orderId,
            '_mp_payment_contract',
            '_mp_idempotency_key'
        )
    );
    $assertSame(0, $nonAuthoritativeAttemptRows, 'The failed HPOS write leaked into postmeta.');
} else {
    $postType = (string) $wpdb->get_var(
        $wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $orderId)
    );
    $assertSame('shop_order', $postType, 'The legacy evidence order has an unexpected post type.');
}

$output = wp_json_encode(
    [
        'datastore'                     => $usesHpos ? 'hpos' : 'posts',
        'order_id'                      => $orderId,
        'failure_observed'              => $failureObserved,
        'failure_mode'                  => $failureMode,
        'authoritative_attempt_rows'     => $attemptRows,
        'non_authoritative_attempt_rows' => $nonAuthoritativeAttemptRows,
        'legacy_placeholder_type'        => $legacyPlaceholderType,
        'orders_id_absent'               => true,
        'order_status'                   => $reloadedOrder->get_status(),
    ],
    JSON_UNESCAPED_SLASHES
);
if (!is_string($output)) {
    throw new RuntimeException('The persistence-failure harness result could not be encoded.');
}

fwrite(STDOUT, $output . PHP_EOL);
