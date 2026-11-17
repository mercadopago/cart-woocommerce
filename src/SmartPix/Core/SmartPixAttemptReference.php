<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The attempt reference is the store identificator followed by the WooCommerce order ID, the same
 * external_reference the other payment methods send. Its format is merchant-defined, so it only
 * needs to exist; provider identifiers keep their own strict format.
 */
final class SmartPixAttemptReference
{
    /** @param mixed $reference */
    public static function isValid($reference): bool
    {
        return is_string($reference) && $reference !== '';
    }
}
