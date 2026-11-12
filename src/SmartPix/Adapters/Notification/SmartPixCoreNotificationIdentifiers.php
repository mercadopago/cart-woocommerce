<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Notification;

use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationIdentifiers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Identifiers of a Core P&P Smart Pix notification ("pp_order" transaction).
 *
 * Accepts only the envelope shape this integration expects today: a PPORD notification id,
 * a string external_reference (the attempt reference) and exactly one payments_details entry
 * whose references.order_id is an ORD provider reference. Every other shape returns null.
 * The PPORD notification id is the Core Order id returned by the create POST and used by
 * GET /v1/orders, so it is the Orders reference matched against the persisted linkage; the
 * ORD id belongs to the layer behind Core and must match the provider reference persisted
 * for that Order.
 * Status, amounts and payment details in the envelope are never read: financial state comes
 * only from the fresh Orders query. Values are returned verbatim, without coercion.
 */
final class SmartPixCoreNotificationIdentifiers implements SmartPixNotificationIdentifiers
{
    public const TRANSACTION_TYPE = 'pp_order';

    public const NOTIFICATION_ID_PATTERN = '/^PPORD[A-Za-z0-9]+$/D';

    private const ORDER_REFERENCE_PATTERN = '/^ORD[A-Za-z0-9]+$/D';

    /** @param mixed $notificationId */
    public static function isNotificationId($notificationId): bool
    {
        return is_string($notificationId) && preg_match(self::NOTIFICATION_ID_PATTERN, $notificationId) === 1;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{attempt_reference: string, order_reference: string, provider_order_reference: string}|null
     */
    public function extract(array $payload): ?array
    {
        $notificationId = $payload['notification_id'] ?? null;
        if (
            ($payload['transaction_type'] ?? null) !== self::TRANSACTION_TYPE
            || !self::isNotificationId($notificationId)
        ) {
            return null;
        }

        $attemptReference = $payload['external_reference'] ?? null;
        if (!is_string($attemptReference) || $attemptReference === '') {
            return null;
        }

        $details = $payload['payments_details'] ?? null;
        if (!is_array($details) || count($details) !== 1 || !is_array($details[0] ?? null)) {
            return null;
        }

        $references = $details[0]['references'] ?? null;
        $layerReference = is_array($references) ? ($references['order_id'] ?? null) : null;
        if (!is_string($layerReference) || preg_match(self::ORDER_REFERENCE_PATTERN, $layerReference) !== 1) {
            return null;
        }

        return [
            'attempt_reference' => $attemptReference,
            'order_reference' => $notificationId,
            'provider_order_reference' => $layerReference,
        ];
    }
}
