<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Payer
 *
 * @property string $entity_type
 * @property string $type
 * @property string $id
 * @property string $email
 * @property PayerIdentification $identification
 * @property string $name
 * @property string $surname
 * @property string $operator_id
 * @property Address $address
 * @property Phone $phone
 * @property string $date_created
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
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
     * @var PayerIdentification
     */
    protected $identification;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $surname;

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
     * @var string
     */
    protected $date_created;

    /**
     * Payer constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->address        = new Address($manager);
        $this->identification = new PayerIdentification($manager);
        $this->phone          = new Phone($manager);
    }
}
