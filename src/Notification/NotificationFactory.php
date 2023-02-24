<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Interfaces\NotificationInterface;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationFactory
{
    
    public function createNotificationHandler(string $topic, string $type, Logs $logs): NotificationInterface {
        if ('payment' === $topic && 'webhook' == $type) {
            return new WebhookNotification($logs);
        }

        if ('merchant_order' === $topic && 'ipn' == $type) {
            return new IpnNotification($logs);
        }

        return new CoreNotification($logs);
    }

}
