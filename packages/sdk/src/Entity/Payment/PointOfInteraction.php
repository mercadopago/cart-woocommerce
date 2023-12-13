<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class PointOfInteraction
 *
 * @property string $type
 * @property string $sub_type
 * @property string $linked_to
 * @property ApplicationData $application_data
 * @property TransactionData $transaction_data
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class PointOfInteraction extends AbstractEntity
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $sub_type;

    /**
     * @var string
     */
    protected $linked_to;

    /**
     * @var ApplicationData
     */
    protected $application_data;

    /**
     * @var TransactionData
     */
    protected $transaction_data;

    /**
     * Payer constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->application_data = new ApplicationData($manager);
        $this->transaction_data = new TransactionData($manager);
    }
}
