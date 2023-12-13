<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Date
{
    /**
     * Get now date
     *
     * @param string $format
     *
     * @return string
     */
    public static function getNowDate(string $format): string
    {
        return gmdate($format);
    }

    /**
     * Sum now() with $value in GMT/CUT format
     *
     * @param string $value
     *
     * @return string
     */
    public static function sumToNowDate(string $value): string
    {
        if ($value) {
            return gmdate('Y-m-d\TH:i:s.000O', strtotime('+' . $value));
        }

        return gmdate('Y-m-d\TH:i:s.000O');
    }

    /**
     * Format a GMT/UTC date/time
     *
     * @param string $timestamp
     *
     * @return string
     */
    public static function formatGmDate(string $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s.vP', strtotime($timestamp));
    }
}
