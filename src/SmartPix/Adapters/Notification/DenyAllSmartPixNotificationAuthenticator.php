<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Notification;

use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationAuthenticator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fail-closed default until the provider authentication scheme is defined.
 * Rejects every request without inspecting it, logging or emitting telemetry.
 */
final class DenyAllSmartPixNotificationAuthenticator implements SmartPixNotificationAuthenticator
{
    /** @param array<string, string> $headers */
    public function authenticate(array $headers, string $rawBody): bool
    {
        return false;
    }
}
