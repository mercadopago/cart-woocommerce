<?php

namespace MercadoPago\PP\Sdk\Entity\MerchantOrder;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Interfaces\RequesterEntityInterface;
use MercadoPago\PP\Sdk\Entity\MerchantOrder\Collector;
use MercadoPago\PP\Sdk\Entity\Payment\Payer;
use MercadoPago\PP\Sdk\Entity\Payment\Item;

/**
 * Handles the integration with the Merchant Order service.
 *
 * This service communicates directly with the Mercado Pago API without going through Core P&P,
 * that way every merchant order created will be related to this entity,either to create or to
 * obtain a list or just a merchant order.
 *
 * @property MerchantOrder[] $elements
 * @property int $next_offset
 * @property int $total
 * @property int $id
 * @property string $status
 * @property string $external_reference
 * @property string $preference_id
 * @property array $payments
 * @property array $shipments
 * @property array $payouts
 * @property Collector $collector
 * @property string $marketplace
 * @property string $notification_url
 * @property string $date_created
 * @property string $last_updated
 * @property int $sponsor_id
 * @property string $shipping_cost
 * @property string $total_amount
 * @property string $site_id
 * @property string $paid_amount
 * @property string $refunded_amount
 * @property Payer $payer
 * @property Item[] $items
 * @property bool $cancelled
 * @property string $additional_info
 * @property int $application_id
 * @property bool $is_test
 * @property string $order_status
 *
 * @package MercadoPago\PP\Sdk\Entity\MerchantOrder
 */
class MerchantOrder extends AbstractEntity implements RequesterEntityInterface
{
    /**
     * @var MerchantOrderResponse[]
     */
    protected $elements;

    /**
     * @var int
     */
    protected $next_offset;

    /**
     * @var int
     */
    protected $total;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $external_reference;

    /**
     * @var string
     */
    protected $preference_id;

    /**
     * @var array
     */
    protected $payments;

    /**
     * @var array
     */
    protected $shipments;

    /**
     * @var array
     */
    protected $payouts;

    /**
     * @var Collector
     */
    protected $collector;

    /**
     * @var string
     */
    protected $marketplace;

    /**
     * @var string
     */
    protected $notification_url;

    /**
     * @var string
     */
    protected $date_created;

    /**
     * @var string
     */
    protected $last_updated;

    /**
     * @var int
     */
    protected $sponsor_id;

    /**
     * @var string
     */
    protected $shipping_cost;

    /**
     * @var string
     */
    protected $total_amount;

    /**
     * @var string
     */
    protected $site_id;

    /**
     * @var string
     */
    protected $paid_amount;

    /**
     * @var string
     */
    protected $refunded_amount;

    /**
     * @var Payer
     */
    protected $payer;

    /**
     * @var Item[]
     */
    protected $items;

    /**
     * @var bool
     */
    protected $cancelled;

    /**
     * @var string
     */
    protected $additional_info;

    /**
     * @var int
     */
    protected $application_id;

    /**
     * @var bool
     */
    protected $is_test;

    /**
     * @var string
     */
    protected $order_status;

    /**
     * MerchantOrder constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
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
            'get' => '/merchant_orders/:id'
        );
    }

    /**
     * Returns all Merchant Orders linked to the provided access token
     *
     * Upon invoking this method, a request is made to the Merchant Orders API.
     * Authentication is performed using the seller's access token, which should
     * be previously configured in the default headers.
     *
     * @return MerchantOrder[] List of Merchant Orders linked to the provided access token.
     *
     * @throws \Exception Throws an exception if something goes wrong during the read operation.
     */
    public function getMerchantOrders()
    {
        $response = parent::read(['id' => ''], [], true);

        $this->elements = $response->elements;

        return $this->elements;
    }

    /**
     * Returns a Merchant Order by its ID
     *
     * Upon invoking this method, a request is made to the Merchant Orders API
     * using the provided Merchant Order ID. Authentication is performed using
     * the seller's access token, which should be previously configured in the default headers.
     *
     * @param string $id The ID of the Merchant Order to be retrieved.
     *
     * @return MerchantOrder The Merchant Order linked to the provided access token and ID.
     *
     * @throws \Exception Throws an exception if something goes wrong during the read operation.
     */
    public function getMerchantOrder(string $id = null)
    {
        $response = parent::read(['id' => $id], [], true);

        $this->setEntity($response);

        return $this;
    }
}
