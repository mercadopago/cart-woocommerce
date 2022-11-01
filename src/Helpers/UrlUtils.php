<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class UrlUtils
{
    public static function getSuffix(): string
    {
        return defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
    }

    public static function getPluginFileUrl($path, $extension): string
    {
        return sprintf(
            '%s%s%s%s',
            plugin_dir_url(__FILE__),
            '/../../../' . $path,
            self::getSuffix(),
            $extension
        );
    }

    public static function getCurrentPage(): string
    {
        return isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    }

    public static function getCurrentSection(): string
    {
        return isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
    }

    public static function getCurrentUrl(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
    }

    public static function validatePage($expected_page, $current_page = null, $allow_partial_match = false): bool
    {
        if (!$current_page) {
            $current_page = self::getCurrentPage();
        }

        return StringUtils::compareStrings($expected_page, $current_page, $allow_partial_match);
    }

    public static function validateSection($expected_section, $current_section = null, $allow_partial_match = true): bool
    {
        if (!$current_section) {
            $current_section = self::getCurrentSection();
        }

        return StringUtils::compareStrings($expected_section, $current_section, $allow_partial_match);
    }

    public static function validateUrl($expected_url, $current_url = null, $allow_partial_match = true): bool
    {
        if (!$current_url) {
            $current_url = self::getCurrentUrl();
        }

        return StringUtils::compareStrings($expected_url, $current_url, $allow_partial_match);
    }
}
