<?php

namespace MercadoPago\Woocommerce\Funnel;

class UpdateSellerFunnelBase extends \MercadoPago\PP\Sdk\Entity\Identification\UpdateSellerFunnelBase
{
    // Declared here because the SDK entity does not have it. Only add a property to
    // this subclass while the SDK lacks it, and drop it once the SDK declares it:
    // a typed child property over an untyped parent one is a fatal error in PHP.
    // See docs/agent/traps.md.
    public string $plugin_version;
}
