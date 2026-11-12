<?php

use Automattic\WooCommerce\Utilities\OrderUtil;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixAttemptRepository;

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('This Smart Pix datastore harness must run through WP-CLI.');
}

global $wpdb;

if (!$wpdb instanceof wpdb) {
    throw new RuntimeException('The WordPress database adapter is unavailable.');
}

if (OrderUtil::custom_orders_table_usage_is_enabled()) {
    throw new RuntimeException('This harness requires the posts/postmeta order datastore.');
}

$plugin = $GLOBALS['mercadopago'] ?? null;
if (!is_object($plugin) || !isset($plugin->orderMetadata) || !$plugin->orderMetadata instanceof OrderMetadata) {
    throw new RuntimeException('The Mercado Pago order metadata dependency is unavailable.');
}

$assertSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf(
                '%s Expected %s, received %s.',
                $message,
                var_export($expected, true),
                var_export($actual, true)
            )
        );
    }
};

$order = wc_create_order(['status' => 'pending']);
if (!$order instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not create the datastore harness order.');
}

$orderId              = $order->get_id();
$candidateIdempotency = 'smart_pix_posts_success_' . wp_generate_uuid4();
$repository           = new SmartPixAttemptRepository($plugin->orderMetadata);
$attempt              = $repository->createOrRead($order, $candidateIdempotency);

$assertSame(
    OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
    $attempt['payment_contract'],
    'The repository returned an unexpected payment contract.'
);
if (!hash_equals($candidateIdempotency, $attempt['idempotency_key'])) {
    throw new RuntimeException('The repository returned an unexpected idempotency key.');
}

$reloadedOrder = wc_get_order($orderId);
if (!$reloadedOrder instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not reload the datastore harness order.');
}

$assertSame(
    OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
    (string) $reloadedOrder->get_meta('_mp_payment_contract', true),
    'The reloaded order has an unexpected payment contract.'
);
if (!hash_equals($candidateIdempotency, (string) $reloadedOrder->get_meta('_mp_idempotency_key', true))) {
    throw new RuntimeException('The reloaded order has an unexpected idempotency key.');
}

$postType = (string) $wpdb->get_var(
    $wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $orderId)
);
$assertSame('shop_order', $postType, 'The order was not stored as a legacy shop_order post.');

$metadataRows = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE post_id = %d
           AND meta_key IN (%s, %s)",
        $orderId,
        '_mp_payment_contract',
        '_mp_idempotency_key'
    ),
    'ARRAY_A'
);
$assertSame(2, count($metadataRows), 'The legacy datastore did not contain exactly two attempt rows.');

$directMetadata = [];
foreach ($metadataRows as $metadataRow) {
    $directMetadata[$metadataRow['meta_key']] = $metadataRow['meta_value'];
}

$assertSame(
    OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
    $directMetadata['_mp_payment_contract'] ?? null,
    'The direct postmeta payment contract is unexpected.'
);
if (!hash_equals($candidateIdempotency, (string) ($directMetadata['_mp_idempotency_key'] ?? ''))) {
    throw new RuntimeException('The direct postmeta idempotency key is unexpected.');
}

// Persisted-attempt reuse: a fresh WC_Order instance (not the one that performed the
// first write) must reuse the already-persisted attempt instead of creating a new one,
// even when called with a different candidate idempotency key.
$reuseOrder      = wc_get_order($orderId);
if (!$reuseOrder instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not load a fresh order instance for the reuse check.');
}
$reuseCandidate  = 'smart_pix_posts_reuse_' . wp_generate_uuid4();
$reusedAttempt   = $repository->createOrRead($reuseOrder, $reuseCandidate);

$assertSame(
    $attempt['payment_contract'],
    $reusedAttempt['payment_contract'],
    'Reusing the persisted attempt returned an unexpected payment contract.'
);
if (!hash_equals($candidateIdempotency, $reusedAttempt['idempotency_key'])) {
    throw new RuntimeException('Reusing the persisted attempt did not return the original idempotency key.');
}

$metadataRowsAfterReuse = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE post_id = %d
           AND meta_key IN (%s, %s)",
        $orderId,
        '_mp_payment_contract',
        '_mp_idempotency_key'
    ),
    'ARRAY_A'
);
$assertSame(
    2,
    count($metadataRowsAfterReuse),
    'Reusing the persisted attempt added extra legacy attempt metadata rows.'
);

// Precondition proof: read_meta_data(true) forces a reload that discards any unsaved
// metadata the caller set on $order before calling createOrRead(), and the method's
// own save() persists only the Smart Pix contract/key it manages, never that pending
// unrelated change.
$preconditionOrder = wc_create_order(['status' => 'pending']);
if (!$preconditionOrder instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not create the precondition harness order.');
}
$preconditionOrderId = $preconditionOrder->get_id();
$preconditionOrder->update_meta_data('_smart_pix_precondition_probe', 'unsaved-pending-value');
$preconditionCandidate = 'smart_pix_posts_precondition_' . wp_generate_uuid4();
$repository->createOrRead($preconditionOrder, $preconditionCandidate);

$reloadedPreconditionOrder = wc_get_order($preconditionOrderId);
if (!$reloadedPreconditionOrder instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not reload the precondition harness order.');
}
$assertSame(
    '',
    (string) $reloadedPreconditionOrder->get_meta('_smart_pix_precondition_probe', true),
    "createOrRead() unexpectedly persisted the caller's unsaved unrelated metadata."
);
if (!hash_equals($preconditionCandidate, (string) $reloadedPreconditionOrder->get_meta('_mp_idempotency_key', true))) {
    throw new RuntimeException('The precondition harness order did not persist its own Smart Pix attempt.');
}

$output = wp_json_encode(
    [
        'datastore'                 => 'posts',
        'order_id'                  => $orderId,
        'post_type'                 => $postType,
        'payment_contract'          => $attempt['payment_contract'],
        'idempotency_key_verified'  => true,
        'direct_postmeta_row_count' => count($metadataRows),
        'reuse_verified'            => true,
        'reuse_row_count'           => count($metadataRowsAfterReuse),
        'precondition_verified'     => true,
        'precondition_order_id'     => $preconditionOrderId,
    ],
    JSON_UNESCAPED_SLASHES
);
if (!is_string($output)) {
    throw new RuntimeException('The datastore harness result could not be encoded.');
}

fwrite(STDOUT, $output . PHP_EOL);
