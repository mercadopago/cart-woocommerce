<?php

namespace MercadoPago\Woocommerce\Order;

use Exception;
use MercadoPago\Woocommerce\Exceptions\OrderStatusLockException;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use RuntimeException;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Durable resumption of a Pix status update that lost the per-order lock race.
 *
 * The retry is an Action Scheduler action (already bundled with WooCommerce), so it survives the
 * browser being closed and does not depend on another webhook delivery. Only the order id, the
 * channel and a short reference are stored: never payloads, buyer data or credentials.
 */
class PixStatusRetry
{
    public const HOOK         = 'mp_retry_pix_status';
    public const GROUP        = 'mercadopago-pix-status';
    public const MAX_ATTEMPTS = 10;
    private const MAX_PAYMENTS_READ = 10;
    private const GUARD_PREFIX = 'mp_pix_retry_';
    private const SIGNAL_PREFIX = 'mp_pix_retry_signal_';
    private const GUARD_MAX_TTL = 3900;
    private const METRIC_EXECUTED = 'pix_status_retry_executed';

    public const SOURCES = ['polling', 'core', 'webhook', 'ipn', 'sync', 'reconciliation'];

    /** Statuses a retry must not replay: refunds need the notification context that is not stored. */
    private const SKIPPED_STATUSES = ['refunded'];

    private OrderStatus $orderStatus;

    private OrderMetadata $orderMetadata;

    private Requester $requester;

    private Logs $logs;

    private Datadog $datadog;

    public function __construct(
        OrderStatus $orderStatus,
        OrderMetadata $orderMetadata,
        Requester $requester,
        Logs $logs,
        ?Datadog $datadog = null
    ) {
        $this->datadog       = $datadog ?? Datadog::getInstance();
        $this->orderStatus   = $orderStatus;
        $this->orderMetadata = $orderMetadata;
        $this->requester     = $requester;
        $this->logs          = $logs;
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run'], 10, 4);
    }

    /**
     * Persist a retry before answering the caller.
     *
     * @return int Action ID, or 0 when the retry could not be persisted.
     */
    public function enqueue(int $orderId, string $source, string $reference): int
    {
        if ($orderId <= 0 || !in_array($source, self::SOURCES, true) || !function_exists('as_enqueue_async_action')) {
            $this->logs->file->error('pix_status_retry_failed', __CLASS__, ['order_id' => $orderId, 'source' => $source]);
            return 0;
        }

        $args = [$orderId, $source, $this->safeReference($reference), 1];

        // A page polling every 5 s (or a sender redelivering) must not pile up actions: while a retry
        // chain exists for this order (any channel/reference, including rescheduled attempts) it will
        // re-query Mercado Pago itself, so it already covers this trigger. The trigger is still
        // recorded as a signal: a worker that is already running may have read the payments before this
        // one changed, so it checks the signal when it ends and schedules a successor.
        $pendingId = $this->guardedActionId($orderId);
        if ($pendingId > 0) {
            // The worker ends by clearing the guard and only then reading the signal. If the guard is
            // still there after the signal was stored, that read is still to come and will see it. If it
            // is gone, the chain ended around this call and may have missed the signal: do not hand back
            // a dead action, open a new chain instead. A signal the store did not keep would be missed the
            // same way, so it also opens a new chain; the per-order lock serializes the two workers.
            if ($this->setSignal($orderId) && $this->guardedActionId($orderId) > 0) {
                return $pendingId;
            }

            $this->clearSignal($orderId);
        }

        $actionId = (int) as_enqueue_async_action(self::HOOK, $args, self::GROUP);
        if ($actionId > 0) {
            $this->setGuard($orderId, $actionId, self::GUARD_MAX_TTL);
        }

        $this->logs->file->info(
            $actionId > 0 ? 'pix_status_retry_queued' : 'pix_status_retry_failed',
            __CLASS__,
            ['order_id' => $orderId, 'source' => $source, 'action_id' => $actionId]
        );

        return $actionId;
    }

    /**
     * One expiring transient per order records the action of the live retry chain: O(1) lookup, no
     * scan of Action Scheduler tables and no per-order rows that outlive the retry (it expires on its
     * own and is cleared as soon as the chain ends).
     */
    private function guardedActionId(int $orderId): int
    {
        if (!function_exists('get_transient')) {
            return 0;
        }

        $actionId = (int) $this->freshTransient(self::GUARD_PREFIX . $orderId);
        if ($actionId <= 0) {
            return 0;
        }

        // The guard can outlive its action (fatal error, timeout, action deleted): only reuse it while
        // the action can still run.
        $status = $this->actionStatus($actionId);
        if ($status !== null && !in_array($status, ['pending', 'in-progress'], true)) {
            $this->clearGuard($orderId);
            $this->logs->file->warning('pix_status_retry_guard_stale', __CLASS__, ['order_id' => $orderId, 'action_id' => $actionId]);

            return 0;
        }

        return $actionId;
    }

    /**
     * @return string|null Action Scheduler status, null when it cannot be known (then the guard is trusted).
     */
    protected function actionStatus(int $actionId): ?string
    {
        if (!class_exists('ActionScheduler') || !method_exists('ActionScheduler', 'store')) {
            return null;
        }

        try {
            return (string) \ActionScheduler::store()->get_status((string) $actionId);
        } catch (\Throwable $unknownAction) {
            return 'missing';
        }
    }

    /**
     * @return bool true when the store holds the signal after the write.
     */
    private function setSignal(int $orderId): bool
    {
        if (!function_exists('set_transient')) {
            return false;
        }

        set_transient(self::SIGNAL_PREFIX . $orderId, 1, self::GUARD_MAX_TTL);

        // set_transient() returns false both when the store refuses the write and when the same value is already
        // stored, so only a fresh read tells whether the worker will find the signal.
        return (bool) $this->freshTransient(self::SIGNAL_PREFIX . $orderId);
    }

    private function clearSignal(int $orderId): void
    {
        if (function_exists('delete_transient')) {
            delete_transient(self::SIGNAL_PREFIX . $orderId);
        }
    }

    /**
     * @return bool true when a trigger arrived since the signal was last consumed.
     */
    private function consumeSignal(int $orderId): bool
    {
        if (!function_exists('get_transient')) {
            return false;
        }

        $signaled = (bool) $this->freshTransient(self::SIGNAL_PREFIX . $orderId);
        if ($signaled && function_exists('delete_transient')) {
            delete_transient(self::SIGNAL_PREFIX . $orderId);
        }

        return $signaled;
    }

    /**
     * Reads a transient past this request's cached entries. Another request may have written, changed or removed it
     * after this one looked: a stale absence would hide a live guard or a new signal, and a stale value would keep
     * reusing a guard that another request already ended. With a persistent object cache, get_transient() reads the
     * transient group, and drop-ins such as Redis Object Cache answer from a per-request copy: the read is forced to
     * the shared store, and the key is never deleted because it is the coordination record itself. Without one,
     * get_transient() reads the value and its timeout as options, so those cached entries are dropped first.
     *
     * @return mixed
     */
    private function freshTransient(string $key)
    {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache() && function_exists('wp_cache_get')) {
            return wp_cache_get($key, 'transient', true);
        }

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('notoptions', 'options');
            wp_cache_delete('_transient_' . $key, 'options');
            wp_cache_delete('_transient_timeout_' . $key, 'options');
        }

        return get_transient($key);
    }

    /**
     * End of the chain: drop the guard first, then look for a trigger that arrived while this worker was
     * running (it only recorded a signal because the guard still existed) and hand it to a new chain.
     */
    private function endChain(int $orderId, string $source): void
    {
        $this->clearGuard($orderId);

        if ($this->consumeSignal($orderId)) {
            $this->logs->file->info('pix_status_retry_successor', __CLASS__, ['order_id' => $orderId]);

            if ($this->enqueue($orderId, $source, '') <= 0) {
                // The pending trigger was already consumed: do not finish as if it had been handled.
                // run() turns this into a normal reschedule, which re-reads every attempt.
                throw new RuntimeException('Pix status retry successor could not be persisted');
            }
        }
    }

    private function setGuard(int $orderId, int $actionId, int $ttl): void
    {
        if (function_exists('set_transient')) {
            set_transient(self::GUARD_PREFIX . $orderId, $actionId, $ttl);
        }
    }

    private function clearGuard(int $orderId): void
    {
        if (function_exists('delete_transient')) {
            delete_transient(self::GUARD_PREFIX . $orderId);
        }
    }

    /**
     * Action Scheduler callback.
     *
     * @throws RuntimeException so the action is flagged as failed when nothing else can resume the work.
     */
    public function run(int $orderId, string $source, string $reference, int $attempt): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order || $order->get_payment_method() !== PixGateway::ID) {
            $this->clearGuard($orderId);
            $this->recordExecution('skipped', $source, $attempt);
            return;
        }

        // Anything that signals from here on arrived after this run started: consume what came before,
        // the payments are read below.
        $this->consumeSignal($orderId);

        try {
            $payment = $this->fetchPayment($order);
            $status  = (string) ($payment['status'] ?? '');

            if ($status === '' || in_array($status, self::SKIPPED_STATUSES, true)) {
                $this->endChain($orderId, $source);
                $this->recordExecution('skipped', $source, $attempt);
                return;
            }

            $this->orderStatus->processStatus($status, $payment, $order, PixGateway::WEBHOOK_API_NAME);
            $this->endChain($orderId, $source);
            $this->recordExecution('processed', $source, $attempt);
        } catch (\Throwable $exception) {
            // Throwable, not Exception: a TypeError or an Error raised by a third-party hook must not
            // skip the reschedule and leave the guard pointing to an action that already ended.
            try {
                $this->reschedule($orderId, $source, $reference, $attempt);
            } catch (RuntimeException $noNextAttempt) {
                $this->clearGuard($orderId);
                $this->recordExecution('failed', $source, $attempt);
                throw $noNextAttempt;
            }
            $this->recordExecution('rescheduled', $source, $attempt);
        }
    }

    /**
     * Metric `pix_status_retry_executed`: one event every time the retry cron/runner executes an
     * action, so the frequency of contention and its outcome can be measured. Telemetry must never
     * change the outcome of the retry.
     */
    private function recordExecution(string $result, string $source, int $attempt): void
    {
        try {
            $this->datadog->sendEvent(self::METRIC_EXECUTED, $result, null, 'pix', [
                'source'  => $source,
                'attempt' => $attempt,
            ]);
        } catch (\Throwable $telemetryFailure) {
            // intentionally ignored
        }
    }

    /**
     * The next attempt is persisted before this action finishes.
     *
     * @throws RuntimeException when there is no next attempt (exhausted or could not be stored).
     */
    private function reschedule(int $orderId, string $source, string $reference, int $attempt): void
    {
        if ($attempt >= self::MAX_ATTEMPTS || !function_exists('as_schedule_single_action')) {
            $this->logs->file->error('pix_status_retry_failed', __CLASS__, ['order_id' => $orderId, 'attempt' => $attempt]);
            throw new RuntimeException('Pix status retry exhausted');
        }

        $delay    = min(3600, 60 * (2 ** min($attempt - 1, 6)));
        $actionId = (int) as_schedule_single_action(
            time() + $delay,
            self::HOOK,
            [$orderId, $source, $reference, $attempt + 1],
            self::GROUP
        );

        if ($actionId <= 0) {
            $this->logs->file->error('pix_status_retry_failed', __CLASS__, ['order_id' => $orderId, 'attempt' => $attempt]);
            throw new RuntimeException('Pix status retry could not be rescheduled');
        }

        $this->setGuard($orderId, $actionId, min(self::GUARD_MAX_TTL, $delay + 300));

        $this->logs->file->info(
            'pix_status_retry_queued',
            __CLASS__,
            ['order_id' => $orderId, 'attempt' => $attempt + 1, 'action_id' => $actionId]
        );
    }

    /**
     * Re-query Mercado Pago using only payment ids that already belong to the order, so a stored
     * reference can never make the worker apply a payment from another order.
     *
     * The retry chain is shared by every trigger of the order (enqueue() reuses the live chain and
     * drops the new reference), so the stored reference is never trusted to be the one that matters:
     * every attempt is read. An approved attempt wins over a rejected or cancelled one, otherwise the
     * current (latest) attempt is used. OrderMetadata::getPaymentIds() lists the current attempt first
     * and the historical ones after it. An attempt that cannot be read is skipped, so it never hides an
     * approval of another attempt; with no approval, any unreadable attempt makes the run fail and retry.
     *
     * @throws Exception
     */
    private function fetchPayment(WC_Order $order): array
    {
        $orderPaymentIds = array_slice($this->orderMetadata->getPaymentIds($order), 0, self::MAX_PAYMENTS_READ);
        if (empty($orderPaymentIds)) {
            throw new Exception('No payment id for order');
        }

        $headers = ['Authorization: Bearer ' . $this->orderStatus->getAccessTokenForOrder($order)];

        $payments   = [];
        $unreadable = [];
        foreach ($orderPaymentIds as $paymentId) {
            try {
                $payments[] = $this->fetchOwnPayment((string) $paymentId, $headers);
            } catch (Exception $exception) {
                // One attempt that cannot be read (non-200, correlation) must not hide an approval.
                $unreadable[] = (string) $paymentId;
                $this->logs->file->warning(
                    'pix_status_retry_payment_unreadable',
                    __CLASS__,
                    ['order_id' => $order->get_id(), 'payment_id' => (string) $paymentId]
                );
            }
        }

        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') === 'approved') {
                return $payment;
            }
        }

        // Without an approval the decision depends on the unreadable attempts (the current one may be
        // among them): never apply an older attempt's status in their place; try again later.
        if (!empty($unreadable) || empty($payments)) {
            throw new Exception('Payment attempts could not be read');
        }

        // The current attempt comes first; end() would pick the oldest historical one.
        return reset($payments);
    }

    /**
     * @throws Exception
     */
    private function fetchOwnPayment(string $paymentId, array $headers): array
    {
        $response = $this->requester->get('/v1/payments/' . rawurlencode($paymentId), $headers);

        if ($response->getStatus() !== 200) {
            throw new Exception('Failed to get payment status');
        }

        $payment = (array) $response->getData();
        if ((string) ($payment['id'] ?? '') !== $paymentId) {
            throw new Exception('Payment correlation failed');
        }

        return $payment;
    }

    private function safeReference(string $reference): string
    {
        return substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $reference) ?? '', 0, 64);
    }
}
