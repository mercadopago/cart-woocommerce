<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Interfaces\NotificationInterface;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationFactory
{
    public function createNotificationHandler(string $gateway, array $data): NotificationInterface
    {
        global $mercadopago;

        $topic  = $data['topic'];
        $type   = $data['type'];
        $source = $data['source_news'];

        if ('payment' === $type && 'webhooks' == $source) {
            return new WebhookNotification($gateway, $mercadopago->logs, $mercadopago->orderStatus, $mercadopago->seller, $mercadopago->store, $mercadopago->requester, $data);
        }
        
        if ('merchant_order' === $topic && 'ipn' == $source) {
            return new IpnNotification($gateway, $mercadopago->logs, $mercadopago->orderStatus, $mercadopago->seller, $mercadopago->store, $mercadopago->requester, $data);
        }

        return new CoreNotification($gateway, $mercadopago->logs, $mercadopago->orderStatus, $mercadopago->seller, $mercadopago->store);
    }
}
