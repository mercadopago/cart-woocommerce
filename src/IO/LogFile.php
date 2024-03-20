<?php

namespace MercadoPago\Woocommerce\IO;

if (!defined('ABSPATH')) {
    exit;
}

class LogFile
{
    /**
     * @var string
     */
    public $fileName;

    /**
     * @var string
     */
    public $fileDate;

    /**
     * @var string
     */
    public $fileFullName;
}
