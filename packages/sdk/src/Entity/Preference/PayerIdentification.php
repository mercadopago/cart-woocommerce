<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class PayerIdentification
 *
 * @property string $number
 * @property string $type
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class PayerIdentification extends AbstractEntity
{
    /**
     * @var string
     */
    protected $number;

    /**
     * @var string
     */
    protected $type;
}
