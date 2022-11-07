<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

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

    public function __construct()
    {
        $this->phone   = new Phone();
        $this->address = new AdditionalInfoPayerAddress();
    }
}
