<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Interfaces\NotificationInterface;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractNotification implements NotificationInterface
{
    /**
     * @var Logs`
     */
    public $logs;

    /**
     * AbstractNotification constructor
     */
    public function __construct(Logs $logs)
    {
        $this->logs = $logs;
    }
    
    public function handleReceivedNotification() {
        @ob_clean();
        $this->logs->file->info( __FUNCTION__, 'received _get content: ' . wp_json_encode( $_GET, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    }

    public function handleUnprocessableEntity() {
    }
}
