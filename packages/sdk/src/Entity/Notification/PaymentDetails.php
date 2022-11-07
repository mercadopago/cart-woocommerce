<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class PaymentDetails
 *
 * @package MercadoPago\PP\Sdk\Entity
 */
class PaymentDetails extends AbstractEntity
{
    /**
     * @var float
     */
    protected $id;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $status_detail;

    /**
     * @var string
     */
    protected $payment_type_id;

    /**
     * @var string
     */
    protected $payment_method_id;

    /**
     * @var float
     */
    protected $total_amount;

    /**
     * @var float
     */
    protected $paid_amount;

    /**
     * @var float
     */
    protected $coupon_amount;

    /**
     * @var float
     */
    protected $shipping_cost;

    /**
     * @var PaymentDetailsRefund
     */
    protected $refunds;

    public function __construct()
    {
        $this->refunds = new RefundList();
    }
}
