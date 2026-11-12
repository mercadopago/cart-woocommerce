<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

if (!defined('ABSPATH')) {
    exit;
}

/** Closed metric schema; none of these fields contains financial or request identifiers. */
final class SmartPixMetricSchema
{
    public const ERROR = 'smart_pix_server_error';
    public const TIME = 'smart_pix_server_processing_time';
    // Core Monitor drops `details` on the distribution route, so each measured span has its own name.
    public const ORDER_CREATION_TIME = 'smart_pix_order_creation_time';
    public const PAYMENT_APPROVAL_TIME = 'smart_pix_payment_approval_time';
    public const PAYMENT_REJECTION_TIME = 'smart_pix_payment_rejection_time';
    public const THANKYOU_RESULT_TIME = 'smart_pix_thankyou_result_time';
    public const EFFECT = 'smart_pix_server_effect_result';
    public const NOTIFICATION_REJECTED = 'smart_pix_server_notification_rejected';
    public const PAYMENT_REJECTED = 'smart_pix_server_payment_rejected';
    public const MUTATION = 'smart_pix_server_mutation_result';
    // Historical: no emitter since unlinked checkout reloads stopped reporting a recovery.
    // Kept closed so the existing dashboard contract and old data stay readable.
    public const CREATION_RECOVERY = 'smart_pix_checkout_creation_recovered';
    public const EXPIRATION = 'smart_pix_pending_expiration';
    public const LEGACY_PAYMENT = 'smart_pix_server_legacy_payment';
    public const RECONCILIATION = 'smart_pix_server_reconciliation';

    private const COUNTERS = [
        self::ERROR => ['true'],
        self::EFFECT => ['applied', 'no_change', 'conflict', 'unsupported', 'uncertain', 'busy'],
        self::NOTIFICATION_REJECTED => [
            'invalid_auth', 'invalid_linkage', 'body_too_large', 'invalid_payload',
            'conflict', 'unsupported', 'effect_unresolved',
        ],
        // Buyer-facing rejection classes, mirroring SmartPixBuyerError rejection codes.
        self::PAYMENT_REJECTED => ['insufficient_balance', 'limit_exceeded', 'rejected'],
        self::MUTATION => ['succeeded', 'already_applied', 'failed'],
        self::EXPIRATION => ['cancel_requested'],
        self::CREATION_RECOVERY => ['result_unknown'],
        self::LEGACY_PAYMENT => ['approved'],
        self::RECONCILIATION => ['resolved', 'rescheduled', 'manual_review'],
    ];

    /**
     * Closed non-empty messages per counter value. Core Monitor tags `message`, so a message
     * distinguishes causes that share one counter value without adding a new value.
     */
    private const MESSAGES = [
        self::NOTIFICATION_REJECTED => ['invalid_payload' => ['unreadable_body']],
        // A late Smart Pix Order paid after the order was paid with another method.
        self::RECONCILIATION => ['manual_review' => ['superseded_payment']],
        // Allowlisted provider status details; any other detail is reported as `other`.
        self::PAYMENT_REJECTED => [
            'insufficient_balance' => ['insufficient_amount'],
            'limit_exceeded' => ['rejected_cap_exceeded'],
            'rejected' => ['high_risk', 'other'],
        ],
    ];

    /** Allowed operation/source pairs per distribution. */
    private const DISTRIBUTIONS = [
        self::TIME => ['operation' => ['create', 'notification', 'refund', 'cancel']],
        self::ORDER_CREATION_TIME => ['operation' => ['create'], 'source' => ['sync_response']],
        self::PAYMENT_APPROVAL_TIME => ['operation' => ['get', 'notification'], 'source' => ['pending_query', 'notification', 'internal']],
        self::PAYMENT_REJECTION_TIME => ['operation' => ['get', 'notification'], 'source' => ['pending_query', 'notification', 'internal']],
        self::THANKYOU_RESULT_TIME => ['operation' => ['get'], 'source' => ['internal']],
    ];

    private const DETAILS = [
        'operation' => ['create', 'get', 'validation', 'notification', 'apply', 'refund', 'cancel'],
        'source' => ['sync_response', 'pending_query', 'notification', 'internal'],
        'surface' => ['classic', 'blocks', 'order_pay', 'server'],
        'category' => ['timeout', 'http_4xx', 'http_5xx', 'invalid_contract', 'unknown_state',
            'validation', 'authentication', 'unavailable', 'persistence', 'conflict', 'internal_error'],
        'outcome' => ['success', 'failure'],
        'reason_class' => ['invalid_context', 'order_context', 'state_decision', 'ownership',
            'native_effect', 'native_verification', 'unknown'],
        'native_status' => ['pending', 'on-hold', 'processing', 'completed', 'failed', 'cancelled', 'refunded', 'paid_custom', 'unknown'],
        'payment_method' => ['smart_pix'],
        'site_id' => ['MLB'],
        'environment' => ['homol', 'prod'],
    ];

    /** @param array<string, string> $details */
    public static function validDetails(array $details): bool
    {
        foreach (['operation', 'source', 'surface', 'payment_method', 'site_id'] as $key) {
            if (!array_key_exists($key, $details)) {
                return false;
            }
        }
        foreach ($details as $key => $value) {
            if (!isset(self::DETAILS[$key]) || !in_array($value, self::DETAILS[$key], true)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, string> $details */
    public static function validCount(string $name, string $value, array $details): bool
    {
        if (!isset(self::COUNTERS[$name]) || !in_array($value, self::COUNTERS[$name], true) || !self::validDetails($details)) {
            return false;
        }
        if ($name === self::ERROR) {
            return isset($details['category']) && in_array($details['operation'], ['create', 'get', 'validation', 'notification', 'apply', 'refund', 'cancel'], true);
        }
        if ($name === self::CREATION_RECOVERY) {
            return $details['operation'] === 'create' && $details['surface'] === 'classic' && $details['source'] === 'internal';
        }
        if ($name === self::PAYMENT_REJECTED) {
            return $details['operation'] === 'apply';
        }
        if ($name === self::MUTATION) {
            return in_array($details['operation'], ['refund', 'cancel'], true)
                && ($value !== 'failed' || isset($details['category']));
        }
        if ($name === self::EXPIRATION) {
            return $details['operation'] === 'cancel';
        }
        if ($name === self::RECONCILIATION) {
            return $details['operation'] === 'apply' && $details['source'] === 'internal';
        }
        if ($name === self::LEGACY_PAYMENT) {
            return $details['operation'] === 'notification'
                && in_array($details['source'], ['notification', 'pending_query'], true);
        }
        if ($name === self::EFFECT) {
            return $details['operation'] === 'apply' && isset($details['reason_class'], $details['native_status']);
        }
        return $details['operation'] === 'notification' && $details['source'] === 'notification';
    }

    /** Error diagnostics are bounded and redacted at serialization; other messages remain closed. */
    public static function validMessage(string $name, string $value, string $message): bool
    {
        if ($name === self::ERROR) {
            return strlen($message) <= 4096;
        }
        return $message === '' || in_array($message, self::MESSAGES[$name][$value] ?? [], true);
    }

    /** @param array<string, string> $details */
    public static function validDistribution(string $name, float $seconds, array $details): bool
    {
        if (
            !isset(self::DISTRIBUTIONS[$name]) || !is_finite($seconds) || $seconds < 0
            || ($seconds === 0.0 && !in_array($name, [self::PAYMENT_APPROVAL_TIME, self::PAYMENT_REJECTION_TIME, self::THANKYOU_RESULT_TIME], true))
            || !self::validDetails($details) || !isset($details['outcome'])
        ) {
            return false;
        }
        foreach (self::DISTRIBUTIONS[$name] as $key => $allowed) {
            if (!in_array($details[$key], $allowed, true)) {
                return false;
            }
        }
        // Only completed spans are measured by the dedicated distributions.
        return $name === self::TIME || $details['outcome'] === 'success';
    }
}
