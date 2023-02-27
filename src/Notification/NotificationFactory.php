<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Interfaces\NotificationInterface;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationFactory
{
    public function createNotificationHandler(string $topic, string $type): NotificationInterface
    {
        global $mercadopago;

        if ('payment' === $topic && 'webhook' == $type) {
            return new WebhookNotification($mercadopago->logs, $mercadopago->requester);
        }

        if ('merchant_order' === $topic && 'ipn' == $type) {
            return new IpnNotification($mercadopago->logs, $mercadopago->requester);
        }

        return new CoreNotification($mercadopago->logs);
    }
}
