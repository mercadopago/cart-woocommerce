<?php

namespace MercadoPago\Woocommerce\Api\Http;

if (!defined('ABSPATH')) {
    exit;
}

interface HttpClientInterface
{
    public function request(
        string $method,
        string $path,
        array $headers = [],
        ?string $body = null
    ): HttpResponse;
}
