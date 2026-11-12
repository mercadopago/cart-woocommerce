<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use Closure;
use Throwable;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixDecimal;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationAuthenticator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationBody;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationIdentifiers;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationLinkage;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles one Smart Pix server notification and returns the HTTP answer to send.
 *
 * Fixed order, so nothing is interpreted before the sender is authenticated:
 * bounded raw read -> authenticate the exact raw bytes -> decode -> extract identifiers
 * -> linkage -> fresh authoritative query -> apply. handleFetched() replaces the first
 * three steps with a notification fetched back from Mercado Pago (Core P&P) and shares the rest.
 *
 * The notification body never decides financial state. Only identifiers are taken from it;
 * the applied decision always comes from the authoritative query response, as with the
 * other notification flows that only take an id and re-fetch from Mercado Pago.
 *
 * The caller (a future WordPress adapter) owns transport: it passes headers already
 * normalized to lowercase name => raw value and the raw declared Content-Length, then
 * sends the returned status and message. This class never echoes, exits or logs, and
 * its messages are fixed: never exception text, body content or identifiers.
 *
 * The query collaborator must be constructed with the 'notification' source.
 * An unpaid attempt whose Orders reference is not persisted yet queries the notification id as
 * a candidate and adopts it only after a validated projection of that Order whose payment names
 * the same provider Order as the notification.
 * The receiver never retries itself. An applied or unchanged state answers 200. A conflict,
 * including an authoritative answer about another Order, answers 409 and an unsupported
 * answer 422: redelivery cannot change them. Busy and uncertain effects, retryable reads,
 * Orders refusing the read of a linked Order (authentication, validation, conflict),
 * unknown/invalid authoritative states, unavailable ownership and unresolved refunds answer 500
 * so the sender redelivers, and a linked order also gets a WP-Cron reconciliation. A redelivery
 * always re-reads Orders and never repeats a provider mutation; an uncertain native effect is
 * not blindly re-executed.
 *
 * Telemetry: one rejection metric per sender rejection, including conflict, unsupported and
 * effect_unresolved (busy or uncertain); server errors use the error counter instead
 * (unavailable when authentication throws, invalid_contract when the authoritative answer
 * names another Order, internal_error for other throws, except query throws, which the query
 * already reports); retryable query failures and unavailable ownership add no error metric,
 * since the client and the applicator already report them; one processing time per request.
 */
final class SmartPixNotificationReceiver
{
    public const OPERATION = 'notification';

    public const SURFACE = 'server';

    public const SOURCE = 'notification';

    public const MESSAGE_PROCESSED = 'Notification processed.';

    public const MESSAGE_IGNORED = 'Notification ignored.';

    public const MESSAGE_INVALID_PAYLOAD = 'Invalid notification payload.';

    public const MESSAGE_UNAUTHENTICATED = 'Notification not authenticated.';

    public const MESSAGE_TOO_LARGE = 'Notification body too large.';

    public const MESSAGE_FAILED = 'Notification processing failed.';

    public const MESSAGE_CONFLICT = 'Notification conflicts with the order state.';

    public const MESSAGE_UNSUPPORTED = 'Notification cannot be applied to the order.';

    public const CATEGORY_CONFLICT = 'conflict';

    public const CATEGORY_UNSUPPORTED = 'unsupported';

    public const CATEGORY_EFFECT_UNRESOLVED = 'effect_unresolved';

    private const CATEGORY_INVALID_AUTH = 'invalid_auth';

    private const STAGE_AUTHENTICATION = 'authentication';

    private const STAGE_QUERY = 'query';

    private const STAGE_INTERNAL = 'internal';

    private const TERMINAL_STATES = ['terminal_success', 'terminal_failure'];

    private const REASON_OWNERSHIP_UNAVAILABLE = 'ownership_unavailable';

    /** Non-retryable answers of Orders about the linked Order; local validation carries no reference. */
    private const REMOTE_READ_FAILURES = ['authentication', 'validation', 'conflict'];

    private SmartPixNotificationBody $body;

    private SmartPixNotificationAuthenticator $authenticator;

    private SmartPixNotificationIdentifiers $identifiers;

    private SmartPixNotificationLinkage $linkage;

    private SmartPixOrderQuery $query;

    private SmartPixStateApplicator $applicator;

    private SmartPixTelemetry $telemetry;

    /** @var (Closure(int): void)|null */
    private ?Closure $terminalObserved;

    /** @var (callable(int): void)|null */
    private $scheduleReconciliation;

    /**
     * @param (Closure(int): void)|null $terminalObserved Receives the order ID after a terminal
     *        state was applied, so a buyer who already left the page is still counted once.
     * @param (callable(int): void)|null $scheduleReconciliation Receives the order id whenever a
     *     linked notification answers 500, so a WP-Cron reconciliation backs up the redelivery.
     */
    public function __construct(
        SmartPixNotificationBody $body,
        SmartPixNotificationAuthenticator $authenticator,
        SmartPixNotificationIdentifiers $identifiers,
        SmartPixNotificationLinkage $linkage,
        SmartPixOrderQuery $query,
        SmartPixStateApplicator $applicator,
        ?SmartPixTelemetry $telemetry = null,
        ?Closure $terminalObserved = null,
        ?callable $scheduleReconciliation = null
    ) {
        $this->body = $body;
        $this->authenticator = $authenticator;
        $this->identifiers = $identifiers;
        $this->linkage = $linkage;
        $this->query = $query;
        $this->applicator = $applicator;
        $this->telemetry = $telemetry ?? new SmartPixTelemetry();
        $this->terminalObserved = $terminalObserved;
        $this->scheduleReconciliation = $scheduleReconciliation;
    }

    /**
     * @param array<string, string> $headers Lowercase header name => raw value, normalized by the caller.
     * @param string|null $contentLength Raw declared length, e.g. from $_SERVER['CONTENT_LENGTH'].
     * @return array{status: int, message: string}
     */
    public function handle(array $headers, ?string $contentLength): array
    {
        return $this->run(self::STAGE_INTERNAL, function (string &$stage) use ($headers, $contentLength): array {
            return $this->process($headers, $contentLength, $stage);
        });
    }

    /**
     * Entry for notifications whose authenticity comes from fetching them back from Mercado
     * Pago with the seller credentials (Core P&P), replacing the raw read and the authenticator.
     *
     * $fetch returns the fetched notification object, or null when the fetched answer is not
     * the requested notification. A throw means Mercado Pago could not be read: it answers 500
     * with server_error category unavailable, so the sender retries. The fetched object then
     * follows the same extraction, linkage, fresh query and guarded apply as handle().
     *
     * @param callable(): (array<string, mixed>|null) $fetch
     * @return array{status: int, message: string}
     */
    public function handleFetched(callable $fetch): array
    {
        return $this->run(self::STAGE_AUTHENTICATION, function (string &$stage) use ($fetch): array {
            $payload = $fetch();
            $stage = self::STAGE_INTERNAL;
            if (!is_array($payload)) {
                return $this->rejectBody(SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD);
            }
            return $this->processPayload($payload, $stage);
        });
    }

    /**
     * Answer for a body above the limit that the caller detected before any fetch or decode:
     * the same 413, rejection metric and processing time as the bounded raw read.
     *
     * @return array{status: int, message: string}
     */
    public function rejectOversized(): array
    {
        return $this->run(self::STAGE_INTERNAL, function (string &$stage): array {
            return $this->rejectBody(SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE);
        });
    }

    /**
     * @param callable(string&): array{status: int, message: string, success: bool} $process
     * @return array{status: int, message: string}
     */
    private function run(string $stage, callable $process): array
    {
        $this->telemetry->resetContext();
        $startedAt = $this->telemetry->startTimer();
        try {
            $result = $process($stage);
        } catch (Throwable $failure) {
            // Not a sender rejection: no rejection metric and no exception details exposed.
            $this->reportFailure($stage, $failure);
            $result = ['status' => 500, 'message' => self::MESSAGE_FAILED, 'success' => false];
        }
        return $this->finish($startedAt, $result);
    }

    /**
     * @param array<string, string> $headers
     * @param string $stage Set to the stage running when a Throwable escapes.
     * @return array{status: int, message: string, success: bool}
     */
    private function process(array $headers, ?string $contentLength, string &$stage): array
    {
        $raw = $this->body->readRaw($contentLength);
        if ($raw['accepted'] !== true || !is_string($raw['raw_body'])) {
            return $this->rejectBody((string) $raw['category']);
        }

        $stage = self::STAGE_AUTHENTICATION;
        $authenticated = $this->authenticator->authenticate($headers, $raw['raw_body']);
        $stage = self::STAGE_INTERNAL;
        if (!$authenticated) {
            return $this->reject(self::CATEGORY_INVALID_AUTH, '', 401, self::MESSAGE_UNAUTHENTICATED);
        }

        $decoded = $this->body->decode($raw['raw_body']);
        if ($decoded['accepted'] !== true || !is_array($decoded['payload'])) {
            return $this->rejectBody((string) $decoded['category']);
        }

        return $this->processPayload($decoded['payload'], $stage);
    }

    /**
     * Shared tail for an authenticated notification object: identifiers -> linkage ->
     * fresh authoritative query -> guarded apply.
     *
     * @param array<string, mixed> $payload
     * @param string $stage Set to the stage running when a Throwable escapes.
     * @return array{status: int, message: string, success: bool}
     */
    private function processPayload(array $payload, string &$stage): array
    {
        $identifiers = $this->identifiers->extract($payload);
        if (
            $identifiers === null
            || !is_string($identifiers['attempt_reference'] ?? null)
            || !is_string($identifiers['order_reference'] ?? null)
            || (array_key_exists('provider_order_reference', $identifiers) && !is_string($identifiers['provider_order_reference']))
        ) {
            return $this->rejectBody(SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD);
        }

        $link = $this->linkage->resolve(
            $identifiers['attempt_reference'],
            $identifiers['order_reference'],
            $identifiers['provider_order_reference'] ?? null
        );
        if (($link['category'] ?? null) === SmartPixNotificationLinkage::CATEGORY_SUPERSEDED) {
            return $this->reviewSuperseded($link['order'], $identifiers, $stage);
        }
        // The notification id names the Core Order, so it is the candidate of a pending attempt.
        $candidate = ($link['category'] ?? null) === SmartPixNotificationLinkage::CATEGORY_PENDING_LINKAGE
            ? $identifiers['order_reference'] : null;
        $expected = $candidate ?? $link['order_reference'];
        if (
            ($link['linked'] !== true && $candidate === null) || $link['order'] === null
            || !is_string($link['attempt_key']) || !is_string($expected)
        ) {
            // Acknowledged so the sender does not retry a notification for an order we do not own.
            return $this->reject(SmartPixNotificationLinkage::CATEGORY_INVALID_LINKAGE, '', 200, self::MESSAGE_IGNORED);
        }

        // Fresh authoritative read; nothing financial is taken from the notification body.
        // Returned failures are already reported by the query and its client; only throws escape.
        $stage = self::STAGE_QUERY;
        $response = $this->query->query($link['order'], self::SURFACE, $candidate);
        $stage = self::STAGE_INTERNAL;
        $terminal = in_array($response['state'] ?? null, self::TERMINAL_STATES, true);
        $reference = $response['order_reference'] ?? null;
        if ($reference !== $expected && ($terminal || $reference !== null)) {
            // The authoritative answer is about another Order: a contract conflict without effects.
            $this->telemetry->error(self::OPERATION, 'invalid_contract', self::SURFACE, self::SOURCE, 'notification.order_read: authoritative order_reference differs from the linked or candidate order');
            return ['status' => 409, 'message' => self::MESSAGE_CONFLICT, 'success' => false];
        }
        $providerOrderReference = $identifiers['provider_order_reference'] ?? null;
        // A read that failed names no provider Order: it falls through to the failure handling below
        // (redelivery and reconciliation) instead of being ignored as a foreign notification.
        $readFailed = ($response['outcome'] ?? null) === 'failure' && !$terminal;
        if (
            ($link['provider_reference_missing'] ?? false) === true && is_string($providerOrderReference) && !$readFailed
            && !$this->linkage->completeReferences($link['order'], (string) $expected, $providerOrderReference, $response)
        ) {
            // Only the authenticated read can name the provider Order of this linked PPORD.
            return $this->reject(SmartPixNotificationLinkage::CATEGORY_INVALID_LINKAGE, '', 200, self::MESSAGE_IGNORED);
        }
        if ($candidate !== null && $this->confirmsOrder($response, $candidate, $providerOrderReference, $link['order'])) {
            $adopted = $this->linkage->adopt(
                $link['order'],
                $candidate,
                (string) $response['observed']['payment_id'],
                (string) $providerOrderReference
            );
            if (!$adopted) {
                // Another Orders reference was persisted meanwhile; this notification is not ours.
                return $this->reject(SmartPixNotificationLinkage::CATEGORY_INVALID_LINKAGE, '', 200, self::MESSAGE_IGNORED);
            }
        } elseif ($candidate !== null) {
            // Nothing confirmed the Order and nothing was written: transient reads are redelivered.
            return $this->isRetryableFailure($response)
                ? $this->retryLater()
                : ['status' => 200, 'message' => self::MESSAGE_PROCESSED, 'success' => true];
        }
        if ($terminal) {
            $result = $this->applicator->apply(
                $link['order']->get_id(),
                $link['attempt_key'],
                $response,
                self::SOURCE,
                self::SURFACE
            );
            $unresolvedRefund = (isset($response['refund_context'])
                    || ($response['observed']['order_status_detail'] ?? null) === 'partially_refunded'
                    || ($response['observed']['payment_status_detail'] ?? null) === 'partially_refunded')
                && !in_array($result['outcome'], ['applied', 'no_change'], true);
            if ($unresolvedRefund && $result['reason'] !== self::REASON_OWNERSHIP_UNAVAILABLE
                && $this->linkage->inManualReview($link['order'])) {
                // The merchant already owns this refund: redelivery can no longer resolve it.
                return ['status' => 200, 'message' => self::MESSAGE_PROCESSED, 'success' => true];
            }
            if ($result['reason'] === self::REASON_OWNERSHIP_UNAVAILABLE || $unresolvedRefund) {
                // Financial reconciliation is not confirmed; the applicator already reported it.
                $this->reconcileLater($link['order']->get_id());
                return $this->retryLater();
            }
            if (in_array($result['outcome'], ['applied', 'no_change'], true)) {
                $this->observeTerminal($link['order']->get_id());
            }
            $answer = $this->effectAnswer($result['outcome']);
            if ($answer !== null) {
                // A paid Order the order state refuses is sent to the reconciliation, which records it
                // for manual review; redelivery alone would only repeat the conflict.
                if ($answer['status'] === 500 || SmartPixReconciliation::isPaidConflict($result, $response)) {
                    $this->reconcileLater($link['order']->get_id());
                }
                return $answer;
            }
        } elseif ($this->isRetryableFailure($response)) {
            // Nothing was learned yet; the query and its client already reported the failure.
            $this->reconcileLater($link['order']->get_id());
            return $this->retryLater();
        } elseif ($this->isRefusedRead($response, (string) $expected)) {
            // Not a processed notification: the WP-Cron reconciliation backs it up and escalates to
            // manual review when it never resolves.
            $this->reconcileLater($link['order']->get_id());
            return $this->retryLater();
        }

        return ['status' => 200, 'message' => self::MESSAGE_PROCESSED, 'success' => true];
    }

    /**
     * The buyer paid this order with another method after a Smart Pix create that never answered.
     * Only a validated, approved projection of that same Order is recorded for manual review; it
     * never changes the native order, and anything else is acknowledged without effects.
     * @param mixed $order
     * @param array<string, mixed> $identifiers
     * @return array{status: int, message: string, success: bool}
     */
    private function reviewSuperseded($order, array $identifiers, string &$stage): array
    {
        $candidate = $identifiers['order_reference'];
        $providerOrderReference = $identifiers['provider_order_reference'] ?? null;
        $attemptReference = $identifiers['attempt_reference'];
        if (!$order instanceof WC_Order || !is_string($candidate) || !is_string($attemptReference)) {
            return $this->reject(SmartPixNotificationLinkage::CATEGORY_INVALID_LINKAGE, '', 200, self::MESSAGE_IGNORED);
        }

        $stage = self::STAGE_QUERY;
        $response = $this->query->querySuperseded($order, $candidate, $attemptReference);
        $stage = self::STAGE_INTERNAL;
        if ($this->isRetryableFailure($response) || $this->isRefusedRead($response, $candidate)) {
            // Only redelivery can still reach a late paid Order: no reconciliation tracks this attempt.
            return $this->retryLater();
        }
        if (
            ($response['state'] ?? null) !== 'terminal_success'
            || ($response['order_reference'] ?? null) !== $candidate
            || $providerOrderReference === null
            || ($response['observed']['payment_reference'] ?? null) !== $providerOrderReference
        ) {
            return ['status' => 200, 'message' => self::MESSAGE_PROCESSED, 'success' => true];
        }
        if ($this->linkage->review($order, $candidate)) {
            $this->telemetry->reconciliation('manual_review', 'superseded_payment');
        }
        return ['status' => 200, 'message' => self::MESSAGE_PROCESSED, 'success' => true];
    }

    private function observeTerminal(int $orderId): void
    {
        try {
            if ($this->terminalObserved !== null) {
                ($this->terminalObserved)($orderId);
            }
        } catch (Throwable $telemetryFailure) {
            // Observability never changes the notification answer.
        }
    }

    /**
     * Applied and unchanged effects keep the 200 answer. Conflicts and unsupported answers will
     * not change on redelivery; busy and uncertain effects ask the sender to redeliver.
     * @return array{status: int, message: string, success: bool}|null
     */
    private function effectAnswer(string $outcome): ?array
    {
        if ($outcome === 'conflict') {
            return $this->reject(self::CATEGORY_CONFLICT, '', 409, self::MESSAGE_CONFLICT);
        }
        if ($outcome === 'unsupported') {
            return $this->reject(self::CATEGORY_UNSUPPORTED, '', 422, self::MESSAGE_UNSUPPORTED);
        }
        if ($outcome === 'busy' || $outcome === 'uncertain') {
            return $this->reject(self::CATEGORY_EFFECT_UNRESOLVED, '', 500, self::MESSAGE_FAILED);
        }
        return null;
    }

    /**
     * Only a validated projection of the candidate Order (pending, approved or rejected) confirms it;
     * the normalizer already matched its id and the attempt external_reference. Its payment must also
     * name the provider Order of the notification, which adoption persists as for a checkout link,
     * and its payments must add up to this order's total, so a mismatched Order is never linked.
     * @param array<string, mixed> $response
     */
    private function confirmsOrder(array $response, string $candidate, ?string $providerOrderReference, WC_Order $order): bool
    {
        if (
            ($response['order_reference'] ?? null) !== $candidate
            || $providerOrderReference === null
            || !is_string($response['observed']['payment_id'] ?? null)
            || ($response['observed']['payment_reference'] ?? null) !== $providerOrderReference
            || !$this->matchesOrderTotal($response['payments'] ?? null, $order)
        ) {
            return false;
        }
        $state = $response['state'] ?? null;
        return in_array($state, self::TERMINAL_STATES, true)
            || (($response['outcome'] ?? null) === 'success' && $state === 'processing');
    }

    /** @param mixed $payments */
    private function matchesOrderTotal($payments, WC_Order $order): bool
    {
        // WooCommerce stubs describe float, but CRUD preserves the decimal string.
        $total = SmartPixDecimal::canonical($order->get_total('edit'));
        if ($total === null || $total === '0.00' || !is_array($payments) || $payments === []) {
            return false;
        }
        $sum = '0.00';
        foreach ($payments as $payment) {
            $value = is_array($payment) && is_array($payment['amount'] ?? null) ? ($payment['amount']['value'] ?? null) : null;
            if (!SmartPixDecimal::isCanonical($value) || ($payment['amount']['currency'] ?? null) !== 'BRL') {
                return false;
            }
            $sum = SmartPixDecimal::add($sum, $value);
        }
        return $sum === $total;
    }

    /** @param array<string, mixed> $response */
    private function isRetryableFailure(array $response): bool
    {
        return ($response['outcome'] ?? null) === 'failure'
            && is_array($response['error'] ?? null)
            && ($response['error']['retryable'] ?? null) === true;
    }

    /**
     * An unsupported authoritative state, or Orders refusing the read of this Order (credentials,
     * request or conflict). A local validation failure names no Order and is not a refused read.
     * @param array<string, mixed> $response
     */
    private function isRefusedRead(array $response, string $expected): bool
    {
        $category = $response['error']['category'] ?? null;
        return ($response['outcome'] ?? null) === 'failure'
            && (in_array($category, ['unknown', 'invalid_response'], true)
                || (in_array($category, self::REMOTE_READ_FAILURES, true)
                    && ($response['order_reference'] ?? null) === $expected));
    }

    /** The reconciliation backs up Core redelivery; scheduling failures never change the answer. */
    private function reconcileLater(int $orderId): void
    {
        if ($this->scheduleReconciliation === null) {
            return;
        }
        try {
            ($this->scheduleReconciliation)($orderId);
        } catch (Throwable $exception) {
            // The 500 answer still asks Core P&P to redeliver.
        }
    }

    /**
     * Server error without exception details, so the sender redelivers the notification.
     * @return array{status: int, message: string, success: bool}
     */
    private function retryLater(): array
    {
        return ['status' => 500, 'message' => self::MESSAGE_FAILED, 'success' => false];
    }

    private function reportFailure(string $stage, Throwable $failure): void
    {
        if ($stage === self::STAGE_QUERY) {
            // The query already reported its own internal_error before rethrowing.
            return;
        }
        $category = $stage === self::STAGE_AUTHENTICATION ? 'unavailable' : 'internal_error';
        $this->telemetry->error(self::OPERATION, $category, self::SURFACE, self::SOURCE, 'notification.' . $stage . ': ' . SmartPixDiagnostic::exception($failure));
    }

    /** @return array{status: int, message: string, success: bool} */
    private function rejectBody(string $category): array
    {
        if ($category === SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE) {
            return $this->reject($category, '', 413, self::MESSAGE_TOO_LARGE);
        }
        if ($category === SmartPixNotificationBody::CATEGORY_UNREADABLE_BODY) {
            return $this->reject(SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD, $category, 400, self::MESSAGE_INVALID_PAYLOAD);
        }
        // Any unknown body category fails closed as an invalid payload.
        return $this->reject(SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD, '', 400, self::MESSAGE_INVALID_PAYLOAD);
    }

    /** @return array{status: int, message: string, success: bool} */
    private function reject(string $category, string $reason, int $status, string $message): array
    {
        $this->telemetry->notificationRejected($category, $reason);
        return ['status' => $status, 'message' => $message, 'success' => false];
    }

    /**
     * @param array{status: int, message: string, success: bool} $result
     * @return array{status: int, message: string}
     */
    private function finish(?float $startedAt, array $result): array
    {
        $this->telemetry->processingTime(
            self::OPERATION,
            $this->telemetry->elapsed($startedAt),
            $result['success'] ? 'success' : 'failure',
            self::SURFACE,
            self::SOURCE
        );
        return ['status' => $result['status'], 'message' => $result['message']];
    }
}
