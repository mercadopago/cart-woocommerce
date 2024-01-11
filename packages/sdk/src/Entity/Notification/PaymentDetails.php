<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class PaymentDetails
 *
 * @property int $id
 * @property string $payment_method_id
 * @property PaymentMethodInfo $payment_method_info
 * @property string $payment_type_id
 * @property float $total_amount
 * @property float $paid_amount
 * @property float $shipping_cost
 * @property float $coupon_amount
 * @property string $status
 * @property string $status_detail
 * @property RefundList $refunds

 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class PaymentDetails extends AbstractEntity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $payment_method_id;

    /**
     * @var PaymentMethodInfo
     */
    protected $payment_method_info;

    /**
     * @var string
     */
    protected $payment_type_id;

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
    protected $shipping_cost;

    /**
     * @var float
     */
    protected $coupon_amount;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $status_detail;

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
