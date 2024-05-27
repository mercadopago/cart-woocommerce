<?php

namespace MercadoPago\PP\Sdk\Entity\Monitoring;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Platform
 *
 * @property string $name
 * @property string $version
 * @property string $uri
 * @property string $url
 *
 * @package MercadoPago\PP\Sdk\Entity\Monitoring
 */
class Platform extends AbstractEntity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var string
     */
    protected $url;
}
