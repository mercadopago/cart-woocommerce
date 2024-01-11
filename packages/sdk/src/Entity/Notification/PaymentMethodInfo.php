<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class PaymentMethodInfo
 *
 * @property string $barcode_content
 * @property string $external_resource_url
 * @property string $payment_method_reference_id
 * @property string $date_of_expiration
 * @property string $last_four_digits
 * @property float $installments
 * @property float $installment_rate
 * @property double $installment_amount

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
     * @var string
     */
    protected $date_of_expiration;

    /**
     * @var string
     */
    protected $last_four_digits;

    /**
     * @var float
     */
    protected $installments;

    /**
     * @var float
     */
    protected $installment_rate;

    /**
     * @var double
     */
    protected $installment_amount;
}
