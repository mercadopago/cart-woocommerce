<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class ReceiverAddress
 *
 * @property string $zip_code
 * @property string $state_name
 * @property string $city_name
 * @property string $street_name
 * @property string $apartment
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
