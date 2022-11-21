<?php

namespace MercadoPago\Woocommerce\Logs\Transports;

use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Logs\LogInterface;
use MercadoPago\Woocommerce\Logs\LogLevels;

if (!defined('ABSPATH')) {
    exit;
}

class Remote implements LogInterface
{
    /**
     * @const
     */
    private const METRIC_NAME_PREFIX = 'MP_WOO_PE_LOG_';

    /**
     * @var bool
     */
    private $debugMode;

    /**
     * @var LogLevels
     */
    private $logLevels;

    /**
     * @var Requester
     */
    private $requester;

    /**
     * Remote Logs constructor
     */
    public function __construct($debugMode, LogLevels $logLevels, Requester $requester)
    {
        $this->debugMode = $debugMode;
        $this->logLevels = $logLevels;
        $this->requester = $requester;
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
        $this->save($this->logLevels::ERROR, $message, $source, $context);
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
        $this->save($this->logLevels::WARNING, $message, $source, $context);
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
        $this->save($this->logLevels::NOTICE, $message, $source, $context);
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
        $this->save($this->logLevels::INFO, $message, $source, $context);
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
            $this->save($this->logLevels::DEBUG, $message, $source, $context);
        }
    }

    /**
     * Save logs by sending to API
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

        try {
            global $woocommerce;

            $level   = strtoupper($level);
            $headers = ['Content-Type: application/json'];
            $uri     = '/v1/plugins/melidata/errors';
            $body    = [
                'name'         => self::METRIC_NAME_PREFIX . $level,
                'message'      => '[' . $level . '] ' . $message . ' - Context: ' . json_encode($context),
                'target'       => $source,
                'plugin'       => [
                    'version'  => MP_VERSION,
                ],
                'platform'     => [
                    'name'     => MP_PLATFORM_NAME,
                    'uri'      => $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                    'version'  => $woocommerce->version,
                    'location' => '/backend',
                ],
            ];

            $this->requester->post($uri, $headers, $body);
        } catch (\Exception $e) {
            return;
        }
    }
}
