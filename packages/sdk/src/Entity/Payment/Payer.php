<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Payer
 *
 * @property string $entity_type
 * @property string $type
 * @property string $id
 * @property string $email
 * @property Identification $identification
 * @property string $first_name
 * @property string $last_name
 * @property string $operator_id
 * @property Address $address
 * @property Phone $phone
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Payer extends AbstractEntity
{
    /**
     * @var string
     */
    protected $entity_type;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var Identification
     */
    protected $identification;

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
    protected $operator_id;

    /**
     * @var Address
     */
    protected $address;

    /**
     * @var Phone
     */
    protected $phone;

    /**
     * Payer constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->identification = new Identification($manager);
        $this->address = new Address($manager);
        $this->phone = new Phone($manager);
    }
}
