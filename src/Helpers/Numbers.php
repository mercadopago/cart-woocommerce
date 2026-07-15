<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class Numbers
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
     * Number of decimal places used by the given currency (0 for zero-decimal
     * currencies like CLP/COP, 2 otherwise). Single source for this rule.
     */
    public static function getDecimalsForCurrency(string $currency): int
    {
        return in_array($currency, ['COP', 'CLP'], true) ? 0 : 2;
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
        if (self::getDecimalsForCurrency($currency) === 0) {
            return self::format($value * $ratio, 0);
        }

        return self::format($value * $ratio * 100) / 100;
    }

    /**
     * Formats an already-calculated amount as a string with the currency's decimal
     * places (e.g. "9.90" for BRL, "1234" for CLP) — required by AP v2, which
     * expects unit_price as a string. Uses a dot as decimal separator, no thousands.
     */
    public static function formatByCurrency(string $currency, float $amount): string
    {
        return number_format($amount, self::getDecimalsForCurrency($currency), '.', '');
    }

    /**
     * Returns the percentage of parcialValue on the sum with the paid value
     *
     * @param float $parcialValue
     * @param float $paidValue
     *
     * @return float
     */
    public static function getPercentageFromParcialValue(float $parcialValue, float $paidValue): float
    {
        $total = $paidValue + $parcialValue;
        $percentage = ($parcialValue / $total) * 100;

        return self::format($percentage);
    }
}
