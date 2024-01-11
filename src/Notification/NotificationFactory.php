<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Interfaces\NotificationInterface;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationFactory
{
    /**
     * Create a notification handler based on $data
     *
     * @param array $data data from $_GET
     *
     * @return void
     */
    public function createNotificationHandler(MercadoPagoGatewayInterface $gateway, array $data): NotificationInterface
    {
        global $mercadopago;

        $topic  = isset($data['topic']) ? $data['topic'] : '';
        $type   = isset($data['type']) ? $data['type'] : '';
        $source = isset($data['source_news']) ? $data['source_news'] : '';

        if ($type === 'payment' && $source === 'webhooks') {
            return new WebhookNotification(
                $gateway,
                $mercadopago->logs,
                $mercadopago->orderStatus,
                $mercadopago->sellerConfig,
                $mercadopago->storeConfig,
                $mercadopago->helpers->requester
            );
        }

        if ($topic === 'merchant_order' && $source === 'ipn') {
            return new IpnNotification(
                $gateway,
                $mercadopago->logs,
                $mercadopago->orderStatus,
                $mercadopago->sellerConfig,
                $mercadopago->storeConfig,
                $mercadopago->helpers->requester
            );
        }

        return new CoreNotification(
            $gateway,
            $mercadopago->logs,
            $mercadopago->orderStatus,
            $mercadopago->sellerConfig,
            $mercadopago->storeConfig
        );
    }
}
