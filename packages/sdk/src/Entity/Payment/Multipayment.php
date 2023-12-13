<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Multipayment
 *
 * @property TransactionInfoList $transaction_info
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Multipayment extends Payment
{
    /**
     * @var TransactionInfoList
     */
    protected $transaction_info;

    /**
     * Multipayment constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->transaction_info = new TransactionInfoList($manager);
    }
}
