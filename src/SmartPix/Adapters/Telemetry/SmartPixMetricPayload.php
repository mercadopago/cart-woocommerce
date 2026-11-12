<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

if (!defined('ABSPATH')) {
    // @codeCoverageIgnoreStart
    exit;
    // @codeCoverageIgnoreEnd
}

/** Projects closed internal signals onto the attributes supported by Core Monitor. */
final class SmartPixMetricPayload
{
    public const STATUS_OBSERVED = 'smart_pix_payment_status_observed';
    public const POLLING_EXPIRED = 'smart_pix_polling_expired';
    public const QUERY_FAILED = 'smart_pix_status_query_failed';
    /** Where a terminal outcome was first observed. */
    public const OBSERVATION_SOURCES = ['poll', 'thankyou', 'notification', 'checkout', 'server'];

    public static function gateway(string $name, string $value, string $message, array $context = []): ?array
    {
        $details = ['payment_method' => 'smart_transfer'];
        $surface = $context['surface'] ?? '';
        if ($name === 'smart_pix_order_creation') {
            $declined = in_array($value, ['rejected', 'insufficient_balance', 'limit_exceeded'], true);
            $details['event'] = $value === 'pending' ? 'order_creation_succeeded'
                : ($declined ? 'order_creation_rejected' : 'order_creation_failed');
            if ($value === 'pending' || $declined) {
                $details['payment_status'] = $declined ? 'rejected' : 'pending';
            }
            if ($value !== 'pending') {
                $details['reason'] = $value;
            }
            self::duration($details, 'request_time', $message);
        } elseif ($name === self::STATUS_OBSERVED && in_array($value, ['approved', 'rejected', 'cancelled'], true)) {
            $details += ['event' => 'payment_status_observed', 'payment_status' => $value];
            if (in_array($context['source'] ?? null, self::OBSERVATION_SOURCES, true)) {
                $details['source'] = $context['source'];
            }
            self::duration($details, 'pooling_time', $message);
        } elseif ($name === self::POLLING_EXPIRED) {
            $details += ['event' => 'polling_expired', 'reason' => 'max_duration'];
            // An expired observer does not mean the payment expired.
            self::duration($details, 'pooling_time', $context['elapsed'] ?? 'unknown');
        } elseif ($name === self::QUERY_FAILED) {
            $details += ['event' => 'status_query_failed', 'reason' => preg_replace('/\Aget_/', '', $value)];
        } elseif ($name === 'smart_pix_server_error') {
            $details += ['event' => $value === 'status_poll_client' ? 'polling_client_failed' : 'polling_failed', 'reason' => $message];
        } elseif ($name === 'smart_pix_status_access') {
            $details += ['event' => 'status_access_denied', 'reason' => $value];
        } else {
            return null;
        }
        foreach (['payment_status', 'payment_status_detail', 'site_id', 'environment', 'cust_id'] as $key) {
            if (isset($context[$key]) && is_string($context[$key])) {
                $details[$key] = $context[$key];
            }
        }
        if (isset($context['reason']) && is_string($context['reason'])
            && preg_match('/\A[a-z0-9_]{1,64}\z/D', $context['reason']) === 1) {
            $details['reason'] = $context['reason'];
        }
        if ($name === 'smart_pix_server_error' && isset($context['diagnostic_message'])) {
            $surface = SmartPixDiagnostic::message($context['diagnostic_message']);
        }
        return ['name' => $name, 'value' => '1', 'message' => $surface,
            'details' => self::correlate($details, $context)];
    }

    /** Internal schema is validated by the sink before entering this projection. */
    public static function semantic(string $name, string $value, array $context, string $message = ''): array
    {
        $details = ['payment_method' => 'smart_transfer', 'site_id' => $context['site_id']];
        $surface = $context['surface'];
        $operation = $context['operation'] ?? '';
        $events = [
            SmartPixMetricSchema::CREATION_RECOVERY => ['event' => 'checkout_recovered_after_creation_started', 'reason' => 'result_unknown'],
            SmartPixMetricSchema::EXPIRATION => ['event' => 'unpaid_order_cancellation_requested', 'reason' => 'unpaid_order_timeout'],
            SmartPixMetricSchema::MUTATION => ['event' => $operation . '_' . $value],
            SmartPixMetricSchema::ERROR => ['event' => $operation . '_processing_failed'],
            SmartPixMetricSchema::TIME => ['event' => $operation . '_processing_' . (($context['outcome'] ?? '') === 'success' ? 'succeeded' : 'failed')],
            SmartPixMetricSchema::ORDER_CREATION_TIME => ['event' => 'order_creation_succeeded'],
            SmartPixMetricSchema::PAYMENT_APPROVAL_TIME => ['event' => 'payment_status_observed', 'payment_status' => 'approved'],
            SmartPixMetricSchema::PAYMENT_REJECTION_TIME => ['event' => 'payment_status_observed', 'payment_status' => 'rejected'],
            SmartPixMetricSchema::THANKYOU_RESULT_TIME => ['event' => 'thankyou_result_displayed'],
            SmartPixMetricSchema::EFFECT => ['event' => 'effect_' . $value, 'reason' => $context['reason_class'] ?? 'unknown'],
            SmartPixMetricSchema::NOTIFICATION_REJECTED => ['event' => 'notification_rejected', 'reason' => $message !== '' ? $message : $value],
            SmartPixMetricSchema::PAYMENT_REJECTED => ['event' => 'payment_rejected', 'payment_status' => 'rejected',
                'reason' => $value, 'payment_status_detail' => $message !== '' ? $message : 'other'],
        ];
        $details += $events[$name] ?? [];
        if (isset($context['category'])) {
            $details['reason'] = $context['category'];
        }
        if ($name === SmartPixMetricSchema::ERROR && $message !== '') {
            $surface = SmartPixDiagnostic::message($message);
        } elseif ($name === SmartPixMetricSchema::EFFECT) {
            $surface = $context['native_status'];
        }
        if (isset($context['environment'])) {
            $details['environment'] = $context['environment'];
        }
        return ['name' => $name, 'value' => '1', 'message' => $surface, 'details' => $details];
    }

    public static function correlate(array $details, array $context): array
    {
        foreach (['request_id', 'sdk_instance_id'] as $key) {
            $id = SmartPixMetricContext::identifier($context[$key] ?? null);
            if ($id !== null) {
                $details[$key] = $id;
            }
        }
        if (isset($context['api_route'])) {
            $details['api_route'] = $context['api_route'];
        }
        if (!isset($details['sdk_instance_id'])) {
            $details['missing_fields'] = 'sdk_instance_id';
        }
        return $details;
    }

    private static function duration(array &$details, string $key, string $seconds): void
    {
        if (preg_match('/\A[0-9]+(?:\.[0-9]{1,3})?s\z/D', $seconds) === 1) {
            $number = (float) substr($seconds, 0, -1) * 1000;
            if (is_finite($number) && $number >= 0) {
                $details[$key] = (string) (int) round($number);
            }
        }
    }
}
