<?php

namespace MercadoPago\Woocommerce\Logs;

use MercadoPago\Woocommerce\Configs\Store;

if (!defined('ABSPATH')) {
    exit;
}

class Logs
{
    /**
     * @var File
     */
    public $file;

    /**
     * @var Remote
     */
    public $remote;

    /**
     * @var Store
     */
    protected $store;

    /**
     * @var Logs
     */
    private static $instance = null;

    /**
     * Logs constructor
     */
    private function __construct()
    {
        $this->store = Store::getInstance();
        $debugMode   = $this->getDebugMode();

        $this->file   = new File($debugMode);
        $this->remote = new Remote($debugMode);
    }

    /**
     * Get Logs instance
     *
     * @return Logs
     */
    public static function getInstance(): Logs
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get plugin debug mode option
     *
     * @return bool
     */
    private function getDebugMode(): bool
    {
        return $this->store->getDebugMode() === 'yes';
    }
}
