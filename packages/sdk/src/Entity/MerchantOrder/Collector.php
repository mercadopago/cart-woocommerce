<?php

namespace MercadoPago\PP\Sdk\Entity\MerchantOrder;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Collector
 *
 * @property int $id
 * @property string $email
 * @property string $nickname
 *
 * @package MercadoPago\PP\Sdk\Entity\MerchantOrder
 */
class Collector extends AbstractEntity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $nickname;
}
