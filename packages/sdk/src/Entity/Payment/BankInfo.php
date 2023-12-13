<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class BankInfo
 *
 * @property string $origin_bank_id
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class BankInfo extends AbstractEntity
{
    /**
     * @var string
     */
    protected $origin_bank_id;
}
