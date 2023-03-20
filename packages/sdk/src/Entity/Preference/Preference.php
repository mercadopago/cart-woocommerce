<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Interfaces\RequesterEntityInterface;

/**
 * Class Preference
 *
 * @property string $additional_info
 * @property string $auto_return
 * @property BackUrl $back_urls
 * @property boolean $binary_mode
 * @property string $date_of_expiration
 * @property object $differential_pricing
 * @property string $expiration_date_from
 * @property string $expiration_date_to
 * @property boolean $expires
 * @property string $external_reference
 * @property ItemList $items
 * @property string $marketplace
 * @property float $marketplace_fee
 * @property object $metadata
 * @property string $notification_url
 * @property Payer $payer
 * @property PaymentMethod $payment_methods
 * @property string $purpose
 * @property Shipment $shipments
 * @property string $sponsor_id
 * @property string $statement_descriptor
 * @property TrackList $tracks
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Preference extends AbstractEntity implements RequesterEntityInterface
{
    /**
     * @var string
     */
    protected $additional_info;

    /**
     * @var string
     */
    protected $auto_return;

    /**
     * @var BackUrl
     */
    protected $back_urls;

    /**
     * @var boolean
     */
    protected $binary_mode;

    /**
     * @var string
     */
    protected $date_of_expiration;

    /**
     * @var object
     */
    protected $differential_pricing;

    /**
     * @var string
     */
    protected $expiration_date_from;

    /**
     * @var string
     */
    protected $expiration_date_to;

    /**
     * @var boolean
     */
    protected $expires;

    /**
     * @var string
     */
    protected $external_reference;

    /**
     * @var ItemList
     */
    protected $items;

    /**
     * @var string
     */
    protected $marketplace;

    /**
     * @var float
     */
    protected $marketplace_fee;

    /**
     * @var object
     */
    protected $metadata;

    /**
     * @var string
     */
    protected $notification_url;

    /**
     * @var Payer
     */
    protected $payer;

    /**
     * @var PaymentMethod
     */
    protected $payment_methods;

    /**
     * @var string
     */
    protected $purpose;

    /**
     * @var Shipment
     */
    protected $shipments;

    /**
     * @var string
     */
    protected $sponsor_id;

    /**
     * @var string
     */
    protected $statement_descriptor;

    /**
     * @var TrackList
     */
    protected $tracks;

    /**
     * Preference constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->back_urls            = new BackUrl($manager);
        $this->items                = new ItemList($manager);
        $this->payer                = new Payer($manager);
        $this->payment_methods      = new PaymentMethod($manager);
        $this->shipments            = new Shipment($manager);
        $this->tracks               = new TrackList($manager);
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
            'post' => '/v1/asgard/preferences',
        );
    }
}
