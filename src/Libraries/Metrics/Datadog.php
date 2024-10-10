<?php

namespace MercadoPago\Woocommerce\Libraries\Metrics;

use Exception;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Libraries\Singleton\Singleton;

use function is_null;

if (!defined('ABSPATH')) {
    exit;
}

class Datadog extends Singleton
{
    private Sdk $sdk;

    public function __construct()
    {
        parent::__construct();
        $this->sdk = new Sdk();
    }

    public function sendEvent(string $event_type, $value, $message = null): void
    {
        try {
            $datadogEvent = $this->sdk->getDatadogEventInstance();

            if (! is_null($message)) {
                $datadogEvent->message = $message;
            }

            $datadogEvent->value = $value;
            $datadogEvent->plugin_version = MP_VERSION;
            $datadogEvent->platform->name = MP_PLATFORM_NAME;
            $datadogEvent->platform->version = $this->getWoocommerceVersion();
            $datadogEvent->platform->url = site_url();

            $datadogEvent->register(array("team" => "smb", "event_type" => $event_type));
        } catch (Exception $e) {
            return;
        }
    }

    private function getWoocommerceVersion(): string
    {
        return $GLOBALS['woocommerce']->version ?? "";
    }
}
