<?php

use Automattic\WooCommerce\Utilities\OrderUtil;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixAttemptRepository;
use MercadoPago\Woocommerce\Tests\SmartPix\CountingSmartPixOrderClientSpy;

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('This Smart Pix concurrency harness must run through WP-CLI.');
}

if (!function_exists('proc_open')) {
    throw new RuntimeException('The Smart Pix concurrency harness requires proc_open.');
}

if (!class_exists(CountingSmartPixOrderClientSpy::class)) {
    require_once __DIR__ . '/CountingSmartPixOrderClientSpy.php';
}

$role       = getenv('SMART_PIX_CONCURRENCY_ROLE') ?: '';
$orderId    = (int) (getenv('SMART_PIX_CONCURRENCY_ORDER_ID') ?: 0);
$barrierDir = getenv('SMART_PIX_CONCURRENCY_BARRIER') ?: '';
$candidate  = getenv('SMART_PIX_CONCURRENCY_CANDIDATE') ?: '';

if ($role !== '') {
    if (!in_array($role, ['A', 'B'], true) || $orderId <= 0 || $barrierDir === '' || $candidate === '') {
        throw new RuntimeException('The Smart Pix concurrency worker configuration is invalid.');
    }

    // phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace -- WP-CLI eval-file harness.
    if (!class_exists('SmartPixConcurrencyBarrierOrder', false)) {
        class SmartPixConcurrencyBarrierOrder extends WC_Order
        {
            private const IDEMPOTENCY_META_KEY = '_mp_idempotency_key';

            private string $role;

            private string $barrierDir;

            private bool $initialReadBarrierUsed = false;

            public function __construct(int $orderId, string $role, string $barrierDir)
            {
                $this->role       = $role;
                $this->barrierDir = $barrierDir;

                parent::__construct($orderId);
            }

            public function get_meta($key = '', $single = true, $context = 'view')
            {
                $value = parent::get_meta($key, $single, $context);

                if ($key !== self::IDEMPOTENCY_META_KEY || $this->initialReadBarrierUsed) {
                    return $value;
                }

                $this->initialReadBarrierUsed = true;
                $marker                       = $this->barrierDir . '/ready-' . $this->role;

                if (file_put_contents($marker, $this->role, LOCK_EX) === false) {
                    throw new RuntimeException('The concurrency worker could not publish its barrier marker.');
                }

                $deadline = microtime(true) + 15;
                while (!is_file($this->barrierDir . '/ready-A') || !is_file($this->barrierDir . '/ready-B')) {
                    if (microtime(true) >= $deadline) {
                        throw new RuntimeException('The concurrency workers did not reach the initial-read barrier.');
                    }

                    usleep(50000);
                }

                if ($this->role === 'B') {
                    while (!is_file($this->barrierDir . '/completed-A')) {
                        if (microtime(true) >= $deadline) {
                            throw new RuntimeException('Worker A did not complete before worker B resumed.');
                        }

                        usleep(50000);
                    }
                }

                return $value;
            }
        }
    }
    // phpcs:enable PSR1.Classes.ClassDeclaration.MissingNamespace

    $plugin = $GLOBALS['mercadopago'] ?? null;
    if (!is_object($plugin) || !isset($plugin->orderMetadata) || !$plugin->orderMetadata instanceof OrderMetadata) {
        throw new RuntimeException('The Mercado Pago order metadata dependency is unavailable.');
    }

    $order      = new SmartPixConcurrencyBarrierOrder($orderId, $role, $barrierDir);
    $repository = new SmartPixAttemptRepository($plugin->orderMetadata);
    $ordersSpy  = new CountingSmartPixOrderClientSpy($role, $barrierDir);

    $attempt = null;

    try {
        $attempt = $repository->createOrRead($order, $candidate);
        $result  = [
            'role'                   => $role,
            'outcome'                => 'success',
            'returned_own_candidate' => hash_equals($candidate, $attempt['idempotency_key']),
            'payment_contract_valid' => $attempt['payment_contract']
                === OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
            'orders_create_calls'    => $ordersSpy->getCreateCalls(),
            'error'                  => null,
        ];
    } catch (RuntimeException $exception) {
        $result = [
            'role'                   => $role,
            'outcome'                => 'failed_closed',
            'returned_own_candidate' => false,
            'payment_contract_valid' => false,
            'orders_create_calls'    => $ordersSpy->getCreateCalls(),
            'error'                  => $exception->getMessage(),
        ];
    }

    // The Orders spy call is deliberately outside the repository's try/catch: a spy
    // failure (e.g. it cannot write its evidence marker) must never be reported under
    // the same 'failed_closed' outcome/message the repository uses for its own
    // fail-closed verification, or the harness could mistake one failure for the other.
    if ($attempt !== null) {
        try {
            $ordersSpy->create(
                [
                    'surface'          => 'classic',
                    'country'          => 'MLB',
                    'payment_contract' => $attempt['payment_contract'],
                    'attempt_reference' => $attempt['idempotency_key'],
                    'order_reference'  => null,
                    'amount'           => [
                        'value'    => '1.00',
                        'currency' => 'BRL',
                    ],
                    'refund_amount'     => null,
                ]
            );
            $result['orders_create_calls'] = $ordersSpy->getCreateCalls();
        } catch (RuntimeException $exception) {
            $result = [
                'role'                   => $role,
                'outcome'                => 'spy_error',
                'returned_own_candidate' => hash_equals($candidate, $attempt['idempotency_key']),
                'payment_contract_valid' => $attempt['payment_contract']
                    === OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
                'orders_create_calls'    => $ordersSpy->getCreateCalls(),
                'error'                  => $exception->getMessage(),
            ];
        }
    }

    if ($role === 'A' && file_put_contents($barrierDir . '/completed-A', 'A', LOCK_EX) === false) {
        throw new RuntimeException('Worker A could not publish its completion marker.');
    }

    $output = wp_json_encode($result, JSON_UNESCAPED_SLASHES);
    if (!is_string($output)) {
        throw new RuntimeException('The concurrency worker result could not be encoded.');
    }

    fwrite(STDOUT, $output . PHP_EOL);
    return;
}

$plugin = $GLOBALS['mercadopago'] ?? null;
if (!is_object($plugin) || !isset($plugin->orderMetadata) || !$plugin->orderMetadata instanceof OrderMetadata) {
    throw new RuntimeException('The Mercado Pago order metadata dependency is unavailable.');
}

$order = wc_create_order(['status' => 'pending']);
if (!$order instanceof WC_Order) {
    throw new RuntimeException('WooCommerce could not create the concurrency harness order.');
}

$orderId    = $order->get_id();
$barrierDir = sys_get_temp_dir() . '/smart-pix-concurrency-' . $orderId . '-' . wp_generate_uuid4();
fwrite(STDERR, sprintf("Smart Pix concurrency harness order: %d\n", $orderId));

if (!mkdir($barrierDir, 0700, true) && !is_dir($barrierDir)) {
    throw new RuntimeException('The concurrency harness could not create its barrier directory.');
}

// Deliberately reversed so A's literal sorts *after* B's ('z' > 'y'): A still
// physically persists first (the barrier forces B to wait for completed-A),
// but if the winner were still being picked by data-store row-return order
// or by string value instead of the real, monotonically increasing meta row
// id, this would flip which worker the assertions below expect to win.
$candidates = [
    'A' => 'smart_pix_concurrency_z_' . wp_generate_uuid4(),
    'B' => 'smart_pix_concurrency_y_' . wp_generate_uuid4(),
];
$processes = [];
$results   = [];
$wpBinary  = getenv('SMART_PIX_CONCURRENCY_WP_BIN') ?: 'wp';

try {
    foreach ($candidates as $workerRole => $workerCandidate) {
        $command = sprintf(
            'SMART_PIX_CONCURRENCY_ROLE=%s SMART_PIX_CONCURRENCY_ORDER_ID=%d SMART_PIX_CONCURRENCY_BARRIER=%s SMART_PIX_CONCURRENCY_CANDIDATE=%s %s --allow-root eval-file %s',
            escapeshellarg($workerRole),
            $orderId,
            escapeshellarg($barrierDir),
            escapeshellarg($workerCandidate),
            escapeshellarg($wpBinary),
            escapeshellarg(__FILE__)
        );
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes   = [];
        $process = proc_open($command, $descriptors, $pipes, ABSPATH);

        if (!is_resource($process)) {
            throw new RuntimeException('The concurrency harness could not start worker ' . $workerRole . '.');
        }

        fclose($pipes[0]);
        $processes[$workerRole] = [
            'process' => $process,
            'stdout'  => $pipes[1],
            'stderr'  => $pipes[2],
        ];
    }

    foreach ($processes as $workerRole => $workerProcess) {
        $stdout = stream_get_contents($workerProcess['stdout']);
        $stderr = stream_get_contents($workerProcess['stderr']);

        fclose($workerProcess['stdout']);
        fclose($workerProcess['stderr']);
        $exitCode = proc_close($workerProcess['process']);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                sprintf('Concurrency worker %s failed: %s', $workerRole, trim((string) $stderr))
            );
        }

        $stdoutLines = preg_split('/\R/', trim((string) $stdout)) ?: [];
        $resultLine  = end($stdoutLines);
        $decoded     = is_string($resultLine) ? json_decode($resultLine, true) : null;
        if (!is_array($decoded)) {
            throw new RuntimeException('Concurrency worker ' . $workerRole . ' returned invalid JSON.');
        }

        $results[$workerRole] = $decoded;
    }

    $reloadedOrder = wc_get_order($orderId);
    if (!$reloadedOrder instanceof WC_Order) {
        throw new RuntimeException('WooCommerce could not reload the concurrency harness order.');
    }

    $persistedKey = (string) $plugin->orderMetadata->getIdempotencyKey($reloadedOrder);
    $winner       = 'other';
    foreach ($candidates as $workerRole => $workerCandidate) {
        if (hash_equals($workerCandidate, $persistedKey)) {
            $winner = $workerRole;
            break;
        }
    }

    global $wpdb;

    if (!$wpdb instanceof wpdb) {
        throw new RuntimeException('The WordPress database adapter is unavailable.');
    }

    $usesHpos      = OrderUtil::custom_orders_table_usage_is_enabled();
    $metadataTable = $usesHpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
    $orderIdColumn = $usesHpos ? 'order_id' : 'post_id';
    $metaIdColumn  = $usesHpos ? 'id' : 'meta_id';
    $attemptMetadataRows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value
             FROM {$metadataTable}
             WHERE {$orderIdColumn} = %d
               AND meta_key IN (%s, %s)
             ORDER BY {$metaIdColumn} ASC",
            $orderId,
            '_mp_payment_contract',
            '_mp_idempotency_key'
        ),
        ARRAY_A
    );
    $contractRows       = 0;
    $idempotencyRows    = 0;
    $persistedKeyOwners = [];

    foreach ($attemptMetadataRows as $metadataRow) {
        if ($metadataRow['meta_key'] === '_mp_payment_contract') {
            ++$contractRows;
            continue;
        }

        ++$idempotencyRows;
        foreach ($candidates as $workerRole => $workerCandidate) {
            if (hash_equals($workerCandidate, (string) $metadataRow['meta_value'])) {
                $persistedKeyOwners[] = $workerRole;
                break;
            }
        }
    }

    $nonAuthoritativeAttemptRows = 0;
    if ($usesHpos) {
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
    }

    $workerASucceededAlone = ($results['A']['outcome'] ?? '') === 'success'
        && ($results['A']['returned_own_candidate'] ?? false) === true
        && ($results['A']['payment_contract_valid'] ?? false) === true
        && ($results['A']['orders_create_calls'] ?? -1) === 1;
    $workerBFailedClosed = ($results['B']['outcome'] ?? '') === 'failed_closed'
        && ($results['B']['error'] ?? '') === 'The Smart Pix attempt persistence could not be verified.'
        && ($results['B']['orders_create_calls'] ?? -1) === 0;
    $competingKeysPersisted = count(array_unique($persistedKeyOwners)) > 1;
    $ordersCreateCallFiles  = glob($barrierDir . '/orders-create-call-*') ?: [];
    $ordersCreateCallRoles  = [];

    foreach ($ordersCreateCallFiles as $ordersCreateCallFile) {
        $evidence = json_decode((string) file_get_contents($ordersCreateCallFile), true);
        if (is_array($evidence) && isset($evidence['role']) && is_string($evidence['role'])) {
            $ordersCreateCallRoles[] = $evidence['role'];
        }
    }

    $ordersCreateCallCount = count($ordersCreateCallFiles);
    $singleOrdersCreateCall = $ordersCreateCallCount === 1;
    $ordersIdAbsent        = (string) $plugin->orderMetadata->getPpOrderId($reloadedOrder) === '';
    $diagnosticNoteId = $reloadedOrder->add_order_note(
        sprintf(
            '[Smart Pix concurrency harness] Worker A: %s; Worker B: %s; competing idempotency keys persisted: %s; authoritative metadata rows: %d; final persisted worker: %s; Orders spy create calls: %d; financial call performed: no.',
            $results['A']['outcome'] ?? 'missing',
            $results['B']['outcome'] ?? 'missing',
            $competingKeysPersisted ? 'yes' : 'no',
            count($attemptMetadataRows),
            $winner,
            $ordersCreateCallCount
        ),
        false,
        false
    );

    if ((int) $diagnosticNoteId <= 0) {
        throw new RuntimeException('The concurrency harness order note could not be persisted.');
    }

    $output = wp_json_encode(
        [
            'datastore'                      => $usesHpos ? 'hpos' : 'posts',
            'order_id'                       => $orderId,
            'worker_a_outcome'               => $results['A']['outcome'] ?? 'missing',
            'worker_b_outcome'               => $results['B']['outcome'] ?? 'missing',
            'worker_a_returned_own_key'      => $results['A']['returned_own_candidate'] ?? false,
            'worker_b_returned_own_key'      => $results['B']['returned_own_candidate'] ?? false,
            'worker_b_failed_closed'         => $workerBFailedClosed,
            'worker_a_orders_create_calls'   => $results['A']['orders_create_calls'] ?? 0,
            'worker_b_orders_create_calls'   => $results['B']['orders_create_calls'] ?? 0,
            'orders_create_call_roles'       => $ordersCreateCallRoles,
            'orders_create_call_count'       => $ordersCreateCallCount,
            'single_orders_create_call'      => $singleOrdersCreateCall,
            'competing_keys_persisted'       => $competingKeysPersisted,
            'persisted_key_owners'           => $persistedKeyOwners,
            'final_persisted_worker'         => $winner,
            'final_contract_valid'           => (string) $plugin->orderMetadata->getPaymentContract($reloadedOrder)
                === OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
            'authoritative_attempt_rows'     => count($attemptMetadataRows),
            'authoritative_contract_rows'    => $contractRows,
            'authoritative_idempotency_rows' => $idempotencyRows,
            'non_authoritative_attempt_rows' => $nonAuthoritativeAttemptRows,
            'orders_id_absent'               => $ordersIdAbsent,
            'order_status'                   => $reloadedOrder->get_status(),
            'diagnostic_order_note_id'       => (int) $diagnosticNoteId,
            'financial_call_performed'       => false,
        ],
        JSON_UNESCAPED_SLASHES
    );
    if (!is_string($output)) {
        throw new RuntimeException('The concurrency harness result could not be encoded.');
    }

    fwrite(STDOUT, $output . PHP_EOL);

    if (!$workerASucceededAlone) {
        throw new RuntimeException(
            'Worker A did not persist and confirm its own Smart Pix attempt with exactly one Orders call.'
        );
    }

    if (!$workerBFailedClosed) {
        throw new RuntimeException(
            'Worker B did not fail closed on the expected persistence-verification error with zero Orders calls.'
        );
    }

    if ($ordersCreateCallRoles !== ['A']) {
        throw new RuntimeException('The Orders spy create call is not attributable to worker A alone.');
    }

    if (!$singleOrdersCreateCall) {
        throw new RuntimeException('The Orders spy did not observe exactly one create call.');
    }

    if (!$ordersIdAbsent) {
        throw new RuntimeException('The concurrency harness order unexpectedly has an Orders id persisted.');
    }

    if (!$competingKeysPersisted) {
        throw new RuntimeException(
            'Both workers were expected to leave a competing metadata row behind, but only one candidate key was found.'
        );
    }

    if ($winner !== 'A') {
        throw new RuntimeException(
            'The independently re-read idempotency key does not resolve to worker A as the earliest-persisted attempt.'
        );
    }
} finally {
    foreach ($processes as $workerProcess) {
        if (is_resource($workerProcess['process'])) {
            proc_terminate($workerProcess['process']);
        }
    }

    foreach (glob($barrierDir . '/*') ?: [] as $barrierFile) {
        unlink($barrierFile);
    }
    rmdir($barrierDir);
}
