<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class MultipaymentV2
 *
 * @property TransactionInfoList $transaction_info
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class MultipaymentV2 extends PaymentV2
{
    /**
     * @var TransactionInfoList
     */
    protected $transaction_info;

    /**
     * MultipaymentV2 constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->transaction_info = new TransactionInfoList($manager);
    }
}
