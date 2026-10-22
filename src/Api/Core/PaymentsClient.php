<?php

namespace MercadoPago\Woocommerce\Api\Core;

use MercadoPago\Woocommerce\Api\Core\Models\Payment;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentsClient
{
    public const CREATE_PATH = '/v1/asgard/payments';

    private const OBJECT_PATHS = [
        'payer.address',
        'payer.identification',
        'payer.phone',
        'additional_info.shipments',
        'additional_info.shipments.receiver_address',
        'additional_info.payer',
        'additional_info.payer.phone',
        'additional_info.payer.mobile',
        'additional_info.payer.address',
        'additional_info.payer.identification',
        'additional_info.seller',
        'transaction_details',
        'point_of_interaction',
        'point_of_interaction.transaction_data',
    ];

    private CoreApiClient $apiClient;

    public function __construct(CoreApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function create(
        array $payload,
        CoreRequestContext $context,
        ?string $sessionId = null,
        ?string $superToken = null,
        ?string $paymentTypeId = null
    ): Payment {
        $headers = $context->toHeaders();

        if ($sessionId !== null && $sessionId !== '') {
            $headers['x-meli-session-id'] = $sessionId;
        }

        if ($superToken !== null && $superToken !== '') {
            $headers['x-super-token'] = $superToken;
        }

        if ($paymentTypeId !== null && $paymentTypeId !== '') {
            $payload['payment_type_id'] = $paymentTypeId;
        }

        $payload = LegacyPayloadSerializer::payment($payload, $paymentTypeId !== null && $paymentTypeId !== '');
        $payload = PayloadNormalizer::preserveEmptyObjects($payload, self::OBJECT_PATHS);

        $response = $this->apiClient->post(self::CREATE_PATH, $headers, $payload);
        $data = $response->getData();
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Core payment response must be a JSON object.');
        }

        return Payment::fromArray($data);
    }
}
