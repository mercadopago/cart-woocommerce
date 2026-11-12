<?php

use Automattic\WooCommerce\Utilities\OrderUtil;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixAttemptRepository;

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('This Smart Pix invalid-state harness must run through WP-CLI.');
}

global $wpdb;

if (!$wpdb instanceof wpdb) {
    throw new RuntimeException('The WordPress database adapter is unavailable.');
}

$plugin = $GLOBALS['mercadopago'] ?? null;
if (!is_object($plugin) || !isset($plugin->orderMetadata) || !$plugin->orderMetadata instanceof OrderMetadata) {
    throw new RuntimeException('The Mercado Pago order metadata dependency is unavailable.');
}

$scenario = (string) getenv('SMART_PIX_INVALID_STATE_SCENARIO');
if (!in_array($scenario, ['contract_only', 'key_only', 'incompatible'], true)) {
    throw new RuntimeException('The invalid-state scenario is not supported.');
}

$assertSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
};

$order = wc_create_order(['status' => 'pending']);
if (!$order instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not create the invalid-state harness order.');
}

$orderId          = $order->get_id();
$seededContract   = '';
$seededKey        = '';
$candidateKey     = 'smart_pix_invalid_candidate_' . wp_generate_uuid4();
$incompatibleKey  = 'smart_pix_invalid_existing_' . wp_generate_uuid4();

if ($scenario === 'contract_only') {
    $seededContract = OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1;
    $order->update_meta_data('_mp_payment_contract', $seededContract);
} elseif ($scenario === 'key_only') {
    $seededKey = $incompatibleKey;
    $order->update_meta_data('_mp_idempotency_key', $seededKey);
} else {
    $seededContract = 'legacy_payments_v1';
    $seededKey      = $incompatibleKey;
    $order->update_meta_data('_mp_payment_contract', $seededContract);
    $order->update_meta_data('_mp_idempotency_key', $seededKey);
}

$order->save();
$order->read_meta_data(true);

$repository = new SmartPixAttemptRepository($plugin->orderMetadata);
$rejected   = false;

try {
    $repository->createOrRead($order, $candidateKey);
} catch (RuntimeException $exception) {
    if (strpos($exception->getMessage(), 'metadata is incomplete or incompatible') === false) {
        throw new RuntimeException('The repository failed for an unexpected reason.');
    }

    $rejected = true;
}

$assertSame(true, $rejected, 'The repository accepted invalid Smart Pix attempt metadata.');

$reloadedOrder = wc_get_order($orderId);
if (!$reloadedOrder instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not reload the invalid-state harness order.');
}

$assertSame(
    $seededContract,
    (string) $reloadedOrder->get_meta('_mp_payment_contract', true),
    'The repository changed the seeded payment contract.'
);
$assertSame(
    $seededKey,
    (string) $reloadedOrder->get_meta('_mp_idempotency_key', true),
    'The repository changed the seeded idempotency key.'
);
$assertSame('', (string) $reloadedOrder->get_meta('_mp_pp_order_id', true), 'The repository created an Orders ID.');

$usesHpos          = OrderUtil::custom_orders_table_usage_is_enabled();
$metadataTable     = $usesHpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
$orderIdColumn     = $usesHpos ? 'order_id' : 'post_id';
$physicalRows      = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT meta_key, meta_value
         FROM {$metadataTable}
         WHERE {$orderIdColumn} = %d
           AND meta_key IN (%s, %s)",
        $orderId,
        '_mp_payment_contract',
        '_mp_idempotency_key'
    ),
    'ARRAY_A'
);
$expectedRowCount = $scenario === 'incompatible' ? 2 : 1;

$assertSame($expectedRowCount, count($physicalRows), 'The authoritative datastore has unexpected attempt rows.');

$physicalMetadata = [];
foreach ($physicalRows as $physicalRow) {
    $physicalMetadata[$physicalRow['meta_key']] = $physicalRow['meta_value'];
}

$assertSame(
    $seededContract,
    (string) ($physicalMetadata['_mp_payment_contract'] ?? ''),
    'The authoritative datastore has an unexpected payment contract.'
);
$assertSame(
    $seededKey,
    (string) ($physicalMetadata['_mp_idempotency_key'] ?? ''),
    'The authoritative datastore has an unexpected idempotency key.'
);

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
    $assertSame(0, $nonAuthoritativeAttemptRows, 'The invalid HPOS state leaked into postmeta.');
}

$output = wp_json_encode(
    [
        'datastore'                    => $usesHpos ? 'hpos' : 'posts',
        'scenario'                     => $scenario,
        'order_id'                     => $orderId,
        'rejected'                     => $rejected,
        'state_unchanged'               => true,
        'authoritative_attempt_rows'    => count($physicalRows),
        'non_authoritative_attempt_rows' => $nonAuthoritativeAttemptRows,
        'legacy_placeholder_type'       => $legacyPlaceholderType,
        'orders_id_absent'              => true,
    ],
    JSON_UNESCAPED_SLASHES
);
if (!is_string($output)) {
    throw new RuntimeException('The invalid-state harness result could not be encoded.');
}

fwrite(STDOUT, $output . PHP_EOL);
