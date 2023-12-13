<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class ReceiverAddress
 *
 * @property string $zip_code
 * @property string $street_name
 * @property string $city_name
 * @property string $state_name
 * @property string $street_number
 * @property string $floor
 * @property string $apartment
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
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
    protected $street_name;

    /**
     * @var string
     */
    protected $city_name;

    /**
     * @var string
     */
    protected $state_name;

    /**
     * @var string
     */
    protected $street_number;

    /**
     * @var string
     */
    protected $floor;

    /**
     * @var string
     */
    protected $apartment;
}
