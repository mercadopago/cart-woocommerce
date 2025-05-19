<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

if (!defined('ABSPATH')) {
    exit;
}

class ThemeMetadata
{
    public ?string $theme_name = null;

    public ?string $theme_version = null;
}
