<?php

namespace MercadoPago\Woocommerce\HealthMonitor;

use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!defined('ABSPATH')) {
    exit;
}

class FileIntegrityChecker
{
    private const MANIFEST_FILE = 'integrity-manifest.json';

    private const TRANSIENT_CHECKED = 'mp_health_integrity_checked';

    private const TRANSIENT_METRIC_SENT = 'mp_health_integrity_metric_sent';

    private Datadog $datadog;

    private WoocommerceMercadoPago $mercadopago;

    public function __construct()
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;
        $this->datadog = Datadog::getInstance();
    }

    /**
     * Run the integrity check with transient-based rate limiting.
     * Executes at most once per hour. Sends metric only when tampered and not already sent.
     *
     * @return void
     */
    public function runWithRateLimit(): void
    {
        try {
            if (false !== get_transient(self::TRANSIENT_CHECKED)) {
                return;
            }

            $result = $this->run();

            set_transient(self::TRANSIENT_CHECKED, $result['status'], HOUR_IN_SECONDS);

            if ($result['status'] === 'tampered' && false === get_transient(self::TRANSIENT_METRIC_SENT)) {
                $this->datadog->sendEvent(
                    'mp_file_integrity_failed',
                    'true',
                    implode(',', $result['files']),
                    null,
                    [
                        'site_id' => $this->mercadopago->sellerConfig->getSiteId(),
                        'environment' => $this->mercadopago->storeConfig->isTestMode() ? 'homol' : 'prod',
                        'cust_id' => $this->mercadopago->sellerConfig->getCustIdFromAT(),
                        'team' => 'big',
                    ]
                );
                set_transient(self::TRANSIENT_METRIC_SENT, true, HOUR_IN_SECONDS);
            }
        } catch (\Exception $e) {
            $this->datadog->sendEvent(
                'mp_file_integrity_checker_error',
                'true',
                $e->getMessage()
            );
        }
    }

    /**
     * Compare current file hashes against the integrity manifest.
     * Never throws — any exception returns ['status' => 'ok'].
     *
     * @return array{status: string, files: array<string>}
     */
    public function run(): array
    {
        try {
            $manifest = $this->loadManifest();

            if (empty($manifest)) {
                return ['status' => 'ok', 'files' => []];
            }

            $tampered = [];

            foreach ($manifest as $relativePath => $expectedHash) {
                $absolutePath = $this->resolvePath($relativePath);

                if (!is_file($absolutePath) || !is_readable($absolutePath)) {
                    $tampered[] = $relativePath;
                    continue;
                }

                $actualHash = hash_file('sha256', $absolutePath);

                if ($actualHash !== $expectedHash) {
                    $tampered[] = $relativePath;
                }
            }

            return [
                'status' => empty($tampered) ? 'ok' : 'tampered',
                'files'  => $tampered,
            ];
        } catch (\Exception $e) {
            $this->datadog->sendEvent(
                'mp_file_integrity_checker_error',
                'true',
                $e->getMessage()
            );
            return ['status' => 'ok', 'files' => []];
        }
    }

    /**
     * @return array<string, string>
     */
    private function loadManifest(): array
    {
        $manifestPath = $this->resolvePath(self::MANIFEST_FILE);

        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            return [];
        }

        $content = file_get_contents($manifestPath);

        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolvePath(string $relativePath): string
    {
        return plugin_dir_path(MP_PLUGIN_FILE) . $relativePath;
    }
}
