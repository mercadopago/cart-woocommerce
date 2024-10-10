<?php

namespace MercadoPago\Woocommerce\Logs\Transports;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Interfaces\LogInterface;
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
     * @var Store
     */
    private $store;

    /**
     * @var Requester
     */
    private $requester;

    /**
     * Remote Logs constructor
     *
     * @param Store $store
     * @param Requester $requester
     */
    public function __construct(Store $store, Requester $requester)
    {
        $this->store     = $store;
        $this->debugMode = $this->store->getDebugMode() === 'yes';
        $this->requester = $requester;
    }

    /**
     * Errors that do not require immediate action
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function error(string $message, string $source, $context = []): void
    {
        $this->save(LogLevels::ERROR, $message, $source, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function warning(string $message, string $source, $context = []): void
    {
        $this->save(LogLevels::WARNING, $message, $source, $context);
    }

    /**
     * Normal but significant events
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function notice(string $message, string $source, $context = []): void
    {
        $this->save(LogLevels::NOTICE, $message, $source, $context);
    }

    /**
     * Interesting events
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function info(string $message, string $source, $context = []): void
    {
        $this->save(LogLevels::INFO, $message, $source, $context);
    }

    /**
     * Detailed debug information
     *
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    public function debug(string $message, string $source, $context = []): void
    {
        if (WP_DEBUG) {
            $this->save(LogLevels::DEBUG, $message, $source, $context);
        }
    }

    /**
     * Save logs by sending to API
     *
     * @param string $level
     * @param string $message
     * @param string $source
     * @param mixed $context
     *
     * @return void
     */
    private function save(string $level, string $message, string $source, $context = []): void
    {
        if (!$this->debugMode && $level != LogLevels::ERROR) {
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
