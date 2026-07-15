<?php

namespace MercadoPago\Woocommerce\Funnel;

class UpdateSellerFunnelBase extends \MercadoPago\PP\Sdk\Entity\Identification\UpdateSellerFunnelBase
{
    public string $plugin_version;

    public ?bool $is_subscription_enabled = null;
}
