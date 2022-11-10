<?php

namespace MercadoPago\Woocommerce\Logs;

use MercadoPago\Woocommerce\Helpers\Requester;

if (!defined('ABSPATH')) {
    exit;
}

class Remote implements LogInterface
{
    /**
     * @var bool
     */
    private $debugMode;

    /**
     * @var Requester
     */
    private $requester;

    /**
     * Remote Logs constructor
     */
    public function __construct($debugMode)
    {
        $this->debugMode = $debugMode;
        $this->requester = Requester::getInstance();
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
        $this->save('error', $message, $source, $context);
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
        $this->save('warning', $message, $source, $context);
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
        $this->save('notice', $message, $source, $context);
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
        $this->save('info', $message, $source, $context);
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
            $this->save('debug', $message, $source, $context);
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
            $headers = ['Content-Type: application/json'];
            $uri     = '/v1/plugins/melidata/errors';
            $body    = [
                'name'         => '',
                'message'      => '',
                'target'       => '',
                'plugin'       => [
                    'version'  => '',
                ],
                'platform'     => [
                    'name'     => '',
                    'uri'      => '',
                    'version'  => '',
                    'location' => '',
                ],
            ];

            $this->requester->post($uri, $headers, $body);
        } catch (\Exception $e) {
            error_log($e);
        }
    }
}
