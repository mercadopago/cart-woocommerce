<?php

namespace MercadoPago\Woocommerce\Api\Core;

if (!defined('ABSPATH')) {
    exit;
}

class CoreRequestContext
{
    private ?string $accessToken;

    private ?string $platformId;

    private ?string $productId;

    private ?string $integratorId;

    public function __construct(
        ?string $accessToken = null,
        ?string $platformId = null,
        ?string $productId = null,
        ?string $integratorId = null
    ) {
        $this->accessToken = $accessToken;
        $this->platformId = $platformId;
        $this->productId = $productId;
        $this->integratorId = $integratorId;
    }

    public function toHeaders(): array
    {
        $headers = [];

        if ($this->accessToken !== null && $this->accessToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        if ($this->platformId !== null && $this->platformId !== '') {
            $headers['x-platform-id'] = $this->platformId;
        }

        if ($this->productId !== null && $this->productId !== '') {
            $headers['x-product-id'] = $this->productId;
        }

        if ($this->integratorId !== null && $this->integratorId !== '') {
            $headers['x-integrator-id'] = $this->integratorId;
        }

        return $headers;
    }
}
