<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Notification
 *
 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class Notification extends AbstractEntity
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
     * Get uris
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
