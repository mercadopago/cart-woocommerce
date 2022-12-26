<?php

namespace MercadoPago\Woocommerce\Logs\Transports;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Logs\LogInterface;
use MercadoPago\Woocommerce\Logs\LogLevels;

if (!defined('ABSPATH')) {
    exit;
}

class File implements LogInterface
{
    /**
     * @var \WC_Logger
     */
    private $logger;

    /**
     * @var bool
     */
    private $debugMode;

    /**
     * @var Store
     */
    private $store;

    /**
     * File Logs constructor
     */
    public function __construct(Store $store)
    {
        $this->logger    = wc_get_logger();
        $this->store     = $store;
        $this->debugMode = $this->store->getDebugMode() === 'yes';
    }

    /**
     * Errors that do not require immediate action
     *
     * @param string               $message
     * @param string               $source
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function error(string $message, string $source, array $context = []): void
    {
        $this->save(LogLevels::ERROR, $message, $source, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     *
     * @param string               $message
     * @param string               $source
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function warning(string $message, string $source, array $context = []): void
    {
        $this->save(LogLevels::WARNING, $message, $source, $context);
    }

    /**
     * Normal but significant events
     *
     * @param string               $message
     * @param string               $source
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function notice(string $message, string $source, array $context = []): void
    {
        $this->save(LogLevels::NOTICE, $message, $source, $context);
    }

    /**
     * Interesting events
     *
     * @param string               $message
     * @param string               $source
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function info(string $message, string $source, array $context = []): void
    {
        $this->save(LogLevels::INFO, $message, $source, $context);
    }

    /**
     * Detailed debug information
     *
     * @param string               $message
     * @param string               $source
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function debug(string $message, string $source, array $context = []): void
    {
        if (WP_DEBUG) {
            $this->save(LogLevels::DEBUG, $message, $source, $context);
        }
    }

    /**
     * Save logs with Woocommerce logger
     *
     * @param string               $level
     * @param string               $message
     * @param string               $source
     * @param array<string, mixed> $context
     *
     * @return void
     */
    private function save(string $level, string $message, string $source, array $context = []): void
    {
        if (!$this->debugMode) {
            return;
        }

        $this->logger->{$level}($message . ' - Context: ' . json_encode($context), ['source' => $source]);
    }
}