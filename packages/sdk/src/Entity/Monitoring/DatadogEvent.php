<?php

namespace MercadoPago\PP\Sdk\Entity\Monitoring;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Interfaces\RequesterEntityInterface;

/**
 * Handles integration with the Core Monitor service.
 *
 * The purpose of this class is to generate metrics in Datadog.
 *
 * @property string $value
 * @property string $message
 * @property string $plugin_version
 * @property Platform $platform
 * @property array $details
 *
 * @package MercadoPago\PP\Sdk\Entity\Monitoring
 */
class DatadogEvent extends AbstractEntity implements RequesterEntityInterface
{
    /**
     * @var string
     */
    protected $value;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var string
     */
    protected $plugin_version;

    /**
     * @var Platform
     */
    protected $platform;

    /**
     * @var array
     */
    protected $details;

    /**
     * DatadogEvent constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->platform = new Platform($manager);
    }

    /**
     * Exclude properties from entity building.
     *
     * @return void
     */
    public function setExcludedProperties()
    {
        $this->excluded_properties = [];
    }

    /**
     * Get and set custom headers for entity.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return [
            'read' => [],
            'save' => [],
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
            'post' => '/ppcore/prod/monitor/v1/event/datadog/:team/:event_type',
        );
    }

    /**
     * Register events on Datadog using the Core Monitor service API.
     *
     * Once called, it receives a payload with event data and records this event
     * in the metric: ppcore.event.<team>.<type>
     *
     * To execute this method, it is essential to provide the datadog_event request payload.
     *
     * The 'details' field receives a map where the key and value are of type string with additional
     * information that needs to be tagged in Datadog according to the needs of the plugin/platform.
     * This information will be tagged and made available to use in Datadog dashboards.
     *
     * Note: This method is inherited from the parent class but specialized for datadog_event.
     *
     * @param array $params Associative array containing the parameters for the register operation.
     *      It expects:
     *          - A team' key which receives the name of the team responsible for the registration.
     *            The available values ​​to be set in the team are: 'long', 'smb', 'big' and 'core';
     *          - A 'event_type' key with the type of event that will be registered in Datadog.
     *            The event_type accept only the alphanumeric, underscore and the '-' characters.
     *
     *      Example: $datadogEvent->register(array("team" => "core", "event_type" => "mp_card_form"))
     * @throws \Exception Throws an exception if something goes wrong during the register operation.
     */
    public function register(array $params = [])
    {
        return parent::saveWithParams($params);
    }
}
