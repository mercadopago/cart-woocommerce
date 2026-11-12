<?php

namespace MercadoPago\Woocommerce\Api\Core;

use MercadoPago\Woocommerce\Api\Core\Models\Notification;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationsClient
{
    /**
     * Smart Pix Orders notification ids: "PPORD" followed by alphanumerics, no separator.
     * Must stay equal to SmartPixCoreNotificationIdentifiers::NOTIFICATION_ID_PATTERN (the
     * client tests assert it); declared here so the Core API layer does not depend on SmartPix.
     */
    public const SMART_PIX_ORDERS_ID_PATTERN = '/^PPORD[A-Za-z0-9]+$/D';

    /** Payment (P-) and merchant order (M-) notification ids. */
    private const LEGACY_ID_PATTERN = '/^[PM]-\d+$/';

    private CoreApiClient $apiClient;

    public function __construct(CoreApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function find(string $notificationId, CoreRequestContext $context): Notification
    {
        $isSmartPixOrder = preg_match(self::SMART_PIX_ORDERS_ID_PATTERN, $notificationId) === 1;
        if (
            preg_match(self::LEGACY_ID_PATTERN, $notificationId) !== 1
            && !$isSmartPixOrder
        ) {
            throw new \InvalidArgumentException('Invalid Core notification id.');
        }

        $path = $isSmartPixOrder
            ? '/plugins-platforms/v1/notification/order/'
            : '/v1/asgard/notification/';
        $response = $this->apiClient->get(
            $path . rawurlencode($notificationId),
            $context->toHeaders()
        );
        $data = $response->getData();
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Core notification response must be a JSON object.');
        }

        return Notification::fromArray($data);
    }
}
