<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderOperations;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRefundContext;
use RuntimeException;
use WC_Order;

if (
    !defined('WP_CLI') || !WP_CLI || getenv('SMART_PIX_MUTATION_HARNESS') !== '1'
    || !in_array(parse_url(get_option('home'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)
) {
    throw new RuntimeException('This synthetic mutation harness requires explicit local WP-CLI authorization.');
}

require_once __DIR__ . '/MutationHarnessSeller.php';
require_once __DIR__ . '/MutationHarnessClient.php';

$assert = static function (bool $condition, string $reason): void {
    if (!$condition) {
        throw new RuntimeException($reason);
    }
};
$plugin = $GLOBALS['mercadopago'];
$assert($plugin->orderMetadata instanceof OrderMetadata, 'Native metadata dependency missing.');
$admin = get_user_by('login', 'admin');
$assert($admin !== false, 'Local admin fixture missing.');
wp_set_current_user($admin->ID);

// No provider network traffic is permitted during the synthetic native lifecycle tests.
add_filter('pre_http_request', static function () {
    return new \WP_Error('synthetic_harness_network_blocked');
}, PHP_INT_MAX);

$client = new MutationHarnessClient();
$metrics = [];
$effects = new \MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects();
$applicator = new \MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator(
    $plugin->orderMetadata,
    new \MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer(),
    new MutationHarnessSeller(),
    $effects
);
$processor = new SmartPixMutationProcessor(
    $client,
    $plugin->orderMetadata,
    new MutationHarnessSeller(),
    new SmartPixOperationRepository(),
    static function ($event, $value, $message) use (&$metrics): void {
        $metrics[] = [$event, $value, $message];
    },
    [$applicator, 'apply']
);
$context = new SmartPixRefundContext();
$service = new SmartPixOrderOperations($plugin->orderMetadata, $processor, $context, null, $effects);
$original = $plugin->smartPixOperations;
remove_action('woocommerce_create_refund', [$original->getRefundContext(), 'capture'], 10);
remove_action('woocommerce_before_order_object_save', [$original, 'beforeSave'], 10);
add_action('woocommerce_create_refund', [$context, 'capture'], 10, 2);
add_action('woocommerce_before_order_object_save', [$service, 'beforeSave'], 10);
$plugin->smartPixOperations = $service;

$create = static function (bool $paid) use ($plugin): WC_Order {
    $order = wc_create_order(['status' => $paid ? 'processing' : 'pending']);
    $order->set_payment_method(PixGateway::ID);
    $order->set_currency('BRL');
    $order->set_total('125.50');
    if ($paid) {
        $order->set_date_paid(time());
    }
    $order->update_meta_data('_psw_4772_synthetic_order', 'yes');
    $order->save();
    $plugin->orderMetadata->persistSmartPixAttempt($order, bin2hex(random_bytes(32)));
    $plugin->orderMetadata->linkSmartPixOrder($order, 'ORDER_SYNTHETIC_' . $order->get_id(), time());
    return $order;
};

$paid = $create(true);
$partial = wc_create_refund(['order_id' => $paid->get_id(), 'amount' => 25.50, 'refund_payment' => true]);
$assert($partial instanceof \WC_Order_Refund, 'Native partial refund failed.');
$assert(count($client->calls) === 1, 'Partial refund did not dispatch exactly once.');
$assert($processor->refund(new WC_Order($paid->get_id()), 'refund_' . $partial->get_id(), '25.50')['success'], 'Replay was not recognized.');
$assert(count($client->calls) === 1, 'Replay duplicated outbound refund.');
$full = wc_create_refund(['order_id' => $paid->get_id(), 'amount' => 100.00, 'refund_payment' => true]);
$assert($full instanceof \WC_Order_Refund, 'Native remaining-balance refund failed.');
$assert(count($client->calls) === 2 && $client->calls[0]['key'] !== $client->calls[1]['key'], 'Distinct partials reused an operation key.');
$assert((new WC_Order($paid->get_id()))->get_status('edit') === 'refunded', 'WooCommerce did not mark the total refund.');

$pending = $create(false);
$pending->update_status('cancelled');
$assert((new WC_Order($pending->get_id()))->get_status('edit') === 'cancelled', 'Confirmed manual cancellation failed.');
$assert(count($client->calls) === 3 && $client->calls[2]['operation'] === 'cancel', 'Manual cancellation did not use Orders.');

$unconfirmed = $create(false);
$unconfirmed->set_total('130.00');
$unconfirmed->update_status('cancelled');
$assert((new WC_Order($unconfirmed->get_id()))->get_status('edit') === 'pending', 'Unconfirmed manual cancellation was persisted.');

// Inject the synthetic Orders dependency into the real unpaid-order filter orchestration.
$reconciliation = new \MercadoPago\Woocommerce\Order\PaymentReconciliation(
    $plugin->orderMetadata,
    $plugin->orderStatus,
    $plugin->helpers->requester,
    $plugin->logs,
    $service
);
$reflection = new \ReflectionProperty($plugin->hooks->order, 'paymentReconciliation');
$reflection->setAccessible(true);
$reflection->setValue($plugin->hooks->order, $reconciliation);
$expiring = $create(false);
$assert(apply_filters('woocommerce_cancel_unpaid_order', true, $expiring) === true, 'Native unpaid-order filter did not confirm Orders cancellation.');
$assert(count($client->calls) === 4 && $client->calls[3]['operation'] === 'cancel', 'Expiration did not dispatch Orders cancellation.');
$expiring->update_status('cancelled');
$assert(count($client->calls) === 4, 'Saving expiration cancellation duplicated the provider effect.');
$assert(in_array(['smart_pix_pending_expiration', 'cancel_requested', null], $metrics, true), 'Expiration telemetry missing.');

// A validated provider/status cancellation must not initiate another merchant mutation,
// even while the polling buyer is logged in as the admin of this isolated store.
$observed = $create(false);
$response = json_decode(file_get_contents(__DIR__ . '/fixtures/orders/orders-cancel-terminal-success.json'), true)['response'];
$response['order_reference'] = $observed->get_meta('_mp_pp_order_id');
$applicator = new \MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator(
    $plugin->orderMetadata,
    new \MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer(),
    new MutationHarnessSeller(),
    $effects
);
$beforeProviderEffect = count($client->calls);
$applied = $applicator->apply($observed->get_id(), $observed->get_meta('_mp_idempotency_key'), $response);
$assert($applied['outcome'] === 'applied', 'Validated provider cancellation was blocked by the merchant guard.');
$assert(count($client->calls) === $beforeProviderEffect, 'Applying provider cancellation initiated another Orders mutation.');

// Count actual native hooks, including stale objects and an overlapping merchant call.
$transitions = [];
add_action('woocommerce_order_status_changed', static function ($id, $from, $to) use (&$transitions): void {
    $transitions[$id][] = [$from, $to];
}, 100, 3);
$overlap = $create(false);
$stale = new WC_Order($overlap->get_id());
$stale->set_customer_note('STALE_SYNTHETIC_NOTE');
$before = count($client->calls);
$client->onCancel = static function () use ($stale): void {
    $stale->update_status('cancelled');
};
$overlap->update_status('cancelled');
$assert(count($client->calls) === $before + 1, 'Overlapping merchant cancellation duplicated the POST.');
$assert(($transitions[$overlap->get_id()] ?? []) === [['pending', 'cancelled']], 'Overlapping cancellation duplicated native hooks.');
$assert((new WC_Order($overlap->get_id()))->get_customer_note('edit') !== 'STALE_SYNTHETIC_NOTE', 'Blocked save retained a legacy status-writing prop.');
$late = $create(false);
$lateStale = new WC_Order($late->get_id());
$late->update_status('cancelled');
$lateStale->update_status('cancelled');
$assert(($transitions[$late->get_id()] ?? []) === [['pending', 'cancelled']], 'A stale successful replay duplicated native hooks.');

$approvedRace = $create(false);
$client->queryState = 'approved';
$approvedRace->set_billing_first_name('Synthetic');
$approvedRace->update_status('cancelled');
$client->queryState = null;
$nativeApproved = new WC_Order($approvedRace->get_id());
$assert($nativeApproved->is_paid() && $nativeApproved->get_date_paid('edit') !== null, 'Concurrent approval was not applied natively.');
$assert(($transitions[$approvedRace->get_id()] ?? []) === [['pending', $nativeApproved->get_status('edit')]], 'Concurrent approval duplicated native hooks.');
$assert($nativeApproved->get_billing_first_name('edit') === 'Synthetic', 'Refreshing cancellation lost an independent billing edit.');

$readFailure = $create(false);
$client->failQuery = true;
$before = count($client->calls);
$readFailure->update_status('cancelled');
$journal = json_decode((new WC_Order($readFailure->get_id()))->get_meta(SmartPixOperationRepository::META_KEY), true);
$assert(reset($journal)['status'] === 'read_failed' && count($client->calls) === $before, 'Initial GET failure did not retain safe retry evidence.');
$readFailure->update_status('cancelled');
$assert((new WC_Order($readFailure->get_id()))->get_status('edit') === 'cancelled' && count($client->calls) === $before + 1, 'Transient GET failure permanently blocked cancellation.');
$assert(($transitions[$readFailure->get_id()] ?? []) === [['pending', 'cancelled']], 'Read retry emitted an extra native transition.');

// Exercise the real WC_Order::save exception path; no synthetic status setter is involved.
foreach (['read', 'read_meta'] as $failingMethod) {
    $readException = $create(false);
    $client->failQuery = $failingMethod === 'read';
    $storeProperty = new \ReflectionProperty(\WC_Data::class, 'data_store');
    $storeProperty->setAccessible(true);
    $storeProperty->setValue($readException, new class ($readException->get_data_store(), $failingMethod) {
        private $original;
        private string $failingMethod;
        public function __construct($original, string $failingMethod)
        {
            $this->original = $original;
            $this->failingMethod = $failingMethod;
        }
        public function __call($method, $arguments)
        {
            if ($method === $this->failingMethod) {
                throw new RuntimeException('SYNTHETIC_DATASTORE_READ_FAILURE');
            }
            return $this->original->{$method}(...$arguments);
        }
    });
    $readException->update_status('cancelled');
    $assert(($transitions[$readException->get_id()] ?? []) === [], 'Datastore read exception emitted the abandoned cancellation hooks.');
    $assert((new WC_Order($readException->get_id()))->get_status('edit') === 'pending', 'Datastore read exception changed persisted status.');
}

$cancelTimeout = $create(false);
$client->cancelTimeout = true;
$before = count($client->calls);
$cancelTimeout->update_status('cancelled');
$beforeRead = $client->queries;
$cancelTimeout->update_status('cancelled');
$client->cancelTimeout = false;
$assert(count($client->calls) === $before + 1 && $client->queries === $beforeRead, 'Uncertain cancel was retried.');
$assert((new WC_Order($cancelTimeout->get_id()))->get_status('edit') === 'pending' && ($transitions[$cancelTimeout->get_id()] ?? []) === [], 'Uncertain cancel emitted native effects.');

$timeoutOrder = $create(true);
$client->timeout = true;
$failed = wc_create_refund(['order_id' => $timeoutOrder->get_id(), 'amount' => 25.50, 'refund_payment' => true]);
$assert(is_wp_error($failed), 'Timeout was reported as success.');
$reloaded = new WC_Order($timeoutOrder->get_id());
$assert($reloaded->get_refunds() === [], 'WooCommerce did not delete the failed native refund.');
$journal = json_decode($reloaded->get_meta(SmartPixOperationRepository::META_KEY), true);
$assert(is_array($journal) && count($journal) === 1 && reset($journal)['status'] === 'uncertain', 'Operation identity was lost with the deleted refund.');
$count = count($client->calls);
$again = wc_create_refund(['order_id' => $timeoutOrder->get_id(), 'amount' => 25.50, 'refund_payment' => true]);
$assert(is_wp_error($again) && count($client->calls) === $count, 'A recreated refund bypassed unresolved operation ownership.');

echo "PASS: native partial/total, replay, distinct keys, manual cancellation, edited-total guard, expiration filter, provider-origin guard, refund deletion, unresolved retry, overlapping/stale native hooks, approval race, transient GET recovery and uncertain cancel blocking.\n";
echo 'Mode: ' . (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'posts/postmeta') . "\n";
