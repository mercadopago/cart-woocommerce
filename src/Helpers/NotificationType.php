<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class NotificationType
{
    /**
     * Get Status Type
     *
     * @param $classGateway
     *
     * @return string
     */
    public static function getNotificationType($classGateway): string
    {
        $types = [
            'WC_WooMercadoPago_Basic_Gateway'   => 'ipn',
            'WC_WooMercadoPago_Credits_Gateway' => 'ipn',
            'WC_WooMercadoPago_Custom_Gateway'  => 'webhooks',
            'WC_WooMercadoPago_Pix_Gateway'     => 'webhooks',
            'WC_WooMercadoPago_Ticket_Gateway'  => 'webhooks',
            'WC_WooMercadoPago_Pse_Gateway'  => 'webhooks',
            'WC_WooMercadoPago_Yape_Gateway'  => 'webhooks'
        ];

        return $types[$classGateway];
    }
}
