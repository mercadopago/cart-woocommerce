<?php

namespace MercadoPago\Woocommerce\Api\Core;

use MercadoPago\Woocommerce\Api\Core\Models\FunnelRegistration;

if (!defined('ABSPATH')) {
    exit;
}

class FunnelClient
{
    private const CREATE_PATH = '/v1/eplatforms/core/identification/seller-configuration/start-integration';

    private const UPDATE_PATH = '/v1/eplatforms/core/identification/seller-configuration/update-integration';

    private CoreApiClient $apiClient;

    public function __construct(CoreApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function create(array $payload): FunnelRegistration
    {
        $response = $this->apiClient->post(self::CREATE_PATH, [], $payload);
        $data = $response->getData();
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Core funnel response must be a JSON object.');
        }

        return new FunnelRegistration($data, $this->findHeader($response->getHeaders(), 'x-cpp-token'));
    }

    public function update(array $payload, string $cppToken): void
    {
        if ($cppToken === '') {
            throw new \InvalidArgumentException('CPP token is required.');
        }

        $this->apiClient->put(self::UPDATE_PATH, ['x-cpp-token' => $cppToken], $payload);
    }

    private function findHeader(array $headers, string $expectedName): string
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === $expectedName && is_scalar($value)) {
                return (string) $value;
            }
        }

        return '';
    }
}
