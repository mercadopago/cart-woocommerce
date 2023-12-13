<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class AdditionalInfoPayer
 *
 * @property string $first_name
 * @property string $last_name
 * @property string $registration_date
 * @property string $registered_user
 * @property string $device_id
 * @property string $platform_email
 * @property string $register_updated_at
 * @property string $user_email
 * @property string $authentication_type
 * @property string $last_purchase
 * @property AdditionalInfoAddress $address
 * @property Phone $phone
 * @property Phone $mobile
 * @property Identification $identification
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class AdditionalInfoPayer extends AbstractEntity
{
    /**
     * @var string
     */
    protected $first_name;

    /**
     * @var string
     */
    protected $last_name;

    /**
     * @var string
     */
    protected $registration_date;

     /**
     * @var string
     */
    protected $registered_user;

     /**
     * @var string
     */
    protected $device_id;

    /**
     * @var string
     */
    protected $platform_email;

     /**
     * @var string
     */
    protected $register_updated_at;

    /**
     * @var string
     */
    protected $user_email;

     /**
     * @var string
     */
    protected $authentication_type;

     /**
     * @var string
     */
    protected $last_purchase;

     /**
     * @var AdditionalInfoAddress
     */
    protected $address;

    /**
     * @var Phone
     */
    protected $phone;

    /**
     * @var Phone
     */
    protected $mobile;

    /**
     * @var Identification
     */
    protected $identification;

    /**
     * AdditionalInfoPayer constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->address         = new AdditionalInfoAddress($manager);
        $this->phone           = new Phone($manager);
        $this->mobile          = new Phone($manager);
        $this->identification  = new Identification($manager);
    }
}
