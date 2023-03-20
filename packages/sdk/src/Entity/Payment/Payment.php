<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Interfaces\RequesterEntityInterface;

/**
 * Class Payment
 *
 * @property string $session_id
 * @property string $description
 * @property string $external_reference
 * @property string $notification_url
 * @property int $installments
 * @property double $transaction_amount
 * @property string $payment_method_id
 * @property string $statement_descriptor
 * @property boolean $binary_mode
 * @property string $date_of_expiration
 * @property string $callback_url
 * @property string $token
 * @property string $issuer_id
 * @property string $campaign_id
 * @property double $coupon_amount
 * @property string $coupon_code
 * @property Payer $payer
 * @property AdditionalInfo $additional_info
 * @property TransactionDetails $transaction_details
 * @property PointOfInteraction $point_of_interaction
 * @property object $metadata
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Payment extends AbstractEntity implements RequesterEntityInterface
{
    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $external_reference;

    /**
     * @var string
     */
    protected $notification_url;

    /**
     * @var int
     */
    protected $installments;

    /**
     * @var double
     */
    protected $transaction_amount;

    /**
     * @var string
     */
    protected $payment_method_id;

    /**
     * @var string
     */
    protected $statement_descriptor;

    /**
     * @var boolean
     */
    protected $binary_mode;

    /**
     * @var string
     */
    protected $date_of_expiration;

    /**
     * @var string
     */
    protected $callback_url;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $issuer_id;

    /**
     * @var string
     */
    protected $campaign_id;

    /**
     * @var double
     */
    protected $coupon_amount;

    /**
     * @var string
     */
    protected $coupon_code;

    /**
     * @var Payer
     */
    protected $payer;

    /**
     * @var AdditionalInfo
     */
    protected $additional_info;

    /**
     * @var TransactionDetails
     */
    protected $transaction_details;

    /**
     * @var PointOfInteraction
     */
    protected $point_of_interaction;

    /**
     * @var object
     */
    protected $metadata;

    /**
     * @var string
     */
    protected $session_id;

    /**
     * Payment constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->payer = new Payer($manager);
        $this->additional_info = new AdditionalInfo($manager);
        $this->transaction_details = new TransactionDetails($manager);
        $this->point_of_interaction = new PointOfInteraction($manager);
    }

    /**
     * Exclude properties from entity building.
     *
     * @return void
     */
    public function setExcludedProperties()
    {
        $this->excluded_properties = ['session_id'];
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
            'save' => ['x-meli-session-id: ' . $this->session_id],
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
            'post' => '/v1/asgard/payments',
        );
    }
}
