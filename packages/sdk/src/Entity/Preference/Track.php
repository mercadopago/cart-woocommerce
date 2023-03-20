<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Track
 *
 * @property string $type
 * @property string $values
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Track extends AbstractEntity
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $values;
}
