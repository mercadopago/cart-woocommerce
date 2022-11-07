<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Payer
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
     * @var Date|string
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

    public function __construct()
    {
        $this->address        = new Address();
        $this->identification = new PayerIdentification();
        $this->phone          = new Phone();
    }
}
