<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Payment
 *
 * @package MercadoPago\PP\Sdk\Entity
 */
class Payment extends AbstractEntity
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
     * @var Date|string
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

    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->payer = new Payer();
        $this->additional_info = new AdditionalInfo();
        $this->transaction_details = new TransactionDetails();
        $this->point_of_interaction = new PointOfInteraction();
    }

    /**
     * @return array
     */
    public function getUris()
    {
        $uris = array(
            'post' => '/v1/asgard/payments',
        );

        return $uris;
    }
}
