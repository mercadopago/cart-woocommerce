<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class AdditionalInfoAddress
 *
 * @property string $street_name
 * @property string $zip_code
 * @property string $city
 * @property string $country
 * @property string $state
 * @property string $number
 * @property string $complement
 * @property string $apartment
 * @property string $floor
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class AdditionalInfoAddress extends AbstractEntity
{
    /**
     * @var string
     */
    protected $street_name;

    /**
     * @var string
     */
    protected $zip_code;

     /**
     * @var string
     */
    protected $city;

     /**
     * @var string
     */
    protected $country;

     /**
     * @var string
     */
    protected $state;

     /**
     * @var string
     */
    protected $number;

     /**
     * @var string
     */
    protected $complement;

    /**
     * @var string
     */
    protected $apartment;

     /**
     * @var string
     */
    protected $floor;
}
