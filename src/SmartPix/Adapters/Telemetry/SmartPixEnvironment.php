<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

use MercadoPago\Woocommerce\Configs\Store;

if (!defined('ABSPATH')) {
    exit;
}

final class SmartPixEnvironment
{
    public const HOMOLOGATION = 'homologation';

    public const PRODUCTION = 'production';

    public static function resolve(Store $store): string
    {
        if (defined('MP_SMART_PIX_ENV')) {
            $override = constant('MP_SMART_PIX_ENV');
            if ($override === self::HOMOLOGATION || $override === self::PRODUCTION) {
                return $override;
            }
        }

        // Core P&P uses production unless development explicitly overrides the environment.
        return self::PRODUCTION;
    }

    /**
     * Telemetry environment tag, separate from request routing: a store in test mode keeps calling
     * the production Core P&P scope, but its metrics must not pollute `prod` dashboards. Uses the
     * plugin-wide `homol`/`prod` convention (`MetricContext`) and the closed Smart Pix schema.
     */
    public static function metricEnvironment(Store $store): string
    {
        if (self::resolve($store) === self::HOMOLOGATION) {
            return 'homol';
        }
        try {
            return $store->isTestMode() ? 'homol' : 'prod';
        } catch (\Throwable $failure) {
            // An unreadable store mode keeps the routing-based tag.
            return 'prod';
        }
    }
}
