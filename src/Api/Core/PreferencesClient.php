<?php

namespace MercadoPago\Woocommerce\Api\Core;

use MercadoPago\Woocommerce\Api\Core\Models\Preference;

if (!defined('ABSPATH')) {
    exit;
}

class PreferencesClient
{
    public const CREATE_PATH = '/v1/asgard/preferences';

    private const OBJECT_PATHS = [
        'additional_info.shipments',
        'additional_info.shipments.receiver_address',
        'additional_info.payer',
        'additional_info.payer.phone',
        'additional_info.payer.mobile',
        'additional_info.payer.address',
        'additional_info.payer.identification',
        'additional_info.seller',
        'back_urls',
        'payer',
        'payer.phone',
        'payer.address',
        'payment_methods',
        'shipments',
        'shipments.receiver_address',
    ];

    private CoreApiClient $apiClient;

    public function __construct(CoreApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function create(array $payload, CoreRequestContext $context): Preference
    {
        $payload = LegacyPayloadSerializer::preference($payload);
        $payload = PayloadNormalizer::preserveEmptyObjects($payload, self::OBJECT_PATHS);
        $response = $this->apiClient->post(self::CREATE_PATH, $context->toHeaders(), $payload);
        $data = $response->getData();
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Core preference response must be a JSON object.');
        }

        return Preference::fromArray($data);
    }
}
