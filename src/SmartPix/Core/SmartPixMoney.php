<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

if (!defined('ABSPATH')) {
    exit;
}

/** Exact two-decimal BRL arithmetic at the mutation boundary. */
final class SmartPixMoney
{
    /** @param mixed $value */
    public static function normalize($value): ?string
    {
        if (
            !is_string($value) || strlen($value) > 64
            || preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2})(0*))?$/D', $value, $match) !== 1
        ) {
            return null;
        }
        return $match[1] . '.' . str_pad($match[2] ?? '', 2, '0');
    }

    public static function compare(string $first, string $second): int
    {
        $first = ltrim(str_replace('.', '', $first), '0') ?: '0';
        $second = ltrim(str_replace('.', '', $second), '0') ?: '0';
        return strlen($first) === strlen($second) ? strcmp($first, $second) : strlen($first) <=> strlen($second);
    }

    /** Add canonical amounts without native integer overflow. */
    public static function add(string $first, string $second): string
    {
        $length = max(strlen($first), strlen($second)) - 1;
        $first = str_pad(str_replace('.', '', $first), $length, '0', STR_PAD_LEFT);
        $second = str_pad(str_replace('.', '', $second), $length, '0', STR_PAD_LEFT);
        $carry = 0;
        $result = '';
        for ($index = $length - 1; $index >= 0; $index--) {
            $sum = (int) $first[$index] + (int) $second[$index] + $carry;
            $result = (string) ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }
        $result = str_pad(ltrim(($carry ? '1' : '') . $result, '0'), 3, '0', STR_PAD_LEFT);
        return substr($result, 0, -2) . '.' . substr($result, -2);
    }

    /** Subtract already validated nonnegative canonical amounts; no native integer overflow. */
    public static function subtract(string $total, string $refunded): ?string
    {
        if (self::compare($total, $refunded) < 0) {
            return null;
        }
        $digits = str_replace('.', '', $total);
        $other = str_pad(str_replace('.', '', $refunded), strlen($digits), '0', STR_PAD_LEFT);
        $borrow = 0;
        $result = '';
        for ($index = strlen($digits) - 1; $index >= 0; $index--) {
            $digit = (int) $digits[$index] - (int) $other[$index] - $borrow;
            $borrow = $digit < 0 ? 1 : 0;
            $result = (string) ($digit < 0 ? $digit + 10 : $digit) . $result;
        }
        $result = str_pad(ltrim($result, '0'), 3, '0', STR_PAD_LEFT);
        return substr($result, 0, -2) . '.' . substr($result, -2);
    }
}
