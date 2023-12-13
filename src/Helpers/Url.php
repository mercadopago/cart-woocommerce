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
     *
     * @param Strings $strings
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
     * @param bool $ignoreSuffix
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
     * Get plugin file path
     *
     * @param string $path
     * @param string $extension
     * @param bool $ignoreSuffix
     *
     * @return string
     */
    public function getPluginFilePath(string $path, string $extension, bool $ignoreSuffix = false): string
    {
        return sprintf(
            '%s%s%s%s',
            untrailingslashit(plugin_dir_path(__FILE__)),
            "/../../$path",
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
     * Get base url of  current url
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return home_url();
    }

    /**
     * Get server address
     *
     * @return string
     */
    public function getServerAddress(): string
    {
        return isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field($_SERVER['SERVER_ADDR']) : '';
    }

    /**
     * Set wp query var
     *
     * @param string $key
     * @param string $value
     * @param string $url
     *
     * @return string
     */
    public function setQueryVar(string $key, string $value, string $url): string
    {
        return add_query_arg($key, $value, $url);
    }

    /**
     * Get wp query var
     *
     * @param string $queryVar
     * @param mixed $default
     *
     * @return string
     */
    public function getQueryVar(string $queryVar, $default = ''): string
    {
        return get_query_var($queryVar, $default);
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

    /**
     * Validate wp query var
     *
     * @param string $expectedQueryVar
     *
     * @return bool
     */
    public function validateQueryVar(string $expectedQueryVar): bool
    {
        return (bool) $this->getQueryVar($expectedQueryVar);
    }

    /**
     * Validate $_GET var
     *
     * @param string $expectedVar
     *
     * @return bool
     */
    public function validateGetVar(string $expectedVar): bool
    {
        return isset($_GET[$expectedVar]);
    }
}
