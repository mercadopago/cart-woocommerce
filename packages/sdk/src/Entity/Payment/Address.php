<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Address
 *
 * @property string $street_name
 * @property string $street_number
 * @property string $neighborhood
 * @property string $city
 * @property string $federal_unit
 * @property string $zip_code
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Address extends AbstractEntity
{
    /**
     * @var string
     */
    protected $street_name;

    /**
     * @var string
     */
    protected $street_number;

    /**
     * @var string
     */
    protected $neighborhood;

    /**
     * @var string
     */
    protected $city;

    /**
     * @var string
     */
    protected $federal_unit;

    /**
     * @var string
     */
    protected $zip_code;
}
