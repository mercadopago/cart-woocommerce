<?php

namespace MercadoPago\Woocommerce\Api\Core;

if (!defined('ABSPATH')) {
    exit;
}

class CoreApi
{
    public PaymentsClient $payments;

    public PreferencesClient $preferences;

    public NotificationsClient $notifications;

    public ExchangeClient $exchange;

    public FunnelClient $funnel;

    public function __construct(CoreApiClient $client)
    {
        $this->payments     = new PaymentsClient($client);
        $this->preferences  = new PreferencesClient($client);
        $this->notifications = new NotificationsClient($client);
        $this->exchange     = new ExchangeClient($client);
        $this->funnel       = new FunnelClient($client);
    }
}
