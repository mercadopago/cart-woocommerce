<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Interfaces\RequesterEntityInterface;

/**
 * Handles integration with the Asgard Notification service.
 *
 * Asgard Notification is responsible for handling notifications originating from plugins and platforms.
 * When a payment is initiated that requires asynchronous approval, a notification is generated and processed
 * by this class.
 * This class streamlines the interaction with the Asgard Notification service, allowing the details of a
 * specific notification to be fetched using its ID. It aims to simplify the complexity associated with
 * managing and validating notifications, ensuring that only relevant information is forwarded to
 * platforms and plugins.
 *
 * @property string $notification_id
 * @property string $notification_url
 * @property string $status
 * @property string $transaction_id
 * @property string $transaction_type
 * @property string $platform_id
 * @property string $external_reference
 * @property string $preference_id
 * @property float $transaction_amount
 * @property float $total_paid
 * @property float $total_approved
 * @property float $total_pending
 * @property float $total_refunded
 * @property float $total_rejected
 * @property float $total_cancelled
 * @property float $total_charged_back
 * @property string $multiple_payment_transaction_id
 * @property array $payments_metadata
 * @property PaymentDetailsList $payments_details
 * @property RefundNotifyingList $refunds_notifying
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
    protected $transaction_id;

    /**
     * @var string
     */
    protected $transaction_type;

    /**
     * @var string
     */
    protected $platform_id;

    /**
     * @var string
     */
    protected $external_reference;

    /**
     * @var string
     */
    protected $preference_id;

    /**
     * @var float
     */
    protected $transaction_amount;

    /**
     * @var float
     */
    protected $total_paid;

    /**
     * @var float
     */
    protected $total_approved;

    /**
     * @var float
     */
    protected $total_pending;

    /**
     * @var float
     */
    protected $total_refunded;

    /**
     * @var float
     */
    protected $total_rejected;

    /**
     * @var float
     */
    protected $total_cancelled;

    /**
     * @var float
     */
    protected $total_charged_back;

    /**
     * @var string
     */
    protected $multiple_payment_transaction_id;

    /**
     * @var array
     */
    protected $payments_metadata;

    /**
     * @var PaymentDetailsList
     */
    protected $payments_details;

    /**
     * @var RefundNotifyingList
     */
    protected $refunds_notifying;

    /**
     * Notification constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->payments_details = new PaymentDetailsList($manager);
        $this->refunds_notifying = new RefundNotifyingList($manager);
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
            'get' => '/v1/asgard/notification/:id',
        );
    }

    /**
     * Retrieves a notification from the Asgard Transaction service.
     *
     * Upon invoking this method, a request is made to the Asgard Transaction service
     * using the provided notification ID. Authentication is performed using
     * the seller's access token, which should be previously configured in the default headers.
     *
     * Note: This method is inherited from the parent class but specialized for notifications.
     *
     * @param array $params Associative array containing the parameters for the read operation.
     *                      It expects an "id" key with the notification ID as its value.
     *                      Example: $notification->read(['id' => 'P-1316643861'])
     *
     * @return mixed The result of the read operation, typically an instance of
     *               this Notification class populated with the retrieved data.
     *
     * @throws \Exception Throws an exception if something goes wrong during the read operation.
     */
    public function read(array $params = [])
    {
        return parent::read($params);
    }
}
