<?php

namespace MercadoPago\Woocommerce\Logs;

use MercadoPago\Woocommerce\Logs\Transports\File;
use MercadoPago\Woocommerce\Logs\Transports\Remote;

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
     * Logs constructor
     *
     * @param File $file
     * @param Remote $remote
     */
    public function __construct(File $file, Remote $remote)
    {
        $this->file   = $file;
        $this->remote = $remote;
    }
}
