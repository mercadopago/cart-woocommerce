<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class PreferenceMock
 */
class PreferenceMock
{

  const COMPLETE_PREFERENCE = [
    "auto_return" => "approved",
    "back_urls" => [
      "failure" => "http://www.myapp.com/carrinho/?cancel_order=true&order=wc_order&order_id=XX&redirect&_wpnonce=XX",
      "pending" => "http://www.myapp.com/finalizar-compra/order-received/XX/?key=wc_order",
      "success" => "http://www.myapp.com/finalizar-compra/order-received/XX/?key=wc_order"
    ],
    "binary_mode" => false,
    "differential_pricing" => [
      "id"  => ""
    ],
    "external_reference" => "WC-XX",
    "items" => [
      [
        "title" => "Dummy Title",
        "description" => "Dummy description",
        "picture_url" => "http://www.myapp.com/myimage.jpg",
        "category_id" => "car_electronics",
        "quantity" => 1,
        "currency_id" => "U$",
        "unit_price" => 10
      ]
    ],
    "notification_url" => "https://httpbin.org/post",
    "payer" => [
      "address" => [
        "street_name" => "Address - BR - BR",
        "street_number" => "123",
        "zip_code" => "00000-000"
      ],
      "email" => "email@testuser.com",
      "identification" => [
        "number" => "CPF",
        "type" => "376.624.684-45"
      ],
      "name" => "Amadeu",
      "phone" => [
        "area_code" => "",
        "number" => "99999999999"
      ],
      "surname" => "Fontes"
    ],
    "payment_methods" => [
      "excluded_payment_methods" => [
        []
      ],
      "excluded_payment_types" => [
        []
      ],
      "installments" => 1
    ],
    "shipments" => [
      "free_methods" => [
        []
      ],
      "receiver_address" => []
    ],
    "statement_descriptor" => "Mercado Pago",
    "tracks" => [
      [
        "type" => "google_ad"
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
