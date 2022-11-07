<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Payer
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Payer extends AbstractEntity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $entity_type;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $first_name;

    /**
     * @var string
     */
    protected $last_name;

    /**
     * @var PayerIdentification
     */
    protected $identification;

    /**
     * @var Address
     */
    protected $address;

    public function __construct()
    {
        $this->identification = new PayerIdentification();
        $this->address        = new Address();
    }
}
