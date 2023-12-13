<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class ApplicationData
 *
 * @property string $name
 * @property string $version
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class ApplicationData extends AbstractEntity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $version;
}
