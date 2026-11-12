<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

use Throwable;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;

if (!defined('ABSPATH')) {
    exit;
}

/** Explicitly configured, best-effort Core Monitor transport for the closed Smart Pix schema. */
final class SmartPixCoreMonitorMetricSink implements SmartPixMetricSink
{
    private const BASE_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/';

    private string $environment;
    private string $woocommerceVersion;
    private string $pluginVersion;
    private bool $configured;
    private ?SmartPixMetricContext $context;
    private ?string $custId;
    private ?string $platformUrl;
    private ?string $platformUri;

    public function __construct(
        string $environment,
        string $woocommerceVersion,
        string $pluginVersion,
        ?SmartPixMetricContext $context = null,
        ?string $custId = null,
        ?string $platformUrl = null,
        ?string $platformUri = null
    ) {
        $this->context = $context;
        // Store identity used to answer "where": the seller account and the store URL.
        $this->custId = is_string($custId) && preg_match('/\A[0-9]{1,20}\z/D', $custId) === 1 ? $custId : null;
        $this->platformUrl = is_string($platformUrl) && preg_match('#\Ahttps?://[^\s]{1,255}\z#D', $platformUrl) === 1 ? $platformUrl : null;
        $this->platformUri = $this->platformUrl === null ? null : SmartPixMetricPage::sanitize($platformUri, $this->platformUrl);
        $this->environment = $environment;
        $this->woocommerceVersion = $woocommerceVersion;
        $this->pluginVersion = $pluginVersion;
        $this->configured = in_array($environment, ['homol', 'prod'], true)
            && $this->validVersion($woocommerceVersion) && $this->validVersion($pluginVersion);
    }

    /**
     * Sink tagged `homol` for a store in test mode or under the homologation override, `prod`
     * otherwise; the tag never changes Core P&P routing. Unknown versions leave the sink
     * unconfigured, which disables emission without affecting callers.
     *
     * @param object $mercadopago Plugin container exposing storeConfig, sellerConfig and woocommerce.
     */
    public static function fromPlugin($mercadopago, ?SmartPixMetricContext $context = null): self
    {
        $environment = SmartPixEnvironment::metricEnvironment($mercadopago->storeConfig);
        $woocommerceVersion = $mercadopago->woocommerce->version ?? '';
        $pluginVersion = defined('MP_VERSION') ? MP_VERSION : '';
        $custId = null;
        $platformUrl = null;
        try {
            $custId = isset($mercadopago->sellerConfig) ? $mercadopago->sellerConfig->getCustIdFromAT() : null;
            $platformUrl = function_exists('site_url') ? site_url() : null;
        } catch (Throwable $failure) {
            // Missing store identity only removes the tags; it never disables the sink.
        }

        return new self(
            $environment,
            is_string($woocommerceVersion) ? $woocommerceVersion : '',
            is_string($pluginVersion) ? $pluginVersion : '',
            $context,
            is_string($custId) ? $custId : null,
            is_string($platformUrl) ? $platformUrl : null,
            is_string($platformUrl) ? SmartPixMetricPage::current($platformUrl) : null
        );
    }

    /** @param array<string, string> $details */
    public function count(string $name, string $value, array $details, string $message = ''): void
    {
        if (
            !$this->configured
            || !SmartPixMetricSchema::validCount($name, $value, $details)
            || !SmartPixMetricSchema::validMessage($name, $value, $message)
        ) {
            return;
        }
        $details['environment'] = $this->environment;
        if (!SmartPixMetricSchema::validCount($name, $value, $details)) {
            return;
        }
        $payload = SmartPixMetricPayload::semantic($name, $value, $details, $message);
        $this->send('event', $name, ['value' => $payload['value'], 'message' => $payload['message']], $payload['details']);
    }

    /** @param array<string, string> $details */
    public function distribution(string $name, float $seconds, array $details): void
    {
        if (!$this->configured || !SmartPixMetricSchema::validDistribution($name, $seconds, $details)) {
            return;
        }
        $details['environment'] = $this->environment;
        if (!SmartPixMetricSchema::validDistribution($name, $seconds, $details)) {
            return;
        }
        $payload = SmartPixMetricPayload::semantic($name, '', $details);
        // Fixed decimal milliseconds: a float cast can yield noise ("300.00000000000006") or "1.0E-5".
        $payload['details']['request_time'] = rtrim(rtrim(sprintf('%.3F', $seconds * 1000), '0'), '.');
        // Preserve the existing distribution's unit (seconds); details durations use milliseconds.
        $this->send('metric', $name, ['value' => $seconds], $payload['details']);
    }

    private function validVersion(string $version): bool
    {
        return strlen($version) <= 32
            && preg_match('/\A[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(?:-(?:dev|alpha|beta|rc)(?:[.-]?[0-9]{1,3})?)?\z/', $version) === 1;
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, string> $details
     */
    private function send(string $kind, string $name, array $value, array $details): void
    {
        try {
            $platform = ['name' => 'woocommerce', 'version' => $this->woocommerceVersion];
            if ($this->platformUrl !== null) {
                $platform['url'] = $this->platformUrl;
            }
            if ($this->platformUri !== null) {
                $platform['uri'] = $this->platformUri;
            }
            if ($this->custId !== null) {
                $details['cust_id'] = $this->custId;
            }
            $payload = $value + [
                'plugin_version' => $this->pluginVersion,
                'platform' => $platform,
                'details' => SmartPixMetricPayload::correlate($details, $this->context === null ? [] : $this->context->details()),
            ];
            $body = wp_json_encode($payload);
            if (!is_string($body) || strlen($body) < 2) {
                return;
            }
            wp_remote_post(self::BASE_URL . $kind . '/datadog/smb/' . $name, [
                'blocking' => false,
                'timeout' => 3,
                'redirection' => 0,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $body,
            ]);
        } catch (Throwable $failure) {
            // Delivery is unacknowledged; never retry or expose transport diagnostics.
        }
    }
}
