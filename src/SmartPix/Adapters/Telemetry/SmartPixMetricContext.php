<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

use WC_Order;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/** Diagnostic context only: never authorizes a payment, lookup, retry or state transition. */
final class SmartPixMetricContext
{
    public const SDK_FIELD = 'mp-smart-pix-sdk-instance-id';
    private const SDK_META = '_mp_smart_pix_sdk_instance_id';

    private ?string $sdkInstanceId = null;
    private ?string $requestId = null;
    private ?string $apiRoute = null;

    /** Only the explicit SDK diagnostic field is accepted; session/authentication tokens are not IDs. */
    public static function identifier($value): ?string
    {
        return is_string($value)
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/D', $value) === 1
            && preg_match('/\A(?:APP_USR-|TEST-|STAUTH|Bearer|Unknown\z|not_available\z)/i', $value) !== 1
            ? $value : null;
    }

    public static function forOrder(WC_Order $order): array
    {
        try {
            $id = self::identifier($order->get_meta(self::SDK_META, true));
            return $id === null ? [] : ['sdk_instance_id' => $id];
        } catch (Throwable $failure) {
            return [];
        }
    }

    /** Preserve the instance that originated this attempt, including checkout reloads/retries. */
    public static function capture(WC_Order $order, $value): void
    {
        try {
            $id = self::identifier($value);
            if ($id !== null && self::forOrder($order) === []) {
                $order->update_meta_data(self::SDK_META, $id);
                $order->save_meta_data();
            }
        } catch (Throwable $failure) {
            // Missing diagnostics must never fail a checkout or persist payment effects.
        }
    }

    public function reset(): void
    {
        $this->sdkInstanceId = null;
        $this->observeRequest(null, null);
    }

    public function observeOrder(WC_Order $order): void
    {
        $this->sdkInstanceId = self::forOrder($order)['sdk_instance_id'] ?? null;
    }

    public function observeSdkInstance($value): void
    {
        $this->sdkInstanceId = self::identifier($value);
    }

    public function observeRequest(?string $requestId, ?string $apiRoute): void
    {
        $this->requestId = self::identifier($requestId);
        // Routes are supplied as fixed templates by the HTTP adapter, never raw URLs/IDs.
        $this->apiRoute = in_array($apiRoute, [
            'POST /plugins-platforms/v1/orders', 'GET /plugins-platforms/v1/orders/{id}',
            'POST /homol/plugins-platforms/v1/orders', 'GET /homol/plugins-platforms/v1/orders/{id}',
            'GET /plugins-platforms/v1/notification/order/{id}',
            'POST /plugins-platforms/v1/orders/{id}/refund', 'POST /plugins-platforms/v1/orders/{id}/cancel',
            'POST /homol/plugins-platforms/v1/orders/{id}/refund', 'POST /homol/plugins-platforms/v1/orders/{id}/cancel',
        ], true) ? $apiRoute : null;
    }

    public function details(): array
    {
        return array_filter([
            'sdk_instance_id' => $this->sdkInstanceId,
            'request_id' => $this->requestId,
            'api_route' => $this->apiRoute,
        ], static fn($value): bool => $value !== null);
    }
}
