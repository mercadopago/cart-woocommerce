<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Strings
{
    /**
     * Fix url ampersand
     * Fix to URL Problem: #038; replaces & and breaks the navigation
     *
     * @param string $link
     *
     * @return string
     */
    public static function fixUrlAmpersand(string $link): string
    {
        return str_replace('\/', '/', str_replace('&#038;', '&', $link));
    }

    /**
     * Performs partial or strict comparison of two strings
     *
     * @param string $expected
     * @param string $current
     * @param bool $allowPartialMatch
     *
     * @return bool
     */
    public static function compareStrings(string $expected, string $current, bool $allowPartialMatch): bool
    {
        if ($allowPartialMatch) {
            return str_contains($current, $expected);
        }

        return $expected === $current;
    }
}
