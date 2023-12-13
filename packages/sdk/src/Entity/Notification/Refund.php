<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Refund
 *
 * @property int $id
 * @property string $status
 * @property bool $notifying
 * @property array $metadata
 *
 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class Refund extends AbstractEntity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var bool
     */
    protected $notifying;

    /**
     * @var array
     */
    protected $metadata;
}
