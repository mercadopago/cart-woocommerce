<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Decides whether a Smart Pix notification request is authentic.
 *
 * Inputs are server-observed request facts only: header names normalized to lowercase
 * mapped to their raw string values, and the exact raw body bytes as received, so a
 * signature scheme can verify the bytes before any decoding happens.
 *
 * The concrete provider scheme (signature header, secret source, replay window) is not
 * yet known. Implementations must compare secrets and signatures in constant time
 * (e.g. hash_equals), must fail closed on any missing or malformed input, and must
 * never log or expose headers, secrets or the body.
 */
interface SmartPixNotificationAuthenticator
{
    /** @param array<string, string> $headers Lowercase header name => raw value. */
    public function authenticate(array $headers, string $rawBody): bool;
}
