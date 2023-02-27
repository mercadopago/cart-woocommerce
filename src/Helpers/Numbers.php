<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Numbers
{
    /**
     * Number format value
     *
     * @param float $number
     * @param int $decimals
     *
     * @return float
     */
    public static function format(float $number, int $decimals = 2): float
    {
        return (float) number_format($number, $decimals, '.', '');
    }

    /**
     * Number format value
     *
     * @param $currency
     * @param $amount
     * @param $ratio
     *
     * @return float
     */
    public static function calculateByCurrency($currency, $amount, $ratio): float
    {
        if ('COP' === $currency || 'CLP' === $currency) {
            return Numbers::format($amount * $ratio, 0);
        }
        return Numbers::format($amount * $ratio * 100) / 100;
    }
}
