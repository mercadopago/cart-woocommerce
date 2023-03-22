<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class PaymentDetails
 *
 * @property float $id
 * @property string $status
 * @property string $status_detail
 * @property string $payment_type_id
 * @property string $payment_method_id
 * @property float $total_amount
 * @property float $paid_amount
 * @property float $coupon_amount
 * @property float $shipping_cost
 * @property RefundList $refunds

 * @package MercadoPago\PP\Sdk\Entity\Notification
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
     * @var PaymentMethodInfo
     */
    protected $payment_method_info;

    /**
     * @var RefundList
     */
    protected $refunds;

    /**
     * PaymentDetails constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->refunds = new RefundList($manager);
    }
}
