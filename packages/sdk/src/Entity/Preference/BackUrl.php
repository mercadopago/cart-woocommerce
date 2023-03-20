<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class BackUrl
 *
 * @property string $failure
 * @property string $pending
 * @property string $success
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class BackUrl extends AbstractEntity
{
    /**
     * @var string
     */
    protected $failure;

    /**
     * @var string
     */
    protected $pending;

    /**
     * @var string
     */
    protected $success;
}
