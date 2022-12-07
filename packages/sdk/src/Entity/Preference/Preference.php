<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Preference
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Preference extends AbstractEntity
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
     * Get uris
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
