<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Address
 *
 * @property string $street_name
 * @property int $street_number
 * @property string $zip_code
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Address extends AbstractEntity
{
    /**
     * @var string
     */
    protected $street_name;

    /**
     * @var int
     */
    protected $street_number;

    /**
     * @var string
     */
    protected $zip_code;
}
