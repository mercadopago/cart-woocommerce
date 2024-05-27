<?php

namespace MercadoPago\PP\Sdk\Entity\Monitoring;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Handles integration with the Core Monitor service.
 *
 * Core Monitor is responsible for handling logs, errors and monitoring from the
 * Plugins and Platforms applications.
 *
 * @property string $flow
 * @property string $user_agent
 * @property string $message
 * @property string $stacktrace
 * @property string $location
 * @property string $platform_version
 * @property string $module_version
 * @property string $runtime_version
 * @property string $os_version
 * @property string $browser_version
 * @property string $uri
 * @property string $url
 * @property array $details
 *
 * @package MercadoPago\PP\Sdk\Entity\RegisterErrorLog
 */

class RegisterErrorLog extends AbstractEntity
{
    /**
     * @var string
     */
    protected $flow;

    /**
     * @var string
     */
    protected $user_agent;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var string
     */
    protected $stacktrace;

    /**
     * @var string
     */
    protected $location;

    /**
     * @var string
     */
    protected $platform_version;

    /**
     * @var string
     */
    protected $module_version;

    /**
     * @var string
     */
    protected $runtime_version;

    /**
     * @var string
     */
    protected $os_version;

    /**
     * @var string
     */
    protected $browser_version;

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var array
     */
    protected $details;

    /**
     * Monitoring constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
    }

    /**
     * Get and set custom headers for entity.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return [
            'save' => [
                'x-flow:' . (isset($this->flow) ? $this->flow : "not identified"),
            ]
        ];
    }

    /**
     * Get uris.
     *
     * @return array
     */
    public function getUris(): array
    {
        return array(
            'post' => '/ppcore/prod/monitor/v1/event/error',
        );
    }

    /**
     * @return void The save operation is non-blocking,
     * so its response is empty returning status code 200
     */
    public function save()
    {
        return parent::save();
    }
}
