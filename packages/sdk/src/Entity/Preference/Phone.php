<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Phone
 *
 * @property string $area_code
 * @property string $number
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Phone extends AbstractEntity
{
    /**
     * @var string
     */
    protected $area_code;

    /**
     * @var string
     */
    protected $number;
}
