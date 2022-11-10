<?php

namespace MercadoPago\Woocommerce\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class Logs
{
    /**
     * @var File
     */
    protected $file;

    /**
     * @var Remote
     */
    protected $remote;

    /**
     * @var Logs
     */
    private static $instance = null;

    /**
     * Logs constructor
     */
    private function __construct()
    {
        $this->file   = File::getInstance();
        $this->remote = Remote::getInstance();
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
}
