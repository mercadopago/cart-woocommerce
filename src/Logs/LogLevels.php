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
    const ERROR = 'error';

    /**
     * @const
     */
    const WARNING = 'warning';

    /**
     * @const
     */
    const NOTICE = 'notice';

    /**
     * @const
     */
    const INFO = 'info';

    /**
     * @const
     */
    const DEBUG = 'debug';
}
