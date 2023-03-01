<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Interfaces\NotificationInterface;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationFactory
{
    public function createNotificationHandler(string $gateway, string $topic, string $type): NotificationInterface
    {
        global $mercadopago;

        if ('payment' === $topic && 'webhook' == $type) {
            return new WebhookNotification($gateway, $mercadopago->logs, $mercadopago->orderStatus, $mercadopago->requester, $mercadopago->seller, $mercadopago->store);
        }

        if ('merchant_order' === $topic && 'ipn' == $type) {
            return new IpnNotification($gateway, $mercadopago->logs, $mercadopago->orderStatus, $mercadopago->requester, $mercadopago->seller, $mercadopago->store);
        }

        return new CoreNotification($gateway, $mercadopago->logs, $mercadopago->orderStatus, $mercadopago->seller, $mercadopago->store);
    }
}
