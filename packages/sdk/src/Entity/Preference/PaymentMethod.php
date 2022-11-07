<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class PaymentMethod
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class PaymentMethod extends AbstractEntity
{
    /**
     * @var int
     */
    protected $default_installments;

    /**
     * @var string
     */
    protected $default_payment_method_id;

    /**
     * @var ExcludedPaymentMethodList
     */
    protected $excluded_payment_methods;

    /**
     * @var ExcludedPaymentTypeList
     */
    protected $excluded_payment_types;

    /**
     * @var int
     */
    protected $installments;

    public function __construct()
    {
        $this->excluded_payment_methods = new ExcludedPaymentMethodList();
        $this->excluded_payment_types = new ExcludedPaymentTypeList();
    }
}
