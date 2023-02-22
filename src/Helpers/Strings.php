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
    public function fixUrlAmpersand(string $link): string
    {
        return esc_url(str_replace('\/', '/', str_replace('&#038;', '&', $link)));
    }

    /**
     * Performs partial or strict comparison of two strings
     *
     * @param string $expected
     * @param string $current
     * @param bool   $allowPartialMatch
     *
     * @return bool
     */
    public function compareStrings(string $expected, string $current, bool $allowPartialMatch): bool
    {
        if ($allowPartialMatch) {
            return strpos($current, $expected) !== false;
        }

        return $expected === $current;
    }
}
