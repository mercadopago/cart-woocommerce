<?php

namespace MercadoPago\Woocommerce\Api\Core;

if (!defined('ABSPATH')) {
    exit;
}

class LegacyPayloadSerializer
{
    public static function payment(array $payload, bool $includePaymentTypeId = false): array
    {
        $fields = [
            'date_of_expiration',
            'operation_type',
            'issuer_id',
            'payment_method_id',
            'three_d_secure_mode',
            'description',
            'sponsor_id',
            'counter_currency',
            'shipping_amount',
            'store_id',
            'metadata',
            'external_reference',
            'transaction_amount',
            'coupon_amount',
            'differential_pricing_id',
            'installments',
            'binary_mode',
            'statement_descriptor',
            'notification_url',
            'processing_mode',
            'merchant_account_id',
            'brand_id',
            'reserve_id',
            'collector',
            'callback_url',
            'application_fee',
            'campaign_id',
            'capture',
            'coupon_code',
            'token',
        ];

        if ($includePaymentTypeId) {
            $fields[] = 'payment_type_id';
        }

        return self::serializeEntity($payload, $fields, [
            'payer' => 'paymentPayer',
            'additional_info' => 'additionalInfo',
            'transaction_details' => 'transactionDetails',
            'point_of_interaction' => 'pointOfInteraction',
        ]);
    }

    public static function preference(array $payload): array
    {
        return self::serializeEntity($payload, [
            'auto_return',
            'binary_mode',
            'expiration_date_from',
            'expiration_date_to',
            'expires',
            'external_reference',
            'notification_url',
            'purpose',
            'statement_descriptor',
            'metadata',
            'date_of_expiration',
            'differential_pricing',
            'marketplace',
            'marketplace_fee',
            'sponsor_id',
        ], [
            'additional_info' => 'additionalInfo',
            'payment_methods' => 'preferencePaymentMethods',
            'back_urls' => 'preferenceBackUrls',
            'payer' => 'preferencePayer',
            'shipments' => 'preferenceShipments',
        ], [
            'items' => 'preferenceItem',
            'tracks' => 'preferenceTrack',
        ]);
    }

    private static function paymentPayer(array $payload): array
    {
        return self::serializeEntity($payload, [
            'entity_type',
            'type',
            'id',
            'email',
            'first_name',
            'last_name',
            'operator_id',
        ], [
            'identification' => 'identification',
            'address' => 'paymentAddress',
            'phone' => 'phone',
        ]);
    }

    private static function additionalInfo(array $payload): array
    {
        return self::serializeEntity($payload, [
            'ip_address',
            'referral_url',
            'drop_shipping',
            'delivery_promise',
            'contrated_plan',
        ], [
            'payer' => 'additionalInfoPayer',
            'seller' => 'seller',
            'shipments' => 'paymentShipments',
        ], [
            'items' => 'paymentItem',
        ]);
    }

    private static function additionalInfoPayer(array $payload): array
    {
        return self::serializeEntity($payload, [
            'first_name',
            'last_name',
            'registration_date',
            'registered_user',
            'device_id',
            'platform_email',
            'register_updated_at',
            'user_email',
            'authentication_type',
            'last_purchase',
            'is_prime_user',
        ], [
            'address' => 'additionalInfoAddress',
            'phone' => 'phone',
            'mobile' => 'phone',
            'identification' => 'identification',
        ]);
    }

    private static function seller(array $payload): array
    {
        return self::serializeEntity($payload, [
            'id',
            'registration_date',
            'business_type',
            'status',
            'store_id',
            'user_platform_mail',
            'email',
            'collector',
            'website',
            'platform_url',
            'referral_url',
            'register_updated_at',
            'document',
            'name',
            'hired_plan',
        ], [
            'identification' => 'identification',
            'phone' => 'phone',
            'address' => 'additionalInfoAddress',
        ]);
    }

    private static function paymentShipments(array $payload): array
    {
        return self::serializeEntity($payload, [
            'delivery_promise',
            'drop_shipping',
            'local_pickup',
            'express_shipment',
            'safety',
            'withdrawn',
        ], [
            'tracking' => 'tracking',
            'receiver_address' => 'receiverAddress',
        ]);
    }

    private static function paymentItem(array $payload): array
    {
        return self::serializeEntity($payload, [
            'id',
            'title',
            'description',
            'picture_url',
            'category_id',
            'quantity',
            'unit_price',
        ]);
    }

    private static function transactionDetails(array $payload): array
    {
        return self::serializeEntity($payload, [
            'financial_institution',
            'bank_transfer_id',
            'transaction_id',
        ]);
    }

    private static function pointOfInteraction(array $payload): array
    {
        return self::serializeEntity($payload, [
            'type',
            'sub_type',
            'linked_to',
        ], [
            'application_data' => 'applicationData',
            'transaction_data' => 'transactionData',
            'location' => 'location',
        ]);
    }

    private static function transactionData(array $payload): array
    {
        return self::serializeEntity($payload, [], [
            'bank_info' => 'bankInfo',
        ]);
    }

    private static function preferencePaymentMethods(array $payload): array
    {
        return self::serializeEntity($payload, [
            'default_payment_method_id',
            'default_installments',
            'installments',
        ], [], [
            'excluded_payment_methods' => 'collectionId',
            'excluded_payment_types' => 'collectionId',
        ]);
    }

    private static function preferenceBackUrls(array $payload): array
    {
        return self::serializeEntity($payload, ['failure', 'pending', 'success']);
    }

    private static function preferencePayer(array $payload): array
    {
        return self::serializeEntity($payload, [
            'entity_type',
            'type',
            'id',
            'email',
            'name',
            'surname',
            'operator_id',
            'date_created',
            'is_prime_user',
        ], [
            'identification' => 'identification',
            'address' => 'paymentAddress',
            'phone' => 'phone',
        ]);
    }

    private static function preferenceShipments(array $payload): array
    {
        return self::serializeEntity($payload, [
            'default_shipping_method',
            'cost',
            'dimensions',
            'free_shipping',
            'local_pickup',
            'mode',
        ], [
            'receiver_address' => 'receiverAddress',
        ], [
            'free_methods' => 'collectionId',
        ]);
    }

    private static function preferenceItem(array $payload): array
    {
        return self::serializeEntity($payload, [
            'id',
            'title',
            'description',
            'picture_url',
            'category_id',
            'quantity',
            'currency_id',
            'unit_price',
        ]);
    }

    private static function preferenceTrack(array $payload): array
    {
        return self::serializeEntity($payload, ['type', 'values']);
    }

    private static function identification(array $payload): array
    {
        return self::serializeEntity($payload, ['type', 'number']);
    }

    private static function paymentAddress(array $payload): array
    {
        return self::serializeEntity($payload, [
            'street_name',
            'street_number',
            'neighborhood',
            'city',
            'federal_unit',
            'zip_code',
        ]);
    }

    private static function additionalInfoAddress(array $payload): array
    {
        return self::serializeEntity($payload, [
            'street_name',
            'zip_code',
            'city',
            'country',
            'state',
            'number',
            'complement',
            'apartment',
            'floor',
            'street_number',
        ]);
    }

    private static function receiverAddress(array $payload): array
    {
        return self::serializeEntity($payload, [
            'zip_code',
            'street_name',
            'city_name',
            'state_name',
            'street_number',
            'floor',
            'apartment',
        ]);
    }

    private static function phone(array $payload): array
    {
        return self::serializeEntity($payload, ['number', 'area_code']);
    }

    private static function tracking(array $payload): array
    {
        return self::serializeEntity($payload, ['code', 'status']);
    }

    private static function applicationData(array $payload): array
    {
        return self::serializeEntity($payload, ['name', 'version']);
    }

    private static function bankInfo(array $payload): array
    {
        return self::serializeEntity($payload, ['origin_bank_id']);
    }

    private static function location(array $payload): array
    {
        return self::serializeEntity($payload, ['source', 'state_id']);
    }

    private static function collectionId(array $payload): array
    {
        return self::serializeEntity($payload, ['id']);
    }

    private static function serializeEntity(
        array $payload,
        array $fields,
        array $entities = [],
        array $collections = []
    ): array {
        $serialized = [];

        foreach ($fields as $field) {
            $serialized[$field] = array_key_exists($field, $payload) ? $payload[$field] : null;
        }

        foreach ($entities as $field => $serializer) {
            $serialized[$field] = self::{$serializer}(self::normalizeEntity($payload[$field] ?? []));
        }

        foreach ($collections as $field => $serializer) {
            $collection = self::normalizeCollection($payload[$field] ?? []);
            if ($collection === []) {
                continue;
            }

            foreach ($collection as $index => $item) {
                $serialized[$field][$index] = self::{$serializer}(self::normalizeEntity($item));
            }
        }

        return $serialized;
    }

    private static function normalizeEntity($value): array
    {
        return is_array($value) ? $value : (is_object($value) ? get_object_vars($value) : []);
    }

    private static function normalizeCollection($value): array
    {
        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }

        return self::normalizeEntity($value);
    }
}
