<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Seller
 *
 * @property string $id
 * @property string $registration_date
 * @property string $business_type
 * @property string $status
 * @property string $store_id
 * @property string $user_platform_mail
 * @property string $email
 * @property string $collector
 * @property string $website
 * @property string $platform_url
 * @property string $referral_url
 * @property string $register_updated_at
 * @property string $document
 * @property string $name
 * @property string $hired_plan
 * @property Identification $identification
 * @property Phone  $phone
 * @property AdditionalInfoAddress $address
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Seller extends AbstractEntity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $registration_date;

    /**
     * @var string
     */
    protected $business_type;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $store_id;

    /**
     * @var string
     */
    protected $user_platform_mail;

    /**
     * @var string
     */
    protected $email;

     /**
     * @var string
     */
    protected $collector;

     /**
     * @var string
     */
    protected $website;

     /**
     * @var string
     */
    protected $platform_url;

    /**
     * @var string
     */
    protected $referral_url;

     /**
     * @var string
     */
    protected $register_updated_at;

     /**
     * @var string
     */
    protected $document;

     /**
     * @var string
     */
    protected $name;

     /**
     * @var string
     */
    protected $hired_plan;

    /**
     * @var Identification
     */
    protected $identification;

    /**
     * @var Phone
     */
    protected $phone;

     /**
     * @var AddtionalInfoAddress
     */
    protected $address;

    /**
     * Seller constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->identification = new Identification($manager);
        $this->phone = new Phone($manager);
        $this->address = new AdditionalInfoAddress($manager);
    }
}
