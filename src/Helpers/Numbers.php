<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Numbers
{
    /**
     * Format value
     *
     * @param float $value
     * @param int $decimals
     * @param string $separator
     *
     * @return float
     */
    public static function format(float $value, int $decimals = 2, string $separator = '.'): float
    {
        return (float) number_format($value, $decimals, $separator, '');
    }

    /**
     * makes the variable a safe float
     *
     * @param mixed $value
     *
     * @return float
     */
    public static function makesValueSafe($value): float
    {
        if (is_string($value) && strlen($value) > 0 && !is_numeric($value[0])) {
            $fixedValue = self::removeNonNumericPrefix($value);
            return floatval($fixedValue);
        }
        return floatval($value);
    }

    public static function removeNonNumericPrefix($str)
    {
        return preg_replace("/[^0-9,.]/", "", $str);
    }



    /**
     * Format value with currency symbol
     *
     * @param string $currencySymbol
     * @param float $value
     * @param int $decimals
     *
     * @return string
     */
    public static function formatWithCurrencySymbol(string $currencySymbol, float $value, int $decimals = 2): string
    {
        return $currencySymbol . ' ' . number_format($value, $decimals, ',', '');
    }

    /**
     * Number format value
     *
     * @param string $currency
     * @param float $value
     * @param float $ratio
     *
     * @return float
     */
    public static function calculateByCurrency(string $currency, float $value, float $ratio): float
    {
        if ($currency === 'COP' || $currency === 'CLP') {
            return self::format($value * $ratio, 0);
        }

        return self::format($value * $ratio * 100) / 100;
    }
}
