<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Payer
 *
 * @property Address $address
 * @property string $date_created
 * @property string $email
 * @property PayerIdentification $identification
 * @property string $name
 * @property Phone $phone
 * @property string $surname
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Payer extends AbstractEntity
{
    /**
     * @var Address
     */
    protected $address;

    /**
     * @var string
     */
    protected $date_created;

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
     * @var Phone
     */
    protected $phone;

    /**
     * @var string
     */
    protected $surname;

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
