<?php

namespace MercadoPago\Woocommerce\Notification;

use Exception;
use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Exceptions\OrderStatusLockException;
use MercadoPago\Woocommerce\Order\FreshOrderReader;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Order\PixOrderStatusLock;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationHandler;
use MercadoPago\Woocommerce\SmartPix\Composition\SmartPixCoreNotificationHandlerFactory;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationIdentifiers;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixNotificationReceiver;
use Throwable;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class CoreNotification extends AbstractNotification
{
    /** Route-wide budget, aligned with the existing 16 KiB Smart Pix limit. */
    private const MAX_NOTIFICATION_BODY_BYTES = 16384;

    /**
     * One order meta row per (payment, refund) already seen by the accumulation. Value: "pending:<total before>:<amount>"
     * until the amount is part of the payment's [Refund] total, then "done:<amount>". The state lives in the marker so
     * an interrupted update can be finished later by adding the amount, never by overwriting the total.
     */
    private const REFUND_APPLIED_META_PREFIX = '_mp_refund_applied_';

    /** Seconds a notification waits for another worker that is accumulating a refund of the same order. */
    private const REFUND_LOCK_WAIT_SECONDS = 3;

    /** Tolerance when comparing sums of money kept as floats. */
    private const REFUND_EPSILON = 0.000001;

    private ?PixOrderStatusLock $refundLock = null;

    private ?FreshOrderReader $freshOrderReader = null;

    private NotificationsClient $notificationsClient;

    private ?SmartPixCoreNotificationHandler $smartPixHandler;

    private const REFUND_ORIGIN_PANEL = 'painel_woocommerce';
    private const REFUND_METRIC_SUCCESS_MP = 'mp_refund_success';
    private const REFUND_METRIC_ERROR_MP = 'mp_refund_error';
    private const REFUND_ORIGIN_MP = 'origin_mercadopago';
    private const CHECKOUT_TYPE_META_KEY = 'checkout_type';

    public function __construct(
        MercadoPagoGatewayInterface $gateway,
        Logs $logs,
        OrderStatus $orderStatus,
        Seller $seller,
        Store $store,
        NotificationsClient $notificationsClient,
        ?SmartPixCoreNotificationHandler $smartPixHandler = null
    ) {
        parent::__construct($gateway, $logs, $orderStatus, $seller, $store);

        $this->notificationsClient = $notificationsClient;
        $this->smartPixHandler     = $smartPixHandler;
    }

    /**
     * Get Notification Id
     *
     * @return mixed
     */
    public function getNotificationId()
    {
        $raw = $this->getInput();

        return strlen($raw) > self::MAX_NOTIFICATION_BODY_BYTES ? null : $this->notificationIdFrom($raw);
    }

    /**
     * @return mixed
     */
    private function notificationIdFrom(string $raw)
    {
        $body = json_decode($raw);

        // For both Core and Bifrost. Core sends a complete object, but Bifrost sends only a string with the notification id
        if (is_object($body)) {
            return $body->notification_id;
        }

        return $body;
    }

    /**
     * Read at most the route budget plus one overflow byte, including calls without a limit.
     */
    protected function getInput(?int $maxBytes = null): string
    {
        $budget = self::MAX_NOTIFICATION_BODY_BYTES + 1;
        $maxBytes = min($maxBytes ?? $budget, $budget);
        if ($maxBytes < 1) {
            return '';
        }

        $stream = $this->openInputStream();
        if ($stream === false) {
            return '';
        }
        try {
            $raw = stream_get_contents($stream, $maxBytes);
        } finally {
            fclose($stream);
        }

        return $raw === false ? '' : $raw;
    }

    /** @return resource|false */
    protected function openInputStream()
    {
        return fopen('php://input', 'rb');
    }

    /**
     * Validate if ID is in the format P-12345 or M-12345 (or any number of digits)
     *
     * @return string
     */
    public function validateNotificationId($notification_id)
    {
        return preg_match('/^[PM]-\\d+$/', $notification_id) === 1;
    }

    private function getRequestContext(): CoreRequestContext
    {
        $platformId   = MP_PLATFORM_ID;
        $productId    = Device::getDeviceProductId();
        $integratorId = $this->store->getIntegratorId();
        $accessToken  = $this->seller->getCredentialsAccessToken();

        return new CoreRequestContext($accessToken, $platformId, $productId, $integratorId);
    }

    /**
     * Handle Notification Request
     *
     * @param $data
     *
     * @return void
     */
    public function handleReceivedNotification($data)
    {
        parent::handleReceivedNotification($data);

        $raw = $this->getInput(self::MAX_NOTIFICATION_BODY_BYTES + 1);
        if (strlen($raw) > self::MAX_NOTIFICATION_BODY_BYTES) {
            $this->rejectOversizedNotification();
            return;
        }

        $notification_id = $this->notificationIdFrom($raw);

        if (SmartPixCoreNotificationIdentifiers::isNotificationId($notification_id)) {
            $this->handleSmartPixNotification($notification_id);
            return;
        }

        if (!$this->validateNotificationId($notification_id)) {
            $message = 'Invalid notification id';
            $this->logs->file->error($message, __CLASS__, $data);
            $this->setResponse(400, $message);
            return;
        }

        try {
            $notification = $this->notificationsClient
                ->find($notification_id, $this->getRequestContext());

            $this->handleSuccessfulRequest($notification->toArray());
        } catch (Exception $e) {
            $this->logs->file->error($e->getMessage(), __CLASS__, $data);
            $this->setResponse(500, $e->getMessage());
        }
    }

    /**
     * Smart Pix Core P&P notifications are applied only by the Smart Pix pipeline. Nothing
     * from the request or the fetched notification is logged, and responses are fixed.
     */
    private function handleSmartPixNotification(string $notificationId): void
    {
        try {
            $handler = $this->smartPixHandler ?? SmartPixCoreNotificationHandlerFactory::fromPlugin();
            $result  = $handler->handle($notificationId, $this->getRequestContext());
        } catch (Throwable $e) {
            $this->logs->file->error('Smart Pix notification could not be handled', __CLASS__);
            $result = ['status' => 500, 'message' => SmartPixNotificationReceiver::MESSAGE_FAILED];
        }

        $this->setResponse($result['status'], $result['message']);
    }

    /** Refuse before decoding or classifying an untrusted body, independently of Smart Pix. */
    private function rejectOversizedNotification(): void
    {
        try {
            Datadog::getInstance()->sendEvent(
                'core_notification_rejected',
                'body_too_large',
                null,
                null,
                ['team' => 'big', 'reason' => 'body_too_large']
            );
        } catch (Throwable $e) {
            // A telemetry failure must never prevent the size rejection or expose request material.
        }

        $this->setResponse(413, 'Notification body too large.');
    }

    public function handleSuccessfulRequestInternal($data, $order): void
    {
        $oldOrderStatus = $order->get_status();

        if ($this->isRefundNotification($data)) {
            $this->handleRefundNotification($order, $oldOrderStatus, $data);
            return;
        }

        $processedStatus = $this->getProcessedStatus($order, $data);
        $this->logStatusChange($oldOrderStatus, $processedStatus);
        $this->processStatus($processedStatus, $order, $data);
    }

    /**
     * Check if notification is for refund
     *
     * @param mixed $data
     *
     * @return bool
     */
    private function isRefundNotification($data): bool
    {
        return !empty($data['refunds_notifying']) && is_array($data['refunds_notifying']);
    }

    /**
     * Handle refund notification
     *
     * @param WC_Order $order
     * @param string $oldOrderStatus
     * @param mixed $data
     *
     * @return void
     */
    private function handleRefundNotification(WC_Order $order, string $oldOrderStatus, $data): void
    {
        $queuedPayments = [];
        foreach ($data['refunds_notifying'] as $refund) {
            $data['current_refund'] = [];
            $refundId = $refund['id'] ?? null;

            if (!$this->isValidRefund($refund, $refundId, $data, $order)) {
                continue;
            }

            // $refundId comes straight from the webhook payload; strip CR/LF/TAB before it is
            // concatenated into any log line so a malformed id cannot forge log entries or trip
            // SIEM parsers (log injection). Used only for logging — lookups keep the raw value.
            $safeRefundId = preg_replace('/[\r\n\t]/', '', (string) $refundId);

            // Skip the WooCommerce refund object, but repair a possible failed panel
            // metadata write after the panel has finished. Queue once per payment.
            if ($this->orderStatus->isRefundIdApplied($order, (string) $refundId)) {
                $paymentId = null;
                foreach ($data['payments_details'] as $payment) {
                    if (isset($payment['refunds'][$refundId])) {
                        $paymentId = (string) $payment['id'];
                        break;
                    }
                }
                if ($paymentId === null) {
                    $ids = array_filter(array_map('trim', explode(',', (string) $order->get_meta(PaymentMetadata::PAYMENT_IDS_META_KEY))));
                    if (count($ids) === 1) {
                        $paymentId = (string) reset($ids);
                    }
                }
                if ($paymentId !== null && !isset($queuedPayments[$paymentId])) {
                    $this->orderStatus->queuePaymentRefundReconciliation($order, $paymentId, (string) $refundId);
                    $queuedPayments[$paymentId] = true;
                }
                $this->logs->file->info('Refund already applied, skipping notification refund: ' . $safeRefundId, __CLASS__);
                continue;
            }

            foreach ($data['payments_details'] as $payment) {
                if (isset($payment['refunds'][$refundId])) {
                    $currentRefund = $payment['refunds'][$refundId];
                    break;
                }
            }

            $data['current_refund'] = array_merge($currentRefund, $refund);

            if ($this->shouldProcessRefund($currentRefund)) {
                $processedStatus = $this->getProcessedStatus($order, $data);
                $this->logStatusChange($oldOrderStatus, $processedStatus);
                $refundApplied = $this->processStatus($processedStatus, $order, $data);

                // Only report success when the refund was actually applied. A wc_create_refund
                // failure inside refundedFlow already emitted mp_refund_error and returns false.
                if ($refundApplied) {
                    $this->sendRefundSuccessMetric($order);
                }
            } else {
                if (!empty($data['payments_details'])) {
                    $this->updatePaymentDetails($order, $data);
                    $order->save();
                }
            }
        }
    }

    /**
     * Validate refund data
     *
     * @param array $refund
     * @param string|null $refundId
     * @param mixed $data
     * @param WC_Order $order
     *
     * @return bool
     */
    private function isValidRefund($refund, $refundId, $data, WC_Order $order): bool
    {
        if (!$refundId) {
            $this->logs->file->error('Refund ID not found in notification', __CLASS__, $data);
            $this->sendRefundErrorMetric('validation_failed', 'Refund ID not found in notification', $order);
            return false;
        }

        if (!isset($refund['amount']) || empty($refund['amount']) || $refund['amount'] <= 0.00) {
            $this->logs->file->error('Invalid refund amount: must be greater than 0', __CLASS__, $refund);
            $this->sendRefundErrorMetric('validation_failed', 'Invalid refund amount: must be greater than 0', $order);
            return false;
        }

        if (!$this->isValidPaymentsDetailsStructure($data)) {
            $this->logs->file->error('Invalid payments_details structure in notification', __CLASS__, $data);
            $this->sendRefundErrorMetric('validation_failed', 'Invalid payments_details structure in notification', $order);
            return false;
        }

        return true;
    }

    /**
     * Check if refund should be processed
     *
     * @param array|null $currentRefund
     *
     * @return bool
     */
    private function shouldProcessRefund($currentRefund): bool
    {
        return $currentRefund &&
               (!isset($currentRefund['metadata']['origin']) ||
                $currentRefund['metadata']['origin'] !== self::REFUND_ORIGIN_PANEL);
    }

    /**
     * Validate payments_details structure
     *
     * @param array $data
     *
     * @return bool
     */
    private function isValidPaymentsDetailsStructure(array $data): bool
    {
        return isset($data['payments_details']) &&
               is_array($data['payments_details']) &&
               !empty($data['payments_details']) &&
               isset($data['payments_details'][0]['refunds']) &&
               is_array($data['payments_details'][0]['refunds']);
    }

    /**
     * Process status
     *
     * @param WC_Order $order
     * @param mixed $data
     *
     * @return string
     */
    public function getProcessedStatus(WC_Order $order, $data): string
    {
        $status = $data['status'];

        if (!empty($data['payer']['email'])) {
            $order->update_meta_data('Buyer email', $data['payer']['email']);
        }

        if (!empty($data['payments_details'])) {
            $this->updatePaymentDetails($order, $data);
        }

        $order->save();

        return $status;
    }

    /**
     * Update payment details metadata for order
     *
     * @param WC_Order $order
     * @param array $data
     *
     * @return void
     */
    public function updatePaymentDetails(WC_Order $order, array $data): void
    {
        $payment_ids = [];

        foreach ($data['payments_details'] as $payment) {
            $payment_ids[] = $payment['id'];

            $paymentData = PaymentMetadata::extractPaymentDataFromMeta(
                $order->get_meta(PaymentMetadata::getPaymentMetaKey($payment['id']))
            );

            // MP-origin refund: the notification carries the current refund's amount, which is
            // incremented into the per-payment total here. Panel-origin refunds do NOT reach this
            // path — RefundHandler records their per-payment amount at refund time (PSW-4412), and
            // their notification is recognised as already-applied and skipped in
            // handleRefundNotification.
            if (isset($data['current_refund']) && isset($payment['refunds'][$data['current_refund']['id']])) {
                // Stores the payment's metadata itself, under the lock: writing it here, after the lock
                // is gone, would let this request's older snapshot overwrite a newer total.
                $this->accumulateMpOriginRefund($order, $payment, $data['current_refund']);
            } else {
                $order->update_meta_data(
                    PaymentMetadata::getPaymentMetaKey($payment['id']),
                    PaymentMetadata::formatPaymentMetadata($payment, $paymentData->refund ?? 0)
                );
            }

            if (Strings::contains($payment['payment_type_id'], 'card')) {
                $paymentMetaPrefix = "Mercado Pago - {$payment['id']} -";
                $order->update_meta_data("$paymentMetaPrefix installments", $payment['payment_method_info']['installments']);
                $order->update_meta_data("$paymentMetaPrefix installment_amount", $payment['payment_method_info']['installment_amount']);
                $order->update_meta_data("$paymentMetaPrefix transaction_amount", $payment['total_amount']);
                $order->update_meta_data("$paymentMetaPrefix total_paid_amount", $payment['paid_amount']);
                $order->update_meta_data("$paymentMetaPrefix card_last_four_digits", $payment['payment_method_info']['last_four_digits']);
            }
        }

        if (empty($data['refunds_notifying'])) {
            $order->update_meta_data(PaymentMetadata::PAYMENT_IDS_META_KEY, PaymentMetadata::joinPaymentIds($payment_ids));
        }
    }

    /**
     * Adds an MP-origin refund to the payment's [Refund] total exactly once, whatever the concurrency or
     * redelivery.
     *
     * The read, the sum and the record of the refund run under a per-order named lock, over a fresh copy of the
     * order (another worker may have changed it since this request loaded it). Marker and total are two rows,
     * so a marker carries its own state and the write order makes a half-done update recoverable:
     *   1. "pending:<total before>:<amount>" marker is persisted and read back;
     *   2. the amount is added to the CURRENT persisted total (never replaced by an absolute value);
     *   3. the marker becomes "done:<amount>".
     * Every run, before anything else, finishes the pending markers of the payment in order: a marker whose
     * amount is not yet in the total gets it added; one whose total write did land (total >= before + amount) is
     * only marked done. So a refund processed while an older one is still pending cannot lose it, and a
     * redelivery never counts a refund twice. The only ambiguous case (a panel refund changed the total between
     * a lost step 2/3) is documented in traps.md.
     *
     * The caller's order also gets the payment's metadata (final total included) and is persisted before the lock
     * is released. Left to the caller's later save(), a request that accumulated first would write its older total
     * over a newer one, and since both markers are already done no redelivery could repair it.
     *
     * @param array $payment one entry of payments_details
     * @param array $currentRefund notification refund (id, amount)
     *
     * @return float the payment's [Refund] total that was stored
     * @throws OrderStatusLockException when the lock cannot be obtained or kept, or the order cannot be read
     *                                  or written: the caller answers 503 and the sender redelivers.
     */
    private function accumulateMpOriginRefund(WC_Order $order, array $payment, array $currentRefund): float
    {
        $orderId = (int) $order->get_id();
        $locks   = null;
        $lock    = null;

        if ($orderId > 0 && (bool) apply_filters('woocommerce_mercadopago_pix_status_lock_enabled', true)) {
            $locks = $this->refundLock();
            $lock  = $locks->acquire($orderId, self::REFUND_LOCK_WAIT_SECONDS);
        }

        try {
            $total = $this->accumulateRefundOnFreshOrder($order, $payment, $currentRefund, $locks, $lock);
            $this->storePaymentTotal($order, $payment, $total, $locks, $lock);

            return $total;
        } finally {
            if ($locks !== null && !$locks->release($lock)) {
                $this->logs->file->warning('refund_accumulation_lock_release_unconfirmed', __CLASS__, ['order_id' => $orderId]);
            }
        }
    }

    /**
     * Writes the payment's metadata with its final total on the caller's order and persists it while the lock is
     * still held, so the caller's later save() has nothing stale left to write for this payment.
     *
     * @param PixOrderStatusLock|null $locks
     * @param array{name: string, connection_id: int}|null $lock
     */
    private function storePaymentTotal(WC_Order $order, array $payment, float $total, ?PixOrderStatusLock $locks, ?array $lock): void
    {
        $order->update_meta_data(
            PaymentMetadata::getPaymentMetaKey((string) $payment['id']),
            PaymentMetadata::formatPaymentMetadata($payment, $total)
        );

        if ((int) $order->get_id() > 0) {
            $order->save_meta_data();
            $this->assertRefundLockOwned($locks, $lock);
        }
    }

    /**
     * @param PixOrderStatusLock|null $locks
     * @param array{name: string, connection_id: int}|null $lock
     */
    private function accumulateRefundOnFreshOrder(
        WC_Order $order,
        array $payment,
        array $currentRefund,
        ?PixOrderStatusLock $locks,
        ?array $lock
    ): float {
        $orderId      = (int) $order->get_id();
        $paymentId    = (string) $payment['id'];
        $refundId     = (string) $currentRefund['id'];
        $amount       = (float) $currentRefund['amount'];
        $logRefund    = preg_replace('/[\r\n\t]/', '', $refundId);
        $logPay       = preg_replace('/[\r\n\t]/', '', $paymentId);
        $metaKey      = PaymentMetadata::getPaymentMetaKey($paymentId);
        $markerPrefix = self::REFUND_APPLIED_META_PREFIX . $this->metaKeyPart($paymentId) . '_';
        $markerKey    = $markerPrefix . $this->metaKeyPart($refundId);

        $fresh = $orderId > 0 ? $this->loadFreshOrder($orderId) : $order;
        if ($fresh === null) {
            $this->logs->file->error('refund_accumulation_order_unreadable', __CLASS__, ['order_id' => $orderId]);
            throw new OrderStatusLockException(OrderStatusLockException::REASON_UNAVAILABLE);
        }

        $total = (float) (PaymentMetadata::extractPaymentDataFromMeta((string) $fresh->get_meta($metaKey))->refund ?? 0);

        // Older pending refunds go first, so the "total before" recorded for this one already includes them.
        $total = $this->finishPendingRefunds($order, $fresh, $payment, $markerPrefix, $total, $locks, $lock);

        if ((string) $fresh->get_meta($markerKey) === '') {
            $pending = 'pending:' . $total . ':' . $amount;
            $fresh->update_meta_data($markerKey, $pending);
            $fresh->save_meta_data();
            $this->assertRefundLockOwned($locks, $lock);
            $this->assertRefundMarkerPersisted($orderId, $markerKey, $pending);
            $this->rememberRefund($order, $markerKey, $pending);

            $total = $this->finishPendingRefunds($order, $fresh, $payment, $markerPrefix, $total, $locks, $lock);
        } else {
            $this->logs->file->info(
                sprintf('Refund %s already seen on payment %s; [Refund] is %s', $logRefund, $logPay, $total),
                __CLASS__
            );
        }

        return $total;
    }

    /**
     * Applies (or just finalizes) every pending refund marker of the payment, in order.
     *
     * @param PixOrderStatusLock|null $locks
     * @param array{name: string, connection_id: int}|null $lock
     *
     * @return float the payment's [Refund] total after the pending markers
     */
    private function finishPendingRefunds(
        WC_Order $order,
        WC_Order $fresh,
        array $payment,
        string $markerPrefix,
        float $total,
        ?PixOrderStatusLock $locks,
        ?array $lock
    ): float {
        $metaKey = PaymentMetadata::getPaymentMetaKey((string) $payment['id']);
        $logPay  = preg_replace('/[\r\n\t]/', '', (string) $payment['id']);

        foreach ($this->pendingRefundMarkers($fresh, $markerPrefix) as $key => $entry) {
            if ($total + self::REFUND_EPSILON < $entry['before'] + $entry['amount']) {
                $total += $entry['amount'];
                $this->logs->file->info(
                    sprintf('Accumulated MP-origin refund (%s) on payment %s; [Refund] -> %s', $entry['amount'], $logPay, $total),
                    __CLASS__
                );

                $expected = PaymentMetadata::formatPaymentMetadata($payment, $total);
                $fresh->update_meta_data($metaKey, $expected);
                $fresh->save_meta_data();
                $this->assertRefundLockOwned($locks, $lock);
                $this->assertPaymentTotalPersisted((int) $order->get_id(), $metaKey, $expected);
            }

            $done = 'done:' . $entry['amount'];
            $fresh->update_meta_data($key, $done);
            $fresh->save_meta_data();
            $this->assertRefundLockOwned($locks, $lock);
            $this->rememberRefund($order, $key, $done);
        }

        return $total;
    }

    /**
     * Pending refund markers of a payment, oldest first.
     *
     * @return array<string, array{before: float, amount: float}> meta key => state
     */
    private function pendingRefundMarkers(WC_Order $order, string $markerPrefix): array
    {
        $pending = [];

        foreach ($order->get_meta_data() as $meta) {
            $data  = $meta->get_data();
            $key   = (string) ($data['key'] ?? '');
            $value = (string) ($data['value'] ?? '');

            if (strpos($key, $markerPrefix) !== 0 || strpos($value, 'pending:') !== 0) {
                continue;
            }

            $parts = explode(':', $value);
            if (count($parts) !== 3 || !is_numeric($parts[1]) || !is_numeric($parts[2])) {
                $this->logs->file->error('refund_marker_unreadable', __CLASS__, ['meta_key' => preg_replace('/[^A-Za-z0-9_-]/', '', $key)]);
                continue;
            }

            $pending[$key] = ['before' => (float) $parts[1], 'amount' => (float) $parts[2]];
        }

        return $pending;
    }

    /**
     * Keeps the caller's in-memory order in step, so its later save() never writes a stale marker.
     */
    private function rememberRefund(WC_Order $order, string $markerKey, string $value): void
    {
        $order->update_meta_data($markerKey, $value);
    }

    /**
     * @param PixOrderStatusLock|null $locks
     * @param array{name: string, connection_id: int}|null $lock
     */
    private function assertRefundLockOwned(?PixOrderStatusLock $locks, ?array $lock): void
    {
        if ($locks !== null && $lock !== null) {
            $locks->assertOwned($lock);
        }
    }

    /**
     * The marker decides the idempotency of every redelivery: do not go on to the total unless it is readable.
     */
    private function assertRefundMarkerPersisted(int $orderId, string $markerKey, string $expected): void
    {
        if ($orderId <= 0) {
            return;
        }

        $check = $this->loadFreshOrder($orderId);
        if ($check === null || (string) $check->get_meta($markerKey) !== $expected) {
            $this->logs->file->error('refund_accumulation_marker_not_persisted', __CLASS__, ['order_id' => $orderId]);
            throw new OrderStatusLockException(OrderStatusLockException::REASON_UNAVAILABLE);
        }
    }

    /**
     * The payment's [Refund] total must be readable before its marker becomes done: a save that the data store did not
     * apply would otherwise leave a done marker over the old total, and no redelivery could repair it.
     */
    private function assertPaymentTotalPersisted(int $orderId, string $metaKey, string $expected): void
    {
        if ($orderId <= 0) {
            return;
        }

        $check = $this->loadFreshOrder($orderId);
        if ($check === null || (string) $check->get_meta($metaKey) !== $expected) {
            $this->logs->file->error('refund_accumulation_total_not_persisted', __CLASS__, ['order_id' => $orderId]);
            throw new OrderStatusLockException(OrderStatusLockException::REASON_UNAVAILABLE);
        }
    }

    /**
     * Ids come from the webhook payload: only [A-Za-z0-9-] reach a meta key, anything else is hashed.
     */
    private function metaKeyPart(string $value): string
    {
        return preg_match('/^[A-Za-z0-9-]{1,40}$/', $value) === 1 ? $value : substr(sha1($value), 0, 40);
    }

    protected function refundLock(): PixOrderStatusLock
    {
        return $this->refundLock ??= new PixOrderStatusLock(null, PixOrderStatusLock::REFUND_NAME_PREFIX);
    }

    /**
     * A separate copy of the order read from the database: the caller's object keeps its unsaved changes.
     */
    protected function loadFreshOrder(int $orderId): ?WC_Order
    {
        $copy = wc_get_order($orderId);
        if (!$copy instanceof WC_Order) {
            return null;
        }

        ($this->freshOrderReader ??= new FreshOrderReader())->refresh($copy);

        return $copy;
    }

    /**
    * Log status change
    *
    * @param string $oldStatus
    * @param string $newStatus
    *
    * @return void
    */
    private function logStatusChange(string $oldStatus, string $newStatus): void
    {
        $this->logs->file->info(
            sprintf(
                'Changing order status from %s to %s',
                $oldStatus,
                OrderStatus::mapMpStatusToWoocommerceStatus(str_replace('_', '', $newStatus))
            ),
            __CLASS__
        );
    }

    /**
     * Get the checkout_type from the order metadata so refund metrics can be
     * segmented by product bucket (super_token, credit_card, pix…) in Datadog.
     * Returns null for legacy orders that predate the checkout_type metadata.
     *
     * @param WC_Order|null $order
     *
     * @return string|null
     */
    private function getCheckoutType(?WC_Order $order): ?string
    {
        if (!$order) {
            return null;
        }

        $checkoutType = $order->get_meta(self::CHECKOUT_TYPE_META_KEY);

        return !empty($checkoutType) ? (string) $checkoutType : null;
    }

    /**
     * Send refund success metric to Datadog
     *
     * @param WC_Order $order
     */
    private function sendRefundSuccessMetric(WC_Order $order): void
    {
        Datadog::getInstance()->sendEvent(self::REFUND_METRIC_SUCCESS_MP, 'refund_success', self::REFUND_ORIGIN_MP, $this->getCheckoutType($order));
    }

    /**
     * Send refund error metric to Datadog
     *
     * @param string $errorCode
     * @param string $errorMessage
     * @param WC_Order|null $order
     */
    private function sendRefundErrorMetric(string $errorCode, string $errorMessage, ?WC_Order $order = null): void
    {
        Datadog::getInstance()->sendEvent(self::REFUND_METRIC_ERROR_MP, $errorCode, $errorMessage, $this->getCheckoutType($order));
    }
}
