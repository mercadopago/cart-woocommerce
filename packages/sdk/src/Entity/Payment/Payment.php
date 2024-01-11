<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use Exception;
use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Constants;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Interfaces\RequesterEntityInterface;

/**
 * Handles integration with the Asgard Transaction service.
 *
 * The Asgard Transaction acts as a middleware for creating various transaction-related entities
 * such as Payments, Preferences, Point, and Transaction Intent. It orchestrates all actions
 * taken during a payment transaction. Its main responsibility is to ensure a secure intermediation
 * between P&P and MercadoPago during payment creation.
 *
 * @property string $date_of_expiration
 * @property string $operation_type
 * @property string $issuer_id
 * @property string $payment_method_id
 * @property string $description
 * @property string $sponsor_id
 * @property string $counter_currency
 * @property double $shipping_amount
 * @property string $store_id
 * @property Payer $payer
 * @property array $metadata
 * @property AdditionalInfo $additional_info
 * @property string $external_reference
 * @property double $transaction_amount
 * @property double $coupon_amount
 * @property int $differential_pricing_id
 * @property int $installments
 * @property TransactionDetails $transaction_details
 * @property bool $binary_mode
 * @property string $statement_descriptor
 * @property string $notification_url
 * @property string $processing_mode
 * @property string $merchant_account_id
 * @property PointOfInteraction $point_of_interaction
 * @property string $brand_id
 * @property string $reserve_id
 * @property array $collector
 * @property string $callback_url
 * @property double $application_fee
 * @property int $campaign_id
 * @property bool $capture
 * @property string $coupon_code
 * @property string $token
 * @property string $session_id
 * @property array $customHeader
 * @property string $three_d_secure_mode
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Payment extends AbstractEntity implements RequesterEntityInterface
{
    /**
     * @var string
     */
    protected $date_of_expiration;

    /**
     * @var string
     */
    protected $operation_type;

    /**
     * @var string
     */
    protected $issuer_id;

    /**
     * @var string
     */
    protected $payment_method_id;

    /**
     * @var string
     */
    protected $three_d_secure_mode;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $sponsor_id;

    /**
     * @var string
     */
    protected $counter_currency;

    /**
     * @var double
     */
    protected $shipping_amount;

    /**
     * @var string
     */
    protected $store_id;

    /**
     * @var Payer
     */
    protected $payer;

    /**
     * @var array
     */
    protected $metadata;

    /**
     * @var AdditionalInfo
     */
    protected $additional_info;

    /**
     * @var string
     */
    protected $external_reference;

    /**
     * @var double
     */
    protected $transaction_amount;

    /**
     * @var double
     */
    protected $coupon_amount;

    /**
     * @var int
     */
    protected $differential_pricing_id;

    /**
     * @var int
     */
    protected $installments;

    /**
     * @var TransactionDetails
     */
    protected $transaction_details;

    /**
     * @var bool
     */
    protected $binary_mode;

    /**
     * @var string
     */
    protected $statement_descriptor;

    /**
     * @var string
     */
    protected $notification_url;

    /**
     * @var string
     */
    protected $processing_mode;

    /**
     * @var string
     */
    protected $merchant_account_id;

    /**
     * @var PointOfInteraction
     */
    protected $point_of_interaction;

    /**
     * @var string
     */
    protected $brand_id;

    /**
     * @var string
     */
    protected $reserve_id;

    /**
     * @var array
     */
    protected $collector;

    /**
     * @var string
     */
    protected $callback_url;

    /**
     * @var double
     */
    protected $application_fee;

    /**
     * @var int
     */
    protected $campaign_id;

    /**
     * @var bool
     */
    protected $capture;

    /**
     * @var string
     */
    protected $coupon_code;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $session_id;

     /**
     * @var array
     */
    private $customHeader;

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
            'save' => isset($this->customHeader)
                        ? array_merge(['x-meli-session-id: ' . $this->session_id], $this->customHeader)
                        : ['x-meli-session-id: ' . $this->session_id],
        ];
    }
    /**
     * Set custom headers for entity.
     *
     * @return array
     */
    public function setCustomHeaders(array $customHeader = [])
    {
        return $this->customHeader = $customHeader;
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

    /**
     *  Addition of the 3DS validation layer. Possible values are as follows:
     * - not_supported: 3DS should not be used (it is the default value).
     * - optional: 3DS may or may not be required, depending on the risk profile of the operation.
     * - mandatory: 3DS must be required, depending on the risk profile of the operation.
     */

    public function validateThreeDSecureMode()
    {

        $validation = Constants::THREE_DS_VALID_OPTIONS;
        if (in_array(strtolower($this->three_d_secure_mode), $validation)) {
            return $this->save();
        }

        throw new Exception("Invalid value for field three_d_secure_mode");
    }

    /**
     * Creates a payment using the Asgard Transaction service API.
     *
     * To execute this method, it is essential to provide the payment request payload. The payload includes
     * properties such as 'paymentMethodId', 'description', 'transactionAmount', among others.
     * The method returns details of the created payment, including fields like 'id', 'status',
     * 'applicationId', 'dateCreated', etc.
     *
     * Internally, this method makes a call to the Asgard Transaction API, supplying the required parameters:
     * Payment creation payload, Platform Identifier, and Access Token.
     *
     * Note: This method is inherited from the parent class but specialized for payments.
     *
     * @return mixed The result of the save operation, typically an instance of this Payment
     * class populated with the created data.
     * @throws \Exception Throws an exception if something goes wrong during the save operation.
     */
    public function save()
    {
        return parent::save();
    }
}
