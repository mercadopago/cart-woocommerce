<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Payer
 *
 * @property string $id
 * @property string $entity_type
 * @property string $email
 * @property string $first_name
 * @property string $last_name
 * @property PayerIdentification $identification
 * @property Address $address
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

    /**
     * Payer constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->identification = new PayerIdentification($manager);
        $this->address        = new Address($manager);
    }
}
