<?php

namespace MercadoPago\Woocommerce\Funnel;

use MercadoPago\Woocommerce\Api\Core\FunnelClient;
use MercadoPago\Woocommerce\Configs\Store;

if (!defined('ABSPATH')) {
    exit;
}

class Uninstall
{
    private Store $store;

    private FunnelClient $client;

    public function __construct(Store $store, FunnelClient $client)
    {
        $this->store = $store;
        $this->client = $client;
    }

    public function send(): bool
    {
        $installationId = $this->store->getInstallationId();
        $installationKey = $this->store->getInstallationKey();

        if ($installationId === '' || $installationKey === '') {
            return false;
        }

        $this->client->update(['id' => $installationId, 'is_deleted' => true], $installationKey);

        return true;
    }
}
