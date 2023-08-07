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
        return str_replace('\\/', '/', str_replace('&#038;', '&', $link));
    }

    /**
     * Sanitizes a text, replacing complex characters and symbols, and truncates it to 230 characters
     *
     * @param string $text
     * @param int $limit
     *
     * @return string
     */
    public function sanitizeAndTruncateText(string $text, int $limit = 80): string
    {
        if (strlen($text) > $limit) {
            return sanitize_file_name(html_entity_decode(substr($text, 0, $limit))) . '...';
        }

        return sanitize_file_name(html_entity_decode($text));
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
