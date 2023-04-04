<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Interfaces\NotificationInterface;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationFactory
{
    public function createNotificationHandler(AbstractGateway $gateway, array $data): NotificationInterface
    {
        global $mercadopago;

        $topic  = $data['topic'];
        $type   = $data['type'];
        $source = $data['source_news'];

        if ($type === 'payment' && $source === 'webhooks') {
            return new WebhookNotification(
                $gateway,
                $mercadopago->logs,
                $mercadopago->orderStatus,
                $mercadopago->seller,
                $mercadopago->store,
                $mercadopago->requester
            );
        }

        if ($topic === 'merchant_order' && $source === 'ipn') {
            return new IpnNotification(
                $gateway,
                $mercadopago->logs,
                $mercadopago->orderStatus,
                $mercadopago->seller,
                $mercadopago->store,
                $mercadopago->requester
            );
        }

        return new CoreNotification(
            $gateway,
            $mercadopago->logs,
            $mercadopago->orderStatus,
            $mercadopago->seller,
            $mercadopago->store
        );
    }
}
