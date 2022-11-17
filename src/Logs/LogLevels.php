<?php

namespace MercadoPago\Woocommerce\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class LogLevels
{
    /**
     * @const
     */
    public const ERROR = 'error';

    /**
     * @const
     */
    public const WARNING = 'warning';

    /**
     * @const
     */
    public const NOTICE = 'notice';

    /**
     * @const
     */
    public const INFO = 'info';

    /**
     * @const
     */
    public const DEBUG = 'debug';
}
