<?php

declare(strict_types=1);

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixEffectOwnership;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;

if (!defined('WP_CLI') || !WP_CLI || getenv('SMART_PIX_EFFECT_HARNESS_ENABLED') !== '1') {
    throw new RuntimeException('This harness requires explicit isolated WP-CLI execution.');
}

$sourceRoot = dirname(__DIR__, 2);
$pluginRoot = getenv('SMART_PIX_EFFECT_PLUGIN_ROOT') ?: $sourceRoot;
require $pluginRoot . '/vendor/autoload.php';
require_once $sourceRoot . '/src/SmartPix/Contracts/SmartPixEffectOwnershipStore.php';
require_once $sourceRoot . '/src/SmartPix/Adapters/Persistence/SmartPixEffectOwnership.php';
require_once $sourceRoot . '/src/SmartPix/UseCases/SmartPixStateApplicator.php';

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace -- Isolated WP-CLI harness.
final class SmartPixEffectHarnessBarrierStore implements SmartPixEffectOwnershipStore
{
    private SmartPixEffectOwnership $store;
    private string $directory;
    private string $role;
    private string $scenario;

    public function __construct(SmartPixEffectOwnership $store, string $directory, string $role, string $scenario)
    {
        $this->store = $store;
        $this->directory = $directory;
        $this->role = $role;
        $this->scenario = $scenario;
    }

    public function acquire(int $orderId, string $attemptKey, string $effect): array
    {
        file_put_contents($this->directory . '/ready-' . $this->role, 'ready', LOCK_EX);
        $this->waitFor('ready-' . ($this->role === 'A' ? 'B' : 'A'));
        $claim = $this->store->acquire($orderId, $attemptKey, $effect);
        if ($claim['outcome'] !== 'acquired') {
            file_put_contents($this->directory . '/denied', 'denied', LOCK_EX);
        }
        return $claim;
    }

    public function start(int $orderId, string $attemptKey, string $effect, string $owner): bool
    {
        $started = $this->store->start($orderId, $attemptKey, $effect, $owner);
        if ($started) {
            // The owner cannot finish before its competing worker has attempted admission.
            $this->waitFor('denied');
            if ($this->scenario === 'crash_before_native') {
                posix_kill(getmypid(), 9); // POSIX SIGKILL; the pcntl constants are not required.
            }
        }
        return $started;
    }

    public function complete(int $orderId, string $attemptKey, string $effect, string $owner): bool
    {
        return $this->store->complete($orderId, $attemptKey, $effect, $owner);
    }

    public function markUncertain(int $orderId, string $attemptKey, string $effect, string $owner): bool
    {
        return $this->store->markUncertain($orderId, $attemptKey, $effect, $owner);
    }

    public function releaseReserved(int $orderId, string $attemptKey, string $effect, string $owner): bool
    {
        return $this->store->releaseReserved($orderId, $attemptKey, $effect, $owner);
    }

    private function waitFor(string $marker): void
    {
        $deadline = microtime(true) + 15;
        while (!is_file($this->directory . '/' . $marker)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('The isolated ownership barrier timed out.');
            }
            usleep(20000);
        }
    }
}
// phpcs:enable PSR1.Classes.ClassDeclaration.MissingNamespace

$scenario = getenv('SMART_PIX_EFFECT_SCENARIO') ?: 'single';
$role = getenv('SMART_PIX_EFFECT_ROLE') ?: '';
$directory = getenv('SMART_PIX_EFFECT_DIRECTORY') ?: '';
if (
    !in_array($scenario, ['single', 'existing_paid', 'concurrent', 'crash_before_native', 'crash_after_native'], true)
    || !in_array($role, ['', 'A', 'B'], true)
) {
    throw new RuntimeException('Invalid isolated ownership scenario.');
}

$metadata = new OrderMetadata(new OrderMeta(), Mockery::mock(Logs::class));
$seller = Mockery::mock(Seller::class);
$seller->shouldReceive('getSiteId')->andReturn('MLB');
$hooks = 0;
$emails = 0;
$httpCalls = 0;
WC()->mailer();
add_filter('woocommerce_email_recipient_new_order', static function (): string {
    return '';
});
add_filter('pre_wp_mail', static function ($shortCircuit, array $attributes) use (&$emails, $directory): bool {
    if ($attributes['to'] === 'synthetic@example.test') {
        ++$emails;
    }
    if ($directory !== '' && $attributes['to'] === 'synthetic@example.test') {
        file_put_contents($directory . '/events', "email\n", FILE_APPEND | LOCK_EX);
    }
    return true;
}, 10, 2);
add_filter('pre_http_request', static function () use (&$httpCalls, $directory) {
    ++$httpCalls;
    if ($directory !== '') {
        file_put_contents($directory . '/events', "http\n", FILE_APPEND | LOCK_EX);
    }
    return new WP_Error('isolated_harness_network_denied');
});
add_action('woocommerce_payment_complete', static function () use (&$hooks, $directory, $scenario): void {
    ++$hooks;
    if ($directory !== '') {
        file_put_contents($directory . '/events', "hook\n", FILE_APPEND | LOCK_EX);
    }
    if ($scenario === 'crash_after_native') {
        posix_kill(getmypid(), 9); // POSIX SIGKILL; the pcntl constants are not required.
    }
});

if ($role === '') {
    $product = new WC_Product_Simple();
    $product->set_name('Synthetic ownership product');
    $product->set_regular_price('10.00');
    $product->set_manage_stock(true);
    $product->set_stock_quantity(10);
    $product->save();
    $order = wc_create_order();
    $order->set_currency('BRL');
    $order->set_payment_method(PixGateway::ID);
    $order->set_billing_email('synthetic@example.test');
    $order->add_product($product, 1);
    $order->calculate_totals();
    $order->save();
} else {
    $order = wc_get_order((int) getenv('SMART_PIX_EFFECT_ORDER_ID'));
    if (!$order instanceof WC_Order || $directory === '' || !is_dir($directory)) {
        throw new RuntimeException('Invalid isolated worker context.');
    }
}
$attempt = 'SYNTHETIC_EFFECT_ATTEMPT_' . $order->get_id();
$reference = 'ORDER_SYNTHETIC_' . $order->get_id();
if ($role === '') {
    $metadata->persistSmartPixAttempt($order, $attempt);
    $metadata->setPpOrderId($order, $reference);
}
$response = (new SmartPixOrderNormalizer())->normalize([
    'id' => $reference, 'external_reference' => 'WC-' . $order->get_id(),
    'type' => 'online', 'site_id' => 'MLB', 'currency' => 'BRL',
    'total_amount' => '10.00', 'total_paid_amount' => '10.00',
    'status' => 'processed', 'status_detail' => 'accredited',
    'payments' => [[
        'id' => 'PAYMENT_SYNTHETIC_' . $order->get_id(),
        'status' => 'processed', 'status_detail' => 'accredited',
        'amount' => '10.00', 'paid_amount' => '10.00',
        'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
    ]],
], $reference, ['value' => '10.00', 'currency' => 'BRL'], 'WC-' . $order->get_id());
global $wpdb;
$ownership = new SmartPixEffectOwnership($wpdb);
$workerOutcomes = [];
$existingPaid = $scenario === 'existing_paid';
if ($existingPaid) {
    // Build a historical paid fixture without an ownership journal, then measure only protected calls.
    if (!$order->payment_complete()) {
        throw new RuntimeException('The isolated historical paid fixture could not complete.');
    }
    $hooks = 0;
    $emails = 0;
}
if ($role !== '') {
    $barrier = new SmartPixEffectHarnessBarrierStore($ownership, $directory, $role, $scenario);
    $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $barrier);
    $result = $applicator->apply($order->get_id(), $attempt, $response);
    file_put_contents($directory . '/result-' . $role, json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX);
    return;
}

$applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership);
if ($scenario === 'single' || $existingPaid) {
    $result = $applicator->apply($order->get_id(), $attempt, $response);
} else {
    if (!function_exists('proc_open') || !function_exists('posix_kill')) {
        throw new RuntimeException('The isolated harness requires independent processes and POSIX signals.');
    }
    $directory = sys_get_temp_dir() . '/smart-pix-effect-' . wp_generate_uuid4();
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('The isolated worker directory could not be created.');
    }
    $processes = [];
    $deadline = microtime(true) + 30;
    try {
        foreach (['A', 'B'] as $workerRole) {
            $command = [PHP_BINARY, getenv('SMART_PIX_EFFECT_WP_CLI') ?: '/usr/local/bin/wp',
                '--allow-root', '--path=' . ABSPATH, 'eval', 'require ' . var_export(__FILE__, true) . ';'];
            $environment = array_merge(getenv(), [
                'SMART_PIX_EFFECT_ROLE' => $workerRole, 'SMART_PIX_EFFECT_ORDER_ID' => (string) $order->get_id(),
                'SMART_PIX_EFFECT_DIRECTORY' => $directory,
            ]);
            $pipes = [];
            $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
            if (!is_resource($process)) {
                throw new RuntimeException('An isolated worker could not start.');
            }
            // Register immediately so a later startup failure cannot abandon an earlier worker.
            $processes[$workerRole] = ['process' => $process, 'pipes' => $pipes, 'exit' => null];
            fclose($pipes[0]);
            if (!stream_set_blocking($pipes[1], false) || !stream_set_blocking($pipes[2], false)) {
                throw new RuntimeException('The isolated worker streams could not be configured.');
            }
        }
        do {
            $running = false;
            foreach ($processes as $workerRole => $worker) {
                if ($worker['exit'] !== null) {
                    continue;
                }
                // Drain bounded chunks without retaining or exposing potentially sensitive diagnostics.
                stream_get_contents($worker['pipes'][1], 8192);
                stream_get_contents($worker['pipes'][2], 8192);
                $status = proc_get_status($worker['process']);
                if ($status === false) {
                    throw new RuntimeException('The isolated worker status could not be read.');
                }
                if ($status['running']) {
                    $running = true;
                } else {
                    // Retain the first terminal status: proc_close may return -1 after this read.
                    $processes[$workerRole]['exit'] = $status['signaled'] ? $status['termsig'] : $status['exitcode'];
                }
            }
            if ($running) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('The isolated workers exceeded their global deadline.');
                }
                usleep(20000);
            }
        } while ($running);
        foreach ($processes as $workerRole => $worker) {
            $exit = $worker['exit'];
            if ($exit !== 0) {
                if ($scenario === 'concurrent' || !in_array($exit, [9, 137], true)) {
                    throw new RuntimeException('An isolated worker failed.');
                }
                $workerOutcomes[] = 'interrupted';
            } else {
                $workerResult = json_decode(file_get_contents($directory . '/result-' . $workerRole), true, 4, JSON_THROW_ON_ERROR);
                $workerOutcomes[] = $workerResult['outcome'];
            }
        }
    } finally {
        // Terminate every remaining child before reaping any, including partial startup failures.
        foreach ($processes as $worker) {
            if (is_resource($worker['process'])) {
                $status = proc_get_status($worker['process']);
                if ($status === false || $status['running']) {
                    proc_terminate($worker['process'], 9);
                }
            }
        }
        foreach ($processes as $worker) {
            foreach ($worker['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($worker['process'])) {
                proc_close($worker['process']);
            }
        }
    }
    $events = is_file($directory . '/events') ? file($directory . '/events', FILE_IGNORE_NEW_LINES) : [];
    $httpCalls += count(array_filter($events, static function ($event): bool {
        return $event === 'http';
    }));
    $hooks = count(array_filter($events, static function ($event): bool {
        return $event === 'hook';
    }));
    $emails = count(array_filter($events, static function ($event): bool {
        return $event === 'email';
    }));
    // Verify from a fresh parent snapshot, independent from its pre-worker object cache.
    wp_cache_flush();
    $result = ['outcome' => $scenario === 'concurrent' ? 'applied' : 'interrupted'];
}
$replay = $applicator->apply($order->get_id(), $attempt, $response);
$product = wc_get_product($product->get_id());
$rawRecord = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    '_mp_smart_pix_effect_owner_' . $order->get_id()
));
$record = $rawRecord === null ? null : json_decode($rawRecord, true, 4, JSON_THROW_ON_ERROR);
$evidence = [
    'datastore' => $order->get_data_store()->get_current_class_name(),
    'scenario' => $scenario, 'worker_outcomes' => $workerOutcomes,
    'outcome' => $result['outcome'], 'replay' => $replay['outcome'],
    'stock_before' => $existingPaid ? 9 : 10, 'stock_after' => $product->get_stock_quantity(),
    'completion_hooks' => $hooks, 'intercepted_customer_emails' => $emails,
    'ownership_state' => $record === null ? null : $record['state'], 'outbound_http_calls' => $httpCalls,
];
$crashed = strpos($scenario, 'crash_') === 0;
$beforeNative = $scenario === 'crash_before_native';
$expectedWorkers = ($scenario === 'single' || $existingPaid) ? [] : ($crashed ? ['busy', 'interrupted'] : ['applied', 'busy']);
sort($workerOutcomes);
$expectedStock = $beforeNative ? 10 : 9;
$expectedEffects = ($beforeNative || $existingPaid) ? 0 : 1;
if (
    $workerOutcomes !== $expectedWorkers || $replay['outcome'] !== ($crashed ? 'busy' : 'no_change')
    || $product->get_stock_quantity() !== $expectedStock || $hooks !== $expectedEffects || $emails !== $expectedEffects
    || ($record === null ? null : $record['state']) !== ($existingPaid ? null : ($crashed ? 'executing' : 'completed'))
    || ($existingPaid && $result['outcome'] !== 'no_change') || $httpCalls !== 0
) {
    throw new RuntimeException('Unexpected isolated native-effect evidence: ' . json_encode($evidence));
}
echo json_encode($evidence, JSON_THROW_ON_ERROR) . PHP_EOL;
