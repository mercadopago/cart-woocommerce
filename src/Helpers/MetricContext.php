<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class MetricContext
{
    public static function buildApiErrorDetails(string $apiRoute, ?object $mercadopago = null): array
    {
        $details = [
            'team'      => 'big',
            'api_route' => strtok($apiRoute, '?'),
        ];

        $mp = $mercadopago ?? ($GLOBALS['mercadopago'] ?? null);
        if ($mp) {
            $details['site_id']     = $mp->sellerConfig->getSiteId();
            $details['environment'] = $mp->storeConfig->isTestMode() ? 'homol' : 'prod';
            $details['cust_id']     = $mp->sellerConfig->getCustIdFromAT();
        }

        return $details;
    }
}
