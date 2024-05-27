<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class PaymentMethodsMock
 */
class PaymentMethodsMock
{
  const PAYMENT_METHOD_VISA = [
        "id" => "visa",
        "settings" => [],
        "financial_institutions" => [],
        "thumbnail" => "https://url_thumbnail_visa",
        "deferred_capture" => "supported",
        "secure_thumbnail" => "https://url_secure_thumbnail_visa",
        "processing_modes" => ["aggregator"],
        "name" => "Visa",
        "additional_info_needed" => ["cardholder_name", "cardholder_identification_type", "cardholder_identification_number"],
        "payment_type_id" => "credit_card",
        "accreditation_time" => 2880,
        "min_allowed_amount" => 0.5,
        "max_allowed_amount" => 60000,
        "status" => "active"
    ];

  const PAYMENT_METHOD_PIX = [
      "id" => "pix",
      "settings" => [],
      "financial_institutions" => [
        [
          "description" => "PIX",
          "id" => "1",
        ]
      ],
      "thumbnail" => "https://url_thumbnail_pix",
      "deferred_capture" => "supported",
      "secure_thumbnail" => "https://url_secure_thumbnail_pix",
      "processing_modes" => ["aggregator"],
      "name" => "pix",
      "additional_info_needed" => null,
      "payment_type_id" => "bank_transfer",
      "accreditation_time" => 0,
      "min_allowed_amount" => 0.01,
      "max_allowed_amount" => 9999999,
      "status" => "active"
  ];

const PAYMENT_METHODS = [PaymentMethodsMock::PAYMENT_METHOD_VISA, PaymentMethodsMock::PAYMENT_METHOD_PIX];

const GROUPED_PAYMENT_METHODS_BY_ID = [
    "visa" => PaymentMethodsMock::PAYMENT_METHOD_VISA,
    "pix" => PaymentMethodsMock::PAYMENT_METHOD_PIX
  ];
}
