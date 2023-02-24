<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Url
{
    /**
     * @var Strings
     */
    private $strings;

    /**
     * Url constructor
     */
    public function __construct(Strings $strings)
    {
        $this->strings = $strings;
    }

    /**
     * Get suffix
     *
     * @return string
     */
    public function getSuffix(): string
    {
        return defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
    }

    /**
     * Get plugin file url
     *
     * @param string $path
     * @param string $extension
     *
     * @return string
     */
    public function getPluginFileUrl(string $path, string $extension, bool $ignoreSuffix = false): string
    {
        return sprintf(
            '%s%s%s%s',
            plugin_dir_url(__FILE__),
            '/../../../' . $path,
            $ignoreSuffix ? '' : $this->getSuffix(),
            $extension
        );
    }

    /**
     * Get current page
     *
     * @return string
     */
    public function getCurrentPage(): string
    {
        return isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    }

    /**
     * Get current section
     *
     * @return string
     */
    public function getCurrentSection(): string
    {
        return isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
    }

    /**
     * Get current url
     *
     * @return string
     */
    public function getCurrentUrl(): string
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
    public function validatePage(string $expectedPage, string $currentPage = null, bool $allowPartialMatch = false): bool
    {
        if (!$currentPage) {
            $currentPage = $this->getCurrentPage();
        }

        return $this->strings->compareStrings($expectedPage, $currentPage, $allowPartialMatch);
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
    public function validateSection(string $expectedSection, string $currentSection = null, bool $allowPartialMatch = true): bool
    {
        if (!$currentSection) {
            $currentSection = $this->getCurrentSection();
        }

        return $this->strings->compareStrings($expectedSection, $currentSection, $allowPartialMatch);
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
    public function validateUrl(string $expectedUrl, string $currentUrl = null, bool $allowPartialMatch = true): bool
    {
        if (!$currentUrl) {
            $currentUrl = $this->getCurrentUrl();
        }

        return $this->strings->compareStrings($expectedUrl, $currentUrl, $allowPartialMatch);
    }
}
