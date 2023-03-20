<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class PaymentMethod
 *
 * @property int $default_installments
 * @property string $default_payment_method_id
 * @property ExcludedPaymentMethodList $excluded_payment_methods
 * @property ExcludedPaymentTypeList $excluded_payment_types
 * @property int $installments
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

    /**
     * PaymentMethod constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->excluded_payment_methods = new ExcludedPaymentMethodList($manager);
        $this->excluded_payment_types = new ExcludedPaymentTypeList($manager);
    }
}
