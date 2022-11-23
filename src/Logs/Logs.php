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
    private $file;

    /**
     * @var Remote
     */
    private $remote;

    /**
     * Logs constructor
     */
    public function __construct(File $file, Remote $remote)
    {
        $this->file   = $file;
        $this->remote = $remote;
    }
}
