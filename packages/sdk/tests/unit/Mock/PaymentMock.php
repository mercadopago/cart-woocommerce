<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class PaymentMock
 */
class PaymentMock
{

  const COMPLETE_PAYMENT = [
    "binary_mode" => false,
    "external_reference" => "WC-105",
    "notification_url" => "https://httpbin.org/post",
    "statement_descriptor" => "TESTSTORE",
    "transaction_amount" => 12,
    "installments" => null,
    "three_d_secure_mode" => "not_supported",
    "description" => "Flying Ninja x 1",
    "payment_method_id" => "pix",
    "date_of_expiration" => "2022-09-20T22:13:32.000+0000",
    "point_of_interaction" => [
      "type" => "OPENPLATFORM"
    ],
    "payer" => [
      "email" => "test_user_15543629@testuser.com",
      "first_name" => "Test",
      "last_name" => "Customer",
      "identification" => [
        "type" => "CPF",
        "number" => "376.624.684-45"
      ],
      "address" => [
        "street_name" => "Address - BR - BR",
        "street_number" => "123",
        "neighborhood" => "City",
        "city" => "City",
        "federal_unit" => "SP",
        "zip_code" => "00000-000"
      ]
    ],
    "additional_info" => [
        "ip_address"=> "191.191.191.0",
        "referral_url"=> "www.sellertest.com",
        "drop_shipping"=> true,
        "delivery_promise"=> "2022-09-22",
        "contrated_plan"=> "premium",
        "items"=> [
          [
            "category_id"=> "others",
            "description"=> "bola",
            "id"=> "123",
            "picture_url"=> "https=>//",
            "quantity"=> "1",
            "title"=> "Bola de futebol",
            "unit_price"=> "20"
          ]
        ],
        "payer"=> [
          "address"=> [
            "street_name"=> "Rua Teste",
            "zip_code"=> "00000-000",
            "city"=> "São Paulo",
            "country"=> "Brasil",
            "state"=> "SP",
            "number"=> "0",
            "complement"=> "A"
          ],
          "first_name"=> "EncryptedName",
          "last_name"=> "Bar",
          "phone"=> [
            "area_code"=> "11",
            "number"=> "999999999"
          ],
          "mobile"=> [
            "area_code"=> 11,
            "number"=> ""
          ],
          "identification"=> [
            "type"=> "",
            "number"=> 0
          ],
          "registration_date"=> "2023-04-11T19=>18=>38.486Z",
          "registered_user"=> false,
          "device_id"=> "",
          "platform_email"=> "",
          "register_updated_at"=> "2023-04-11T19=>18=>38.486Z",
          "user_email"=> "test@test.com",
          "authentication_type"=> "",
          "last_purchase"=> ""
        ],
        "seller"=> [
          "id"=> "",
          "registration_date"=> "2023-04-11T19=>18=>38.486Z",
          "business_type"=> "dropshipping",
          "identification"=> [
            "type"=> "",
            "number"=> "0",
          ],
          "status"=> "active",
          "store_id"=> "123",
          "user_platform_mail"=> "gmail",
          "email"=> "test@test.com",
          "phone"=> [
            "area_code"=> "11",
            "number"=> ""
          ],
          "collector"=> "123",
          "website"=> "https//",
          "platform_url"=> "https//",
          "referral_url"=> "",
          "register_updated_at"=> "2023-04-11T19=>18=>38.486Z",
          "document"=> "123",
          "name"=> "Foo",
          "hired_plan"=> "plan",
          "address"=> [
            "zip_code"=> "00000-000",
            "street_name"=> "Rua Teste",
            "city"=> "São Paulo",
            "country"=> "Brasil",
            "state"=> "SP",
            "number"=> "0",
            "complement"=> "A"
          ]
        ],
        "shipments"=> [
          "receiver_address"=> [
            "apartment"=> "45",
            "floor"=> "4",
            "street_name"=> "Rua Teste",
            "zip_code"=> "00000-000",
            "city"=> "São Paulo",
            "country"=> "Brasil",
            "state"=> "SP",
            "number"=> "0",
            "complement"=> "A"
          ],
          "delivery_promise"=> "",
          "drop_shipping"=> "",
          "local_pickup"=> "",
          "express_shipment"=> "",
          "safety"=> "",
          "shipment_safety"=> false,
          "withdrawn"=> false,
          "tracking"=> [
            "code"=> "",
            "status"=> ""
          ],
          "shipment_amount"=> 0
        ]
    ]
  ];
  
  const COMPLETE_PAYMENT_WITH_3DS = [
    "binary_mode" => false,
    "external_reference" => "WC-105",
    "notification_url" => "https://httpbin.org/post",
    "statement_descriptor" => "TESTSTORE",
    "transaction_amount" => 210,
    "installments" => 3,
    "three_d_secure_mode" => "optional",
    "description" => "Flying Ninja x 1",
    "payment_method_id" => "master",
    "date_of_expiration" => "2022-09-20T22:13:32.000+0000",
    "point_of_interaction" => [
      "type" => "OPENPLATFORM"
    ],
    "payer" => [
      "email" => "test_user_15543629@testuser.com",
      "first_name" => "Test",
      "last_name" => "Customer",
      "identification" => [
        "type" => "CPF",
        "number" => "376.624.684-45"
      ],
      "address" => [
        "street_name" => "Address - BR - BR",
        "street_number" => "123",
        "neighborhood" => "City",
        "city" => "City",
        "federal_unit" => "SP",
        "zip_code" => "00000-000"
      ]
    ],
    "additional_info" => [
        "ip_address"=> "191.191.191.0",
        "referral_url"=> "www.sellertest.com",
        "drop_shipping"=> true,
        "delivery_promise"=> "2022-09-22",
        "contrated_plan"=> "premium",
        "items"=> [
          [
            "category_id"=> "others",
            "description"=> "bola",
            "id"=> "123",
            "picture_url"=> "https=>//",
            "quantity"=> "1",
            "title"=> "Bola de futebol",
            "unit_price"=> "20"
          ]
        ],
        "payer"=> [
          "address"=> [
            "street_name"=> "Rua Teste",
            "zip_code"=> "00000-000",
            "city"=> "São Paulo",
            "country"=> "Brasil",
            "state"=> "SP",
            "number"=> "0",
            "complement"=> "A"
          ],
          "first_name"=> "EncryptedName",
          "last_name"=> "Bar",
          "phone"=> [
            "area_code"=> "11",
            "number"=> "999999999"
          ],
          "mobile"=> [
            "area_code"=> 11,
            "number"=> ""
          ],
          "identification"=> [
            "type"=> "",
            "number"=> 0
          ],
          "registration_date"=> "2023-04-11T19=>18=>38.486Z",
          "registered_user"=> false,
          "device_id"=> "",
          "platform_email"=> "",
          "register_updated_at"=> "2023-04-11T19=>18=>38.486Z",
          "user_email"=> "test@test.com",
          "authentication_type"=> "",
          "last_purchase"=> ""
        ],
        "seller"=> [
          "id"=> "",
          "registration_date"=> "2023-04-11T19=>18=>38.486Z",
          "business_type"=> "dropshipping",
          "identification"=> [
            "type"=> "",
            "number"=> "0",
          ],
          "status"=> "active",
          "store_id"=> "123",
          "user_platform_mail"=> "gmail",
          "email"=> "test@test.com",
          "phone"=> [
            "area_code"=> "11",
            "number"=> ""
          ],
          "collector"=> "123",
          "website"=> "https//",
          "platform_url"=> "https//",
          "referral_url"=> "",
          "register_updated_at"=> "2023-04-11T19=>18=>38.486Z",
          "document"=> "123",
          "name"=> "Foo",
          "hired_plan"=> "plan",
          "address"=> [
            "zip_code"=> "00000-000",
            "street_name"=> "Rua Teste",
            "city"=> "São Paulo",
            "country"=> "Brasil",
            "state"=> "SP",
            "number"=> "0",
            "complement"=> "A"
          ]
        ],
        "shipments"=> [
          "receiver_address"=> [
            "apartment"=> "45",
            "floor"=> "4",
            "street_name"=> "Rua Teste",
            "zip_code"=> "00000-000",
            "city"=> "São Paulo",
            "country"=> "Brasil",
            "state"=> "SP",
            "number"=> "0",
            "complement"=> "A"
          ],
          "delivery_promise"=> "",
          "drop_shipping"=> "",
          "local_pickup"=> "",
          "express_shipment"=> "",
          "safety"=> "",
          "shipment_safety"=> false,
          "withdrawn"=> false,
          "tracking"=> [
            "code"=> "",
            "status"=> ""
          ],
          "shipment_amount"=> 0
        ]
    ]
  ];
}
