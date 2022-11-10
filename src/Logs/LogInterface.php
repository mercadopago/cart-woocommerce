<?php

namespace MercadoPago\Woocommerce\Logs;

if (!defined('ABSPATH')) {
    exit;
}

interface LogInterface
{
    /**
     * Get LogInterface Singleton instance
     *
     * @return LogInterface
     */
    public static function getInstance(): LogInterface;

    /**
     * Errors that do not require immediate action
     *
     * @param string $context
     * @param string $message
     * @param array  $info
     *
     * @return void
     */
    public function error(string $context, string $message, array $info = []): void;

    /**
     * Exceptional occurrences that are not errors
     *
     * @param string $context
     * @param string $message
     * @param array  $info
     *
     * @return void
     */
    public function warning(string $context, string $message, array $info = []): void;

    /**
     * Normal but significant events
     *
     * @param string $context
     * @param string $message
     * @param array  $info
     *
     * @return void
     */
    public function notice(string $context, string $message, array $info = []): void;

    /**
     * Interesting events
     *
     * @param string $context
     * @param string $message
     * @param array  $info
     *
     * @return void
     */
    public function info(string $context, string $message, array $info = []): void;

    /**
     * Detailed debug information
     *
     * @param string $context
     * @param string $message
     * @param array  $info
     *
     * @return void
     */
    public function debug(string $context, string $message, array $info = []): void;
}
