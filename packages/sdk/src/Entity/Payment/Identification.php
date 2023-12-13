<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Identification
 *
 * @property string $type
 * @property string $number
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Identification extends AbstractEntity
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $number;
}
