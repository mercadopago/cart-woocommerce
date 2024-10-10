<?php

namespace MercadoPago\Woocommerce\Helpers;

use const ENT_COMPAT;

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
            return sanitize_file_name(html_entity_decode(substr($text, 0, $limit), ENT_COMPAT)) . '...';
        }

        return sanitize_file_name(html_entity_decode($text, ENT_COMPAT));
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

    public function getStreetNumberInFullAddress(string $fullAddress, string $defaultNumber): string
    {
        $pattern = '/\b\d+[A-Za-z]*\b/';
        preg_match($pattern, $fullAddress, $matches);

        if (isset($matches[0])) {
            return $matches[0];
        }
        return $defaultNumber;
    }

    /**
     * Get list of html tags allowed to be used
     *
     * @return array
     */
    public function getAllowedHtmlTags(): array
    {
        return array(
            'br' => array(),
            'b'  => array(),
            'a'  => array(
                'href'   => array(),
                'target' => array(),
                'class'  => array(),
                'id'     => array()
            ),
            'span' => array(
                'id'      => array(),
                'class'   => array(),
                'onclick' => array()
            )
        );
    }
}
