<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class Device
{
    /**
     * Verify if device is mobile
     *
     * @return bool
     */
    public static function isMobile(): bool
    {
        return wp_is_mobile();
    }

    /**
     * Get device product id
     *
     * @return string
     */
    public static function getDeviceProductId(): string
    {
        return self::isMobile() ? MP_PRODUCT_ID_MOBILE : MP_PRODUCT_ID_DESKTOP;
    }

    /**
     * Coarse device bucket derived from the buyer's User-Agent, for the
     * conversion-by-version-and-device alerting (PSW-4391). Kept low-cardinality
     * on purpose: os version is never part of the bucket.
     *
     * @return string One of: ios, android, desktop, other, unknown.
     */
    public static function getDeviceType(): string
    {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        if ($userAgent === '') {
            return 'unknown';
        }

        if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            return 'ios';
        }

        // iPadOS 13+ requests desktop-class pages with a Mac-like UA (no iPad token). The 'Mobile'
        // token, which real macOS Safari never sends, is the only server-side signal it is an iPad.
        // Limitation: iPads whose UA carries no 'Mobile' token are indistinguishable from a Mac here
        // and fall into 'desktop'. Acceptable: iOS Custom traffic is overwhelmingly iPhone.
        if (stripos($userAgent, 'Macintosh') !== false && stripos($userAgent, 'Mobile') !== false) {
            return 'ios';
        }

        if (stripos($userAgent, 'Android') !== false) {
            return 'android';
        }

        return self::isMobile() ? 'other' : 'desktop';
    }
}
