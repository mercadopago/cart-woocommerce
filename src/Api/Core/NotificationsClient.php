<?php

namespace MercadoPago\Woocommerce\Api\Core;

use MercadoPago\Woocommerce\Api\Core\Models\Notification;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationsClient
{
    private CoreApiClient $apiClient;

    public function __construct(CoreApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function find(string $notificationId, CoreRequestContext $context): Notification
    {
        if (preg_match('/^[PM]-\d+$/', $notificationId) !== 1) {
            throw new \InvalidArgumentException('Invalid Core notification id.');
        }

        $response = $this->apiClient->get(
            '/v1/asgard/notification/' . rawurlencode($notificationId),
            $context->toHeaders()
        );
        $data = $response->getData();
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Core notification response must be a JSON object.');
        }

        return Notification::fromArray($data);
    }
}
