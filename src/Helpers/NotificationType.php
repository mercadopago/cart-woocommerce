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
     * @param $ClassGateway
     *
     * @return string
     */
    public static function getNotificationType($ClassGateway): string
    {
        $types['WC_WooMercadoPago_Basic_Gateway']   = 'ipn';
        $types['WC_WooMercadoPago_Credits_Gateway'] = 'ipn';
        $types['WC_WooMercadoPago_Custom_Gateway']  = 'webhooks';
        $types['WC_WooMercadoPago_Pix_Gateway']     = 'webhooks';
        $types['WC_WooMercadoPago_Ticket_Gateway']  = 'webhooks';

        return $types[ $ClassGateway ];
    }
}
