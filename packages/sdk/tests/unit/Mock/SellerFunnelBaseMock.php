<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class SellerFunnelBaseMock
 */
class SellerFunnelBaseMock
{

    const COMPLETE_CREATE_SELLER_BASE = [
        "platform_id" => "ppcoreinternal",
        "shopUrl" => "http://localhost"
    ];

    const COMPLETE_UPDATE_SELLER_BASE = [
        "id" => "d06e1a4510ab404d34af3f738834a617",
        "is_added_production_credential" => true,
        "is_added_test_credential" => true,
        "productId" => "4das56",
        "custId" => "123",
        "applicationId" => "123",
        "pluginMode" => "prod",
        "isDeleted" => false,
        "acceptedPayments" => [
            "bolbradesco",
            "pix"
        ]
    ];
}
