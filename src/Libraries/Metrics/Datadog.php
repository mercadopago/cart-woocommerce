<?php

namespace MercadoPago\Woocommerce\Libraries\Metrics;

use Exception;
use MercadoPago\Woocommerce\Libraries\Singleton\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

class Datadog extends Singleton
{
    private const BASEURL = 'https://api.mercadopago.com';
    private const ENDPOINT = '/ppcore/prod/monitor/v1/event/datadog';
    private const REQUEST_TIMEOUT_SECONDS = 3;

    public function sendEvent(string $event_type, $value, $message = null, $paymentMethod = null, $details = []): void
    {
        try {
            $team = $details['team'] ?? 'smb';

            $payload = [
                'value'          => $value,
                'plugin_version' => MP_VERSION,
                'platform'       => [
                    'name'    => MP_PLATFORM_NAME,
                    'version' => $this->getWoocommerceVersion(),
                    'url'     => site_url(),
                ],
            ];

            if ($message !== null) {
                $payload['message'] = $message;
            }

            $eventDetails = [];

            if ($paymentMethod !== null) {
                $eventDetails['payment_method'] = $paymentMethod;
            }

            if (!empty($details)) {
                $eventDetails = array_merge($eventDetails, $details);
            }

            if (!empty($eventDetails)) {
                $payload['details'] = $eventDetails;
            }

            $url = self::BASEURL . self::ENDPOINT . '/' . $team . '/' . $event_type;

            wp_remote_post($url, [
                'blocking' => false,
                'timeout'  => self::REQUEST_TIMEOUT_SECONDS,
                'body'     => wp_json_encode($payload),
                'headers'  => ['Content-Type' => 'application/json'],
            ]);
        } catch (Exception $e) {
            return;
        }
    }

    private function getWoocommerceVersion(): string
    {
        return $GLOBALS['woocommerce']->version ?? "";
    }
}
