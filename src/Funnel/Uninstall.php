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
        $identity = $this->store->getInstallationCredentials();

        if ($identity['id'] === '' || $identity['key'] === '') {
            return false;
        }

        $this->client->update(['id' => $identity['id'], 'is_deleted' => true], $identity['key']);

        return true;
    }
}
