<?php

namespace MercadoPago\Woocommerce\Interfaces;

if (!defined('ABSPATH')) {
    exit;
}

interface LogInterface
{
    /**
     * Errors that do not require immediate action
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function error(string $message, string $source, $context = []): void;

    /**
     * Exceptional occurrences that are not errors
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function warning(string $message, string $source, $context = []): void;

    /**
     * Normal but significant events
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function notice(string $message, string $source, $context = []): void;

    /**
     * Interesting events
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function info(string $message, string $source, $context = []): void;

    /**
     * Detailed debug information
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function debug(string $message, string $source, $context = []): void;
}
