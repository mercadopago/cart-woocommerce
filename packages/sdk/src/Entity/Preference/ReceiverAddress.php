<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class ReceiverAddress
 *
 * @property string $apartment
 * @property string $city_name
 * @property string $floor
 * @property string $state_name
 * @property string $street_name
 * @property int $street_number
 * @property string $zip_code
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class ReceiverAddress extends AbstractEntity
{
    /**
     * @var string
     */
    protected $apartment;

    /**
     * @var string
     */
    protected $city_name;

    /**
     * @var string
     */
    protected $floor;

    /**
     * @var string
     */
    protected $state_name;

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
