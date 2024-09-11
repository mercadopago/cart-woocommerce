<?php

namespace MercadoPago\Woocommerce\Libraries\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class LogLevels
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const NOTICE = 'notice';

    public const INFO = 'info';

    public const DEBUG = 'debug';
}
