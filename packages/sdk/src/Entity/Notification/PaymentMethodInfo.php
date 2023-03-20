<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class PaymentMethodInfo
 *
 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class PaymentMethodInfo extends AbstractEntity
{
    /**
     * @var string
     */
    protected $barcode_content;

    /**
     * @var string
     */
    protected $external_resource_url;

    /**
     * @var string
     */
    protected $payment_method_reference_id;

    /**
     * @var bool
     */
    protected $date_of_expiration;

    /**
     * @var double
     */
    protected $installments;

    /**
     * @var double
     */
    protected $installment_rate;

    /**
     * @var string
     */
    protected $last_four_digits;

    /**
     * @var double
     */
    protected $installment_amount;
}
