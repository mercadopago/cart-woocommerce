<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Notification;

use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixNotificationReceiver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles a Core P&P notification whose posted notification_id is a Smart Pix PPORD id.
 *
 * Trust model identical to CoreNotification: the posted body only names the notification;
 * authenticity comes from fetching it back with the seller context through the same
 * NotificationsClient. Its exceptions (Core API errors, including non-2xx answers) are
 * absorbed by the receiver into a fixed failure response. Identifiers are taken
 * from the fetched notification only, which must echo the requested id; the receiver then
 * runs linkage, the fresh Orders query and the guarded apply.
 *
 * Like the legacy Core notification, the fetch is made once with the given context, which
 * carries the store's current-mode credentials; a failed fetch is never retried here.
 *
 * Never logs, echoes or exits; the caller sends the returned fixed status and message.
 */
final class SmartPixCoreNotificationHandler
{
    private NotificationsClient $client;

    private SmartPixNotificationReceiver $receiver;

    public function __construct(NotificationsClient $client, SmartPixNotificationReceiver $receiver)
    {
        $this->client = $client;
        $this->receiver = $receiver;
    }

    /** @return array{status: int, message: string} */
    public function handle(string $notificationId, CoreRequestContext $context): array
    {
        return $this->receiver->handleFetched(function () use ($notificationId, $context): ?array {
            if (!SmartPixCoreNotificationIdentifiers::isNotificationId($notificationId)) {
                return null;
            }
            $notification = $this->client->find($notificationId, $context)->toArray();
            return ($notification['notification_id'] ?? null) === $notificationId ? $notification : null;
        });
    }

    /**
     * The posted Core body exceeded the Smart Pix limit: answered without fetching it.
     * @return array{status: int, message: string}
     */
    public function rejectOversized(): array
    {
        return $this->receiver->rejectOversized();
    }
}
