<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class TransactionData
 *
 * @property BankInfo $bank_info
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class TransactionData extends AbstractEntity
{
    /**
     * @var BankInfo
     */
    protected $bank_info;

    /**
     * @var string
     */
    protected $qr_code_base64;

    /**
     * @var string
     */
    protected $qr_code;

    /**
     * TransactionData constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->bank_info = new BankInfo($manager);
    }
}
