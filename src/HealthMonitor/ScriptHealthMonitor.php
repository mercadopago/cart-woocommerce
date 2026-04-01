<?php

namespace MercadoPago\Woocommerce\HealthMonitor;

use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!defined('ABSPATH')) {
    exit;
}

class ScriptHealthMonitor
{
    private const TRANSIENT_CHECKED = 'mp_health_script_checked';

    private const TRANSIENT_METRIC_SENT = 'mp_health_script_metric_sent';

    /**
     * Handles that the plugin attempted to enqueue in this request.
     *
     * @var array<string>
     */
    private array $trackedHandles = [];

    private Datadog $datadog;

    private WoocommerceMercadoPago $mercadopago;

    public function __construct()
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;
        $this->datadog = Datadog::getInstance();
    }

    /**
     * Register the late wp_enqueue_scripts hook to verify that our scripts
     * were not dequeued by third-party code.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', function () {
            $this->check();
        }, 9999);
    }

    /**
     * Mark a script handle as one that the plugin tried to enqueue.
     * Only tracked handles will be verified — avoids false positives for
     * gateways that are not active on the current page.
     *
     * @param string $handle
     *
     * @return void
     */
    public function trackEnqueued(string $handle): void
    {
        if (!in_array($handle, $this->trackedHandles, true)) {
            $this->trackedHandles[] = $handle;
        }
    }

    /**
     * Check whether any tracked handles were removed after the plugin enqueued them.
     *
     * @return void
     */
    private function check(): void
    {
        try {
            if (empty($this->trackedHandles)) {
                return;
            }

            if (false !== get_transient(self::TRANSIENT_CHECKED)) {
                return;
            }

            $removed = array_filter(
                $this->trackedHandles,
                fn(string $handle) => !wp_script_is($handle, 'enqueued')
            );

            set_transient(self::TRANSIENT_CHECKED, true, HOUR_IN_SECONDS);

            if (!empty($removed) && false === get_transient(self::TRANSIENT_METRIC_SENT)) {
                $this->datadog->sendEvent(
                    'mp_script_dequeued_detected',
                    'true',
                    implode(',', array_values($removed)),
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
        } catch (\Throwable $e) {
            // Never propagate errors — health checks must not interfere with checkout
        }
    }
}
