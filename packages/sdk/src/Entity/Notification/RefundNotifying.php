<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class RefundNotifying
 *
 * @property int $id
 * @property bool $notifying
 * @property float $amount

 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class RefundNotifying extends AbstractEntity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var bool
     */
    protected $notifying;

    /**
     * @var float
     */
    protected $amount;
}
