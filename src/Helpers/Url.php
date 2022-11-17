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
     * @param string      $expectedPage
     * @param string|null $currentPage
     * @param bool        $allowPartialMatch
     *
     * @return bool
     */
    public static function validatePage(string $expectedPage, string $currentPage = null, bool $allowPartialMatch = false): bool
    {
        if (!$currentPage) {
            $currentPage = self::getCurrentPage();
        }

        return Strings::compareStrings($expectedPage, $currentPage, $allowPartialMatch);
    }

    /**
     * Validate section
     *
     * @param string      $expectedSection
     * @param string|null $currentSection
     * @param bool        $allowPartialMatch
     *
     * @return bool
     */
    public static function validateSection(string $expectedSection, string $currentSection = null, bool $allowPartialMatch = true): bool
    {
        if (!$currentSection) {
            $currentSection = self::getCurrentSection();
        }

        return Strings::compareStrings($expectedSection, $currentSection, $allowPartialMatch);
    }

    /**
     * Validate url
     *
     * @param string      $expectedUrl
     * @param string|null $currentUrl
     * @param bool        $allowPartialMatch
     *
     * @return bool
     */
    public static function validateUrl(string $expectedUrl, string $currentUrl = null, bool $allowPartialMatch = true): bool
    {
        if (!$currentUrl) {
            $currentUrl = self::getCurrentUrl();
        }

        return Strings::compareStrings($expectedUrl, $currentUrl, $allowPartialMatch);
    }
}
