<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extracts the only facts a Smart Pix notification is trusted to carry: its identifiers.
 *
 * The provider payload field names are not yet known, so the mapping is isolated here.
 * Implementations receive an already authenticated and decoded JSON object, must return
 * the identifiers verbatim as strings without coercion, and must return null when either
 * one is missing or not a string. They must never read financial state from the payload,
 * never log or expose it, and never perform effects.
 */
interface SmartPixNotificationIdentifiers
{
    /**
     * Expected result: ['attempt_reference' => string, 'order_reference' => string], or null.
     * Core P&P also returns provider_order_reference for correlation with the separate ORD id.
     * The type stays open on purpose: callers must still validate the returned shape.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function extract(array $payload): ?array;
}
