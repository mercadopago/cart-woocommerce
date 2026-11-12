<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

use InvalidArgumentException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exact nonnegative BRL decimals as canonical "units.cents" strings.
 * Arithmetic runs digit by digit, so no float rounding or integer overflow can change money.
 */
final class SmartPixDecimal
{
    public const MAX_FACTOR = 999999;

    private const MAX_LENGTH = 64;

    /**
     * Accept nonnegative integers and decimal strings whose digits beyond cents are zeros.
     * Floats and sub-cent values are rejected instead of rounded.
     *
     * @param mixed $value
     */
    public static function canonical($value): ?string
    {
        if (is_int($value) && $value >= 0) {
            return $value . '.00';
        }
        if (
            !is_string($value)
            || strlen($value) > self::MAX_LENGTH
            || preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2})0*)?$/D', $value, $parts) !== 1
        ) {
            return null;
        }

        return $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
    }

    /** @param mixed $value */
    public static function isCanonical($value): bool
    {
        return is_string($value)
            && strlen($value) <= self::MAX_LENGTH
            && preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1;
    }

    public static function add(string $left, string $right): string
    {
        $left   = self::cents($left);
        $right  = self::cents($right);
        $length = max(strlen($left), strlen($right));
        $left   = str_pad($left, $length, '0', STR_PAD_LEFT);
        $right  = str_pad($right, $length, '0', STR_PAD_LEFT);
        $carry  = 0;
        $result = '';
        for ($index = $length - 1; $index >= 0; $index--) {
            $digit  = (int) $left[$index] + (int) $right[$index] + $carry;
            $result = ($digit % 10) . $result;
            $carry  = intdiv($digit, 10);
        }

        return self::fromCents(($carry > 0 ? (string) $carry : '') . $result);
    }

    /** Multiply by a positive quantity bounded by MAX_FACTOR. */
    public static function multiply(string $value, int $factor): string
    {
        if ($factor < 1 || $factor > self::MAX_FACTOR) {
            throw new InvalidArgumentException('Invalid Smart Pix decimal factor.');
        }
        $cents  = self::cents($value);
        $carry  = 0;
        $result = '';
        for ($index = strlen($cents) - 1; $index >= 0; $index--) {
            $digit  = (int) $cents[$index] * $factor + $carry;
            $result = ($digit % 10) . $result;
            $carry  = intdiv($digit, 10);
        }

        return self::fromCents(($carry > 0 ? (string) $carry : '') . $result);
    }

    /** Return the exact quotient, or null when the cents are not evenly divisible. */
    public static function divideExact(string $value, int $divisor): ?string
    {
        if ($divisor < 1 || $divisor > self::MAX_FACTOR) {
            return null;
        }
        $remainder = 0;
        $result    = '';
        foreach (str_split(self::cents($value)) as $digit) {
            $remainder = $remainder * 10 + (int) $digit;
            $result   .= (string) intdiv($remainder, $divisor);
            $remainder %= $divisor;
        }

        return $remainder === 0 ? self::fromCents($result) : null;
    }

    private static function cents(string $value): string
    {
        return str_replace('.', '', $value);
    }

    private static function fromCents(string $cents): string
    {
        $cents = str_pad(ltrim($cents, '0'), 3, '0', STR_PAD_LEFT);

        return substr($cents, 0, -2) . '.' . substr($cents, -2);
    }
}
