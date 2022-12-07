<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class AdditionalInfoPayer
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
     * @var Phone
     */
    protected $phone;

    /**
     * @var AdditionalInfoPayerAddress
     */
    protected $address;

    /**
     * AdditionalInfoPayer constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->phone   = new Phone($manager);
        $this->address = new AdditionalInfoPayerAddress($manager);
    }
}
