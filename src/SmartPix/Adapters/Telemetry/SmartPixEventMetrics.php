<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Best-effort transport for the gateway-shaped Smart Pix events (creation, status, polling, access).
 * Every event carries the store identity, and the two measured spans also go to their own
 * distributions, because Core Monitor's event tags are strings and cannot be aggregated.
 */
final class SmartPixEventMetrics
{
    /** Where the first observation happened, mapped to the closed distribution schema. */
    private const APPROVAL_SOURCES = [
        'poll' => ['get', 'pending_query'],
        'thankyou' => ['get', 'internal'],
        'notification' => ['notification', 'notification'],
        'checkout' => ['get', 'internal'],
        'server' => ['get', 'internal'],
    ];

    private Datadog $datadog;
    private SmartPixTelemetry $timings;
    /** @var array<string, string> */
    private array $identity;
    private ?string $platformUri;

    /** @param array<string, mixed> $identity site_id, environment and cust_id of the store; non-strings are dropped. */
    public function __construct(Datadog $datadog, SmartPixTelemetry $timings, array $identity = [], ?string $platformUri = null)
    {
        $this->datadog = $datadog;
        $this->timings = $timings;
        $this->platformUri = $platformUri;
        $this->identity = array_filter(
            array_intersect_key($identity, array_flip(['site_id', 'environment', 'cust_id'])),
            static fn($value): bool => is_string($value) && $value !== ''
        );
    }

    /** @param object $mercadopago Plugin container exposing sellerConfig, storeConfig and woocommerce. */
    public static function fromPlugin($mercadopago, ?Datadog $datadog = null): self
    {
        $identity = [];
        $platformUri = null;
        try {
            $identity = [
                'site_id' => $mercadopago->sellerConfig->getSiteId(),
                'environment' => SmartPixEnvironment::metricEnvironment($mercadopago->storeConfig),
                'cust_id' => $mercadopago->sellerConfig->getCustIdFromAT(),
            ];
            $storeUrl = function_exists('site_url') ? site_url() : null;
            $platformUri = is_string($storeUrl) ? SmartPixMetricPage::current($storeUrl) : null;
        } catch (Throwable $failure) {
            // Missing store identity only removes the tags.
        }

        return new self(
            $datadog ?? Datadog::getInstance(),
            new SmartPixTelemetry(SmartPixCoreMonitorMetricSink::fromPlugin($mercadopago)),
            $identity,
            $platformUri
        );
    }

    /**
     * @param string $message Fixed message; for creation and status events, the `<seconds>s` duration.
     * @param array<string, string> $details
     */
    public function send(string $name, string $value, string $message, array $details = []): void
    {
        try {
            if ($name === SmartPixMetricSchema::THANKYOU_RESULT_TIME) {
                if (in_array($value, ['approved', 'rejected'], true)) {
                    $this->timings->thankyouResultTime(self::seconds($message));
                }
                return;
            }
            $payload = SmartPixMetricPayload::gateway($name, $value, $message, $details + $this->identity);
            if ($payload === null) {
                return;
            }
            if ($this->platformUri === null) {
                $this->datadog->sendEvent($payload['name'], $payload['value'], $payload['message'], 'smart_transfer', $payload['details']);
            } else {
                $this->datadog->sendEvent($payload['name'], $payload['value'], $payload['message'], 'smart_transfer', $payload['details'], null, $this->platformUri);
            }
            $this->timing($payload, $message);
        } catch (Throwable $error) {
            // Observability is best-effort and must never change checkout, polling or notifications.
        }
    }

    /** @param array{name: string, message: string, details: array<string, string>} $payload */
    private function timing(array $payload, string $duration): void
    {
        $seconds = self::seconds($duration);
        $details = $payload['details'];
        if ($payload['name'] === 'smart_pix_order_creation' && ($details['event'] ?? null) === 'order_creation_succeeded') {
            $this->timings->orderCreationTime($seconds, $payload['message']);
        } elseif (
            $payload['name'] === SmartPixMetricPayload::STATUS_OBSERVED
            && in_array($details['payment_status'] ?? null, ['approved', 'rejected'], true)
            && isset(self::APPROVAL_SOURCES[$details['source'] ?? ''])
        ) {
            [$operation, $source] = self::APPROVAL_SOURCES[$details['source']];
            if ($details['payment_status'] === 'approved') {
                $this->timings->paymentApprovalTime($seconds, $operation, 'server', $source);
            } else {
                $this->timings->paymentRejectionTime($seconds, $operation, 'server', $source);
            }
        }
    }

    private static function seconds(string $duration): ?float
    {
        return preg_match('/\A[0-9]+(?:\.[0-9]{1,3})?s\z/D', $duration) === 1 ? (float) substr($duration, 0, -1) : null;
    }
}
