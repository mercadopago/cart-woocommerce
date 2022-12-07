<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class ReceiverAddress
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class ReceiverAddress extends AbstractEntity
{
    /**
     * @var string
     */
    protected $zip_code;

    /**
     * @var string
     */
    protected $state_name;

    /**
     * @var string
     */
    protected $city_name;

    /**
     * @var string
     */
    protected $street_name;

    /**
     * @var string
     */
    protected $apartment;
}
