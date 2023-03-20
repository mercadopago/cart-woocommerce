<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Interfaces\RequesterEntityInterface;

/**
 * Class Notification
 *
 * @property string $ip_address
 * @property string $notification_id
 * @property string $notification_url
 * @property string $status
 * @property string $external_reference
 * @property float $transaction_id
 * @property string $transaction_type
 * @property float $transaction_amount
 * @property float $total_pending
 * @property float $total_approved
 * @property float $total_paid
 * @property float $total_rejected
 * @property float $total_refunded
 * @property float $total_cancelled
 * @property float $total_charged_back
 * @property array $payments_metadata
 * @property PaymentDetails $payments_details
 *
 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class Notification extends AbstractEntity implements RequesterEntityInterface
{
    /**
     * @var string
     */
    protected $notification_id;

    /**
     * @var string
     */
    protected $notification_url;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $external_reference;

    /**
     * @var float
     */
    protected $transaction_id;

    /**
     * @var string
     */
    protected $transaction_type;

    /**
     * @var float
     */
    protected $transaction_amount;

    /**
     * @var float
     */
    protected $total_pending;

    /**
     * @var float
     */
    protected $total_approved;

    /**
     * @var float
     */
    protected $total_paid;

    /**
     * @var float
     */
    protected $total_rejected;

    /**
     * @var float
     */
    protected $total_refunded;

    /**
     * @var float
     */
    protected $total_cancelled;

    /**
     * @var float
     */
    protected $total_charged_back;

    /**
     * @var array
     */
    protected $payments_metadata;

    /**
     * @var PaymentDetails
     */
    protected $payments_details;

    /**
     * Notification constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->payments_details = new PaymentDetailsList($manager);
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
            'get' => '/v1/bifrost/notification/status/:id',
        );
    }
}
