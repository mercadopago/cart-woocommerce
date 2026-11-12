<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce;

use Closure;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixBuyerError;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricPayload;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTerminalObservation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Buyer-triggered status read for the Smart Pix pending page. It never creates, cancels or
 * dispatches provider refunds: it reads the linked Order at most once per window and lets
 * the applicator reconcile confirmed provider facts through native WooCommerce methods.
 */
final class SmartPixStatusEndpoint
{
    public const ACTION = 'mp_smart_pix_order_status';

    public const NONCE_ACTION = 'mp_smart_pix_order_status_nonce';

    public const RATE_LIMIT_SECONDS = 4;

    /** The pending page observes an attempt for 15 minutes from its start, including reloads. */
    public const POLLING_WINDOW_SECONDS = 900;

    /** After the window plus this grace, polls return only the persisted state without an Orders GET. */
    public const POLLING_GRACE_SECONDS = 60;

    /**
     * Per-order Orders query windows by attempt age, one second below the page's 5 s/15 s/30 s
     * backoff: transients expire on whole seconds, so a window equal to the interval could skip
     * a legitimate poll.
     */
    private const QUERY_WINDOW_SECONDS_BY_AGE = [60 => self::RATE_LIMIT_SECONDS, 300 => 14];

    private const LATE_QUERY_WINDOW_SECONDS = 29;

    public const STATE_PENDING = 'pending';

    public const STATE_APPROVED = 'approved';

    public const STATE_REJECTED = 'rejected';

    public const STATE_CANCELLED = 'cancelled';

    private const RATE_LIMIT_TRANSIENT_PREFIX = 'mp_smart_pix_status_';

    private const MAX_ORDER_KEY_LENGTH = 128;

    private const PUBLIC_STATE_BY_ORDER_STATUS = [
        'pending'    => self::STATE_PENDING,
        'on-hold'    => self::STATE_PENDING,
        'processing' => self::STATE_APPROVED,
        'completed'  => self::STATE_APPROVED,
        'failed'     => self::STATE_REJECTED,
        'cancelled'  => self::STATE_CANCELLED,
        'refunded'   => self::STATE_CANCELLED,
    ];

    private const QUERYABLE_ORDER_STATUSES = ['pending', 'on-hold'];

    private const TERMINAL_RESPONSE_STATES = ['terminal_success', 'terminal_failure'];

    public const PAYMENT_STATUS_METRIC = SmartPixMetricPayload::STATUS_OBSERVED;
    public const POLLING_EXPIRED_METRIC = SmartPixMetricPayload::POLLING_EXPIRED;
    public const QUERY_FAILED_METRIC = SmartPixMetricPayload::QUERY_FAILED;

    public const SERVER_ERROR_METRIC = 'smart_pix_server_error';

    private const STATUS_POLL_OPERATION = 'status_poll';

    private const POLLING_EXPIRED_TRANSIENT_PREFIX = 'mp_smart_pix_expired_';

    public const POLLING_EXPIRED_REPORT_TTL_SECONDS = 86400;

    private SmartPixOrderQuery $query;

    private SmartPixStateApplicator $applicator;

    private OrderMetadata $metadata;

    /** @var Closure(string): void */
    private Closure $reportRejection;

    /** @var Closure(string, string, string, array): void */
    private Closure $reportMetric;

    private SmartPixStatusLock $lock;

    private SmartPixStatusLock $terminalLock;

    private SmartPixTerminalObservation $terminalObservation;

    private ?WC_Order $metricOrder = null;

    /**
     * @param Closure(string): void $reportRejection Receives only the server-side rejection reason.
     * @param Closure(string, string, string, array): void $reportMetric Receives name, value and a fixed message.
     */
    public function __construct(
        SmartPixOrderQuery $query,
        SmartPixStateApplicator $applicator,
        OrderMetadata $metadata,
        Closure $reportRejection,
        Closure $reportMetric,
        SmartPixStatusLock $lock,
        SmartPixStatusLock $terminalLock
    ) {
        $this->query           = $query;
        $this->applicator      = $applicator;
        $this->metadata        = $metadata;
        $this->reportRejection = $reportRejection;
        $this->reportMetric    = $reportMetric;
        $this->lock            = $lock;
        $this->terminalLock    = $terminalLock;

        $this->terminalObservation = new SmartPixTerminalObservation(
            $metadata,
            $terminalLock,
            function (string $state, string $duration): void {
                $this->metric($state, $duration);
            }
        );
    }

    public function handle(): void
    {
        $request = Form::sanitizedPostData();
        $result  = $this->resolve(is_array($request) ? $request : []);

        if ($result['rejection'] !== null) {
            $this->report($result['rejection']);
            wp_send_json_error(['message' => 'Forbidden'], 403);
        } else {
            $data = ['state' => $result['state'], 'message' => 'Smart Pix order status'];
            if (isset($result[SmartPixBuyerError::PAYMENT_DETAILS_KEY])) {
                $data[SmartPixBuyerError::PAYMENT_DETAILS_KEY] = $result[SmartPixBuyerError::PAYMENT_DETAILS_KEY];
            }
            wp_send_json_success($data);
        }
    }

    /**
     * @param array<mixed> $request POST credentials, expiry and bounded browser polling diagnostics.
     * @return array{rejection: string|null, state: string|null, smart_pix_error?: string}
     */
    public function resolve(array $request): array
    {
        $this->metricOrder = null;
        $nonce = $request['nonce'] ?? null;
        if (!is_string($nonce) || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return $this->rejected('invalid_nonce');
        }

        $order = $this->authorizedOrder($request['order_id'] ?? null, $request['order_key'] ?? null);
        if (is_string($order)) {
            return $this->rejected($order);
        }

        // A presentation receipt is authenticated like a poll, but never queries Orders,
        // applies payment effects or re-emits the existing financial terminal observation.
        if (array_key_exists('thankyou_result_time_ms', $request)) {
            $state = self::publicStateFor($order);
            $milliseconds = $request['thankyou_result_time_ms'];
            $maximum = (self::POLLING_WINDOW_SECONDS + self::POLLING_GRACE_SECONDS) * 1000;
            if (
                is_string($milliseconds) && strlen($milliseconds) <= 7 && ctype_digit($milliseconds)
                && (int) $milliseconds <= $maximum
                && in_array($state, [self::STATE_APPROVED, self::STATE_REJECTED], true)
            ) {
                $this->reportThankyouResultTimeOnce($order, $state, (int) $milliseconds);
            }
            return $this->accepted($state);
        }

        $reference = $this->metadata->getPpOrderId($order);
        if (is_string($reference) && $reference !== '') {
            SmartPixCheckoutRecovery::acknowledge($order);
        }
        $this->metricOrder = $order;
        // Authenticated diagnostic only: never perform another Orders read or native effect.
        if (array_key_exists('polling_failure', $request)) {
            $reason = $request['polling_failure'];
            $message = $request['polling_failure_message'] ?? '';
            if (
                is_string($reason) && in_array($reason, ['network', 'timeout', 'http_error', 'invalid_json', 'invalid_response'], true)
                && is_string($message) && strlen($message) <= 1024
            ) {
                $transient = 'mp_smart_pix_poll_error_' . $order->get_id() . '_' . $reason;
                if (get_transient($transient) === false) {
                    set_transient($transient, 1, self::RATE_LIMIT_SECONDS);
                    $this->serverError($reason, SmartPixDiagnostic::message(
                        $message !== '' ? $message : $reason,
                        [$nonce, (string) ($request['order_key'] ?? '')]
                    ), 'status_poll_client');
                }
            }
            return $this->accepted(self::publicStateFor($order));
        }
        // The original request may still be saving its reference. Never POST again or GET an invented ID.
        if ($this->metadata->getPpOrderId($order) === '' && SmartPixCreationStore::hasStarted($order)) {
            return $this->accepted(self::publicStateFor($order));
        }
        $state = self::publicStateFor($order);
        $this->terminalObservation->record($order);
        if (($request['polling_expired'] ?? null) === '1') {
            $this->reportPollingExpired($order, $state);
            return $this->accepted($state);
        }

        if (!in_array($order->get_status(), self::QUERYABLE_ORDER_STATUSES, true)) {
            return $this->accepted($state);
        }

        $age = self::attemptAgeSeconds($this->metadata, $order);
        if ($age !== null && $age > self::POLLING_WINDOW_SECONDS + self::POLLING_GRACE_SECONDS) {
            // The page stopped observing: notifications and WP-Cron own convergence from here.
            return $this->accepted($state);
        }

        $lease = $this->lock->acquire($order->get_id());
        if ($lease === null) {
            return $this->accepted($state);
        }

        try {
            if (!$this->acquireQueryWindow($order, $age)) {
                return $this->accepted($state);
            }

            return $this->accepted($this->queryAndApply($order, $state));
        } catch (\Throwable $error) {
            $this->serverError('exception', SmartPixDiagnostic::exception($error));
            return $this->accepted($state);
        } finally {
            $this->lock->release($lease);
        }
    }

    private function queryAndApply(WC_Order $order, string $state): string
    {
        $response = $this->query->query($order, 'server');
        $errorCategory = $response['error']['category'] ?? null;
        if (
            !in_array($response['state'] ?? null, self::TERMINAL_RESPONSE_STATES, true)
            && is_string($errorCategory) && preg_match('/\A[a-z0-9_]{1,64}\z/D', $errorCategory) === 1
        ) {
            $this->metric('get_' . $errorCategory, 'orders_get');
        }

        if (!in_array($response['state'] ?? null, self::TERMINAL_RESPONSE_STATES, true)) {
            return $state;
        }

        $attemptKey = $this->metadata->getIdempotencyKey($order);
        if (!is_string($attemptKey) || $attemptKey === '') {
            $this->serverError('attempt_key_missing');
            return $state;
        }

        // The poll is the observer: without its source the shared flag would credit `server` first.
        // The applicator itself reports a conflict/unsupported/uncertain outcome as a server error.
        $this->applicator->apply($order->get_id(), $attemptKey, $response, 'pending_query', 'server');
        $persisted = wc_get_order($order->get_id());
        $resolvedState = $persisted instanceof WC_Order ? self::publicStateFor($persisted) : $state;

        if ($persisted instanceof WC_Order) {
            $this->metricOrder = $persisted;
            $this->terminalObservation->record($persisted);
        }

        return $resolvedState;
    }

    /** Sent once per order by the page after the maximum polling duration, never on its own. */
    /**
     * One sample per attempt: replayed receipts with the same credentials cannot add samples.
     * The flag is written before reporting, so a failed write drops the sample instead of
     * counting it twice; a contended lease also drops it.
     */
    private function reportThankyouResultTimeOnce(WC_Order $order, string $state, int $milliseconds): void
    {
        try {
            if ($this->thankyouResultReported($order)) {
                return;
            }

            $lease = $this->terminalLock->acquire($order->get_id());
            if ($lease === null) {
                return;
            }

            try {
                $this->reportThankyouResultTime($order, $state, $milliseconds);
            } finally {
                $this->terminalLock->release($lease);
            }
        } catch (\Throwable $telemetryFailure) {
            // A lost receipt must not change the result already displayed to the buyer.
        }
    }

    private function reportThankyouResultTime(WC_Order $order, string $state, int $milliseconds): void
    {
        // The pre-check read a cached copy; a concurrent receipt may have flagged it since.
        $order->read_meta_data(true);
        if ($this->thankyouResultReported($order)) {
            return;
        }
        $this->metadata->setPpOrderThankyouResultReported($order, $state);
        ($this->reportMetric)(
            SmartPixMetricSchema::THANKYOU_RESULT_TIME,
            $state,
            number_format($milliseconds / 1000, 3, '.', '') . 's',
            []
        );
    }

    private function thankyouResultReported(WC_Order $order): bool
    {
        $reported = $this->metadata->getPpOrderThankyouResultReported($order);

        return $reported !== '' && $reported !== null && $reported !== false;
    }

    private function reportPollingExpired(WC_Order $order, string $state): void
    {
        $transient = self::POLLING_EXPIRED_TRANSIENT_PREFIX . $order->get_id();
        if ($state !== self::STATE_PENDING || get_transient($transient) !== false) {
            return;
        }

        // The transient read and write are not atomic: two expired pages would both report without the lease.
        $lease = $this->terminalLock->acquire($order->get_id());
        if ($lease === null) {
            return;
        }

        try {
            if (get_transient($transient) !== false) {
                return;
            }
            set_transient($transient, 1, self::POLLING_EXPIRED_REPORT_TTL_SECONDS);
            $this->metric('expired', 'max_duration');
        } finally {
            $this->terminalLock->release($lease);
        }
    }

    /**
     * Every ownership failure maps to a reason that is logged, never returned to the caller.
     *
     * @param mixed $orderId
     * @param mixed $orderKey
     * @return WC_Order|string
     */
    private function authorizedOrder($orderId, $orderKey)
    {
        if (!is_string($orderId) || !ctype_digit($orderId) || (int) $orderId <= 0) {
            return 'invalid_order_id';
        }

        $order = wc_get_order((int) $orderId);
        if (!$order instanceof WC_Order) {
            return 'order_not_found';
        }

        if (!is_string($orderKey) || $orderKey === '' || strlen($orderKey) > self::MAX_ORDER_KEY_LENGTH) {
            return 'missing_order_key';
        }

        if (!hash_equals($order->get_order_key(), $orderKey)) {
            return 'invalid_order_key';
        }

        if ($order->get_payment_method() !== PixGateway::ID) {
            return 'gateway_mismatch';
        }

        if ($this->metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1) {
            return 'contract_mismatch';
        }

        $reference = $this->metadata->getPpOrderId($order);
        if ((!is_string($reference) || $reference === '') && !SmartPixCreationStore::hasStarted($order)) {
            return 'order_not_linked';
        }

        return $order;
    }

    /** Native statuses keep their mapping; any other status is approved only when WooCommerce reports it paid. */
    public static function publicStateFor(WC_Order $order): string
    {
        return self::PUBLIC_STATE_BY_ORDER_STATUS[$order->get_status()]
            ?? ($order->is_paid() ? self::STATE_APPROVED : self::STATE_PENDING);
    }

    /** Shared by the initial page and polling: never expose provider details or messages. */
    public static function publicErrorFor(WC_Order $order, OrderMetadata $metadata): ?string
    {
        if (self::publicStateFor($order) !== self::STATE_REJECTED) {
            return null;
        }
        $detail = $metadata->getPpPaymentStatusDetail($order);
        return SmartPixBuyerError::fromRejectionStatusDetail(is_string($detail) ? $detail : null);
    }

    /** Must run under the lease: read-then-write on a transient is not atomic, so outside it two polls could both pass. */
    private function acquireQueryWindow(WC_Order $order, ?int $age): bool
    {
        $transient = self::RATE_LIMIT_TRANSIENT_PREFIX . $order->get_id();
        if (get_transient($transient) !== false) {
            return false;
        }

        set_transient($transient, 1, self::queryWindowSeconds($age));

        return true;
    }

    /** An unknown attempt age keeps the shortest window, as before the backoff. */
    private static function queryWindowSeconds(?int $age): int
    {
        foreach (self::QUERY_WINDOW_SECONDS_BY_AGE as $maximumAge => $window) {
            if ($age === null || $age < $maximumAge) {
                return $window;
            }
        }
        return self::LATE_QUERY_WINDOW_SECONDS;
    }

    /**
     * Seconds since this attempt started waiting, or null when the start was never recorded.
     * Shared with the pending page so a reload continues the same polling window.
     */
    public static function attemptAgeSeconds(OrderMetadata $metadata, WC_Order $order): ?int
    {
        $pendingSince = $metadata->getPpOrderPendingSince($order);
        if (is_int($pendingSince)) {
            $pendingSince = (string) $pendingSince;
        }
        if (!is_string($pendingSince) || !ctype_digit($pendingSince) || (int) $pendingSince <= 0) {
            return null;
        }
        return max(0, time() - (int) $pendingSince);
    }

    private function report(string $reason): void
    {
        try {
            ($this->reportRejection)($reason);
        } catch (\Throwable $telemetryFailure) {
            // Telemetry must never prevent the 403 that blocks the request.
        }
    }

    /** Separate financial observations, page expiry and failed Orders reads at the emitter. */
    private function metric(string $value, string $message): void
    {
        try {
            $name = in_array($value, ['approved', 'rejected', 'cancelled'], true) ? self::PAYMENT_STATUS_METRIC
                : ($value === 'expired' ? self::POLLING_EXPIRED_METRIC : self::QUERY_FAILED_METRIC);
            $details = $this->metricDetails($value === 'expired');
            if ($name === self::PAYMENT_STATUS_METRIC) {
                $details['source'] = 'poll';
                if ($value === self::STATE_REJECTED) {
                    $detail = $this->metadata->getPpPaymentStatusDetail($this->metricOrder);
                    if (is_string($detail) && preg_match('/\A[a-z0-9_]{1,64}\z/D', $detail) === 1) {
                        $details['reason'] = $details['payment_status_detail'] = $detail;
                    }
                }
            }
            ($this->reportMetric)($name, $value, $message, $details);
        } catch (\Throwable $telemetryFailure) {
            // Observability is best-effort and must never change the public state.
        }
    }

    private function metricDetails(bool $includeElapsed = false): array
    {
        if ($this->metricOrder === null) {
            return [];
        }
        $details = $this->query->metricDetails($this->metricOrder);
        if ($includeElapsed) {
            $details['elapsed'] = $this->terminalObservation->secondsSinceAttemptStart($this->metricOrder);
        }
        return $details;
    }

    /** Unexpected internal failures of the poll go to the shared Smart Pix error signal, never to the status metric. */
    private function serverError(string $category, string $message = '', string $operation = self::STATUS_POLL_OPERATION): void
    {
        try {
            $details = $this->metricDetails();
            $details['diagnostic_message'] = 'thankyou.' . $operation . ': ' . ($message !== '' ? $message : SmartPixDiagnostic::reason($category));
            ($this->reportMetric)(self::SERVER_ERROR_METRIC, $operation, $category, $details);
        } catch (\Throwable $telemetryFailure) {
            // Observability is best-effort and must never change the public state.
        }
    }

    /** @return array{rejection: string, state: null} */
    private function rejected(string $reason): array
    {
        return ['rejection' => $reason, 'state' => null];
    }

    /** @return array{rejection: null, state: string, smart_pix_error?: string} */
    private function accepted(string $state): array
    {
        $result = ['rejection' => null, 'state' => $state];
        if ($state === self::STATE_REJECTED && $this->metricOrder !== null) {
            $result[SmartPixBuyerError::PAYMENT_DETAILS_KEY] = self::publicErrorFor($this->metricOrder, $this->metadata) ?? SmartPixBuyerError::REJECTED;
        }
        return $result;
    }
}
