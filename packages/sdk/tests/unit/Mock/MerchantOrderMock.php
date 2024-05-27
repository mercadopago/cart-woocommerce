<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class MerchantOrderMock
 */
class MerchantOrderMock
{
    const MERCHANT_ORDERS = [
        "elements" => [
            [
                "id" => 1234567891,
                "status" => "opened",
                "external_reference" => "Order_1234567891",
                "preference_id" => "123465789-12346578-1234-1234-1234-123456789123",
                "payments" => [],
                "shipments" => [],
                "payouts" => [],
                "collector" => [
                    "id" => 123465789,
                    "email" => "",
                    "nickname" => "TETE4642489"
                ],
                "marketplace" => "NONE",
                "notification_url" => "https://webhook.com",
                "date_created" => "2021-09-08T19:30:26.637+00:00",
                "last_updated" => "2022-10-06T06:18:44.821-04:00",
                "sanitized_version" => 1,
                "sponsor_id" => null,
                "shipping_cost" => 0,
                "total_amount" => 18.37,
                "site_id" => "MLB",
                "paid_amount" => 0,
                "refunded_amount" => 0,
                "payer" => null,
                "items" => [
                    "id" => "40",
                    "category_id" => "art",
                    "currency_id" => "BRL",
                    "description" => "camiseta branca",
                    "picture_url" => "https://site/img.jpg",
                    "picture_id" => "123",
                    "title" => "Camiseta",
                    "quantity" => 1,
                    "unit_price" => 18.37
                ],
                "cancelled" => false,
                "additional_info" => "",
                "application_id" => null,
                "is_test" => false,
                "order_status" => "payment_required"
            ],
            [
                "id" => 1234567892,
                "status" => "opened",
                "external_reference" => "Order_1234567891",
                "preference_id" => "123465789-12346578-1234-1234-1234-123456789123",
                "payments" => [],
                "shipments" => [],
                "payouts" => [],
                "collector" => [
                    "id" => 123465789,
                    "email" => "",
                    "nickname" => "TETE4642489"
                ],
                "marketplace" => "NONE",
                "notification_url" => "https://webhook.com",
                "date_created" => "2021-09-08T19:30:26.637+00:00",
                "last_updated" => "2022-10-06T06:18:44.821-04:00",
                "sanitized_version" => 1,
                "sponsor_id" => null,
                "shipping_cost" => 0,
                "total_amount" => 18.37,
                "site_id" => "MLB",
                "paid_amount" => 0,
                "refunded_amount" => 0,
                "payer" => null,
                "items" => [
                    "id" => "40",
                    "category_id" => "art",
                    "currency_id" => "BRL",
                    "description" => "camiseta cinza",
                    "picture_url" => "https://site/img.jpg",
                    "picture_id" => "123",
                    "title" => "Camiseta",
                    "quantity" => 1,
                    "unit_price" => 18.37
                ],
                "cancelled" => false,
                "additional_info" => "",
                "application_id" => null,
                "is_test" => false,
                "order_status" => "payment_required"
            ]
        ]
    ];

    const MERCHANT_ORDER = [
        "id" => 1234567891,
        "status" => "opened",
        "external_reference" => "Order_1234567891",
        "preference_id" => "123465789-12346578-1234-1234-1234-123456789123",
        "payments" => [],
        "shipments" => [],
        "payouts" => [],
        "collector" => [
            "id" => 123465789,
            "email" => "",
            "nickname" => "TETE4642489"
        ],
        "marketplace" => "NONE",
        "notification_url" => "https://webhook.com",
        "date_created" => "2021-09-08T19:30:26.637+00:00",
        "last_updated" => "2022-10-06T06:18:44.821-04:00",
        "sanitized_version" => 1,
        "sponsor_id" => null,
        "shipping_cost" => 0,
        "total_amount" => 18.37,
        "site_id" => "MLB",
        "paid_amount" => 0,
        "refunded_amount" => 0,
        "payer" => null,
        "items" => [
            "id" => "40",
            "category_id" => "art",
            "currency_id" => "BRL",
            "description" => "camiseta branca",
            "picture_url" => "https://site/img.jpg",
            "picture_id" => "123",
            "title" => "Camiseta",
            "quantity" => 1,
            "unit_price" => 18.37
        ],
        "cancelled" => false,
        "additional_info" => "",
        "application_id" => null,
        "is_test" => false,
        "order_status" => "payment_required"
    ];
}
