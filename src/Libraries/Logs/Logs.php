<?php

namespace MercadoPago\Woocommerce\Libraries\Logs;

use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;

if (!defined('ABSPATH')) {
    exit;
}

class Logs
{
    public File $file;

    public Remote $remote;

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
