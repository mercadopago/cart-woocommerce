<?php

namespace MercadoPago\PP\Sdk\Entity\PaymentMethods;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class PaymentMethod
 *
 * @property string $id
 * @property FinancialInstitutionList $financial_institutions
 * @property array $settings
 * @property string $thumbnail
 * @property string $deferred_capture
 * @property string $secure_thumbnail
 * @property array $processing_modes
 * @property string $name
 * @property array $additional_info_needed
 * @property string $payment_type_id
 * @property int $accreditation_time
 * @property double $min_allowed_amount
 * @property double $max_allowed_amount
 * @property string $status
 *
 * @package MercadoPago\PP\Sdk\Entity\PaymentMethods
 */
class PaymentMethod extends AbstractEntity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var FinancialInstitutionList
     */
    protected $financial_institutions;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var string
     */
    protected $thumbnail;

    /**
     * @var string
     */
    protected $deferred_capture;

    /**
     * @var string
     */
    protected $secure_thumbnail;

    /**
     * @var array
     */
    protected $processing_modes;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $additional_info_needed;

    /**
     * @var string
     */
    protected $payment_type_id;

    /**
     * @var int
     */
    protected $accreditation_time;

    /**
     * @var double
     */
    protected $min_allowed_amount;

    /**
     * @var double
     */
    protected $max_allowed_amount;

    /**
     * @var string
     */
    protected $status;

    /**
     * PaymentMethod constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->financial_institutions = new FinancialInstitutionList($manager) && [];
    }
}
