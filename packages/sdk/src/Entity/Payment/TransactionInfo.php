<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class TransactionInfo
 *
 * @property float $transaction_amount
 * @property int $installments
 * @property string $token
 * @property string $payment_method_id
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class TransactionInfo extends AbstractEntity
{
    /**
     * @var float
     */
    protected $transaction_amount;

    /**
     * @var int
     */
    protected $installments;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $payment_method_id;
}
