<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Url
{
    /**
     * Get suffix
     *
     * @return string
     */
    public static function getSuffix(): string
    {
        // TODO: uncomment
        // return defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        return '';
    }

    /**
     * Get plugin file url
     *
     * @param string $path
     * @param string $extension
     *
     * @return string
     */
    public static function getPluginFileUrl(string $path, string $extension): string
    {
        return sprintf(
            '%s%s%s%s',
            plugin_dir_url(__FILE__),
            '/../../../' . $path,
            self::getSuffix(),
            $extension
        );
    }

    /**
     * Get current page
     *
     * @return string
     */
    public static function getCurrentPage(): string
    {
        return isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    }

    /**
     * Get current section
     *
     * @return string
     */
    public static function getCurrentSection(): string
    {
        return isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
    }

    /**
     * Get current url
     *
     * @return string
     */
    public static function getCurrentUrl(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
    }

    /**
     * Validate page
     *
     * @param string $expected_page
     * @param string|null $current_page
     * @param bool $allow_partial_match
     *
     * @return bool
     */
    public static function validatePage(string $expected_page, string $current_page = null, bool $allow_partial_match = false): bool
    {
        if (!$current_page) {
            $current_page = self::getCurrentPage();
        }

        return StringUtils::compareStrings($expected_page, $current_page, $allow_partial_match);
    }

    /**
     * Validate section
     *
     * @param string $expected_section
     * @param string|null $current_section
     * @param bool $allow_partial_match
     *
     * @return bool
     */
    public static function validateSection(string $expected_section, string $current_section = null, bool $allow_partial_match = true): bool
    {
        if (!$current_section) {
            $current_section = self::getCurrentSection();
        }

        return StringUtils::compareStrings($expected_section, $current_section, $allow_partial_match);
    }

    /**
     * Validate url
     *
     * @param string $expected_url
     * @param string|null $current_url
     * @param bool $allow_partial_match
     *
     * @return bool
     */
    public static function validateUrl(string $expected_url, string $current_url = null, bool $allow_partial_match = true): bool
    {
        if (!$current_url) {
            $current_url = self::getCurrentUrl();
        }

        return StringUtils::compareStrings($expected_url, $current_url, $allow_partial_match);
    }
}
