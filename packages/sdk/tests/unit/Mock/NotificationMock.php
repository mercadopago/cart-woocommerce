<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class NotificationMock
 */
class NotificationMock
{

  const COMPLETE_NOTIFICATION = [
    "notification_id" => "P-25604645467",
    "notification_url" => "https://httpbin.org/post",
    "status" => "approved",
    "transaction_id" => 25604645467,
    "transaction_type" => "payment",
    "transaction_amount" => 51.99,
    "total_pending" => 0,
    "total_approved" => 51.99,
    "total_paid" => 51.99,
    "total_rejected" => 0,
    "total_refunded" => 0,
    "total_cancelled" => 0,
    "total_charged_back" => 0,
    "payments_metadata" => [
      "account_name" => "paguemenos01040",
      "callback_url" => "https://mercadopago.vtexpayments.com.br/api/pvt/payment-provider/transactions/4ED8296AB28F47C6AF3FFFBA558F2EDD/payments/9A5431CE81A44DBA8C76268BBD7F6781/callback?accountName=paguemenos01040",
      "delay_auto_settle" => "1",
      "merchant_name" => "paguemenos01040",
      "order_id" => "4ED8296AB28F47C6AF3FFFBA558F2EDD",
      "original_notification_url" => "https://httpbin.org/post",
      "seller_website" => "http://paguemenos.vtexcommercestable.com.br",
      "uuid" => "c9b70cc3-dd29-4c28-acb3-94f3632f3dd1",
      "vtex_payment_id" => "9A5431CE81A44DBA8C76268BBD7F6781",
      "vtex_transaction_id" => "4ED8296AB28F47C6AF3FFFBA558F2EDD"
    ],
    "payments_details" => [
      [
        "id" => 25604645467,
        "status" => "approved",
        "status_detail" => "accredited",
        "payment_type_id" => "pix",
        "payment_method_id" => "pix",
        "payment_method_info"=> [
          "barcode_content" => "9A5431CE81A44DBA8C76268BBD7F6781",
          "external_resource_url" => "https://mercadopago.vtexpayments.com.br/api/pvt/payment-provider/transactions/4ED8296AB28F47C6AF3FFFBA558F2EDD/payments/9A5431CE81A44DBA8C76268BBD7F6781/callback?accountName=paguemenos01040",
          "payment_method_reference_id" => "1129288350",
          "date_of_expiration" => "2022-09-20T22:13:32.000+0000",
          "installments" => 51.99,
          "installment_rate" => 51.99,
          "last_four_digits" => "1234",
          "installment_amount" => 51.99
        ],
        "total_amount" => 51.99,
        "paid_amount" => 51.99,
        "coupon_amount" => 0,
        "shipping_cost" => 0,
        "refunds" => [
          "1129288350" => [
            "id" => "1129288350",
            "status" => "approved",
            "notifying" => true,
            "metadata" => [
              "uuid" => "c9b70cc3-dd29-4c28-acb3-94f3632f3dd1",
              "order_id" => "4ED8296AB28F47C6AF3FFFBA558F2EDD"
            ]
          ],
          "1129288351" => [
            "id" => "1129288351",
            "status" => "approved",
            "notifying" => true,
            "metadata" => [
              "uuid" => "c9b70cc3-dd29-4c28-acb3-94f3632f3dd2",
              "order_id" => "4ED8296AB28F47C6AF3FFFBA558F2EDD"
            ]
          ]
        ]
      ]
    ]
  ];
}
