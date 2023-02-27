<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Date
{
    /**
     * Get date/time in GMT/CUT format
     *
     * @param string $value
     *
     * @return string
     */
    public static function format(string $value): string
    {
        if (!$value) {
            return gmdate('Y-m-d\TH:i:s.000O', strtotime('+' . $value));
        }

        return '';
    }
}
