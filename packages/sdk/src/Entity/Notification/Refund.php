<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Refund
 *
 * @property float $id
 * @property string $status
 * @property bool $notifying
 * @property object $metadata
 *
 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class Refund extends AbstractEntity
{
    /**
     * @var float
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
     * @var object
     */
    protected $metadata;
}
