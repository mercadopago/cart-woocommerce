<?php

namespace MercadoPago\Woocommerce\Order;

use MercadoPago\Woocommerce\Hooks\OrderMeta;

if (!defined('ABSPATH')) {
    exit;
}

class OrderMetadata
{
    /**
     * @const
     */
    private const IS_PRODUCTION_MODE = 'is_production_mode';

    /**
     * @const
     */
    private const USED_GATEWAY = '_used_gateway';

    /**
     * @const
     */
    private const DISCOUNT = 'Mercado Pago: discount';

    /**
     * @const
     */
    private const COMMISSION = 'Mercado Pago: commission';

    /**
     * @const
     */
    private const MP_INSTALLMENTS = 'mp_installments';

    /**
     * @const
     */
    private const MP_TRANSACTION_DETAILS = 'mp_transaction_details';

    /**
     * @const
     */
    private const MP_TRANSACTION_AMOUNT = 'mp_transaction_amount';

    /**
     * @const
     */
    private const MP_TOTAL_PAID_AMOUNT = 'mp_total_paid_amount';

    /**
     * @const
     */
    private const PAYMENTS_IDS = '_Mercado_Pago_Payment_IDs';

    /**
     * @const
     */
    private const TICKET_TRANSACTION_DETAILS = '_transaction_details_ticket';

    /**
     * @const
     */
    private const MP_PIX_QR_BASE_64 = 'mp_pix_qr_base64';

    /**
     * @const
     */
    private const MP_PIX_QR_CODE = 'mp_pix_qr_code';

    /**
     * @const
     */
    private const PIX_EXPIRATION_DATE = 'checkout_pix_date_expiration';

    /**
     * @const
     */
    private const PIX_ON = 'pix_on';

    /**
     * @var OrderMeta
     */
    private $orderMeta;

    /**
     * Metadata constructor
     */
    public function __construct(OrderMeta $orderMeta)
    {
        $this->orderMeta = $orderMeta;
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setIsProductionModeData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::IS_PRODUCTION_MODE, $value);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setUsedGatewayData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::USED_GATEWAY, $value);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setUsedGatewayPost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::USED_GATEWAY, $value);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setDiscountData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::DISCOUNT, $value);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setDiscountPost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::DISCOUNT, $value);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setCommissionData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::COMMISSION, $value);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setCommissionPost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::COMMISSION, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getInstallmentsMeta(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_INSTALLMENTS);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function addInstallmentsData(\WC_Order $order, $value): void
    {
        $this->orderMeta->addData($order, self::MP_INSTALLMENTS, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getTransactionDetailsMeta(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_TRANSACTION_DETAILS);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function addTransactionDetailsData(\WC_Order $order, string $value): void
    {
        $this->orderMeta->addData($order, self::MP_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getTransactionAmountMeta(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_TRANSACTION_AMOUNT);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed
     */
    public function getTransactionAmountPost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::MP_TRANSACTION_AMOUNT, $single);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function addTransactionAmountData(\WC_Order $order, $value): void
    {
        $this->orderMeta->addData($order, self::MP_TRANSACTION_AMOUNT, $value);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setTransactionAmountData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::MP_TRANSACTION_AMOUNT, $value);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setTransactionAmountPost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::MP_TRANSACTION_AMOUNT, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getTotalPaidAmountMeta(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_TOTAL_PAID_AMOUNT);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function addTotalPaidAmountData(\WC_Order $order, $value): void
    {
        $this->orderMeta->addData($order, self::MP_TOTAL_PAID_AMOUNT, $value);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed
     */
    public function getPaymentIdsPost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::PAYMENTS_IDS, $single);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setPaymentIdsPost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::PAYMENTS_IDS, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getTicketTransactionDetailsMeta(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::TICKET_TRANSACTION_DETAILS);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed
     */
    public function getTicketTransactionDetailsPost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::TICKET_TRANSACTION_DETAILS, $single);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setTicketTransactionDetailsData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::TICKET_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setTicketTransactionDetailsPost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::TICKET_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getPixQrBase64Meta(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_PIX_QR_BASE_64);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed
     */
    public function getPixQrBase64Post(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::MP_PIX_QR_BASE_64, $single);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixQrBase64Data(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::MP_PIX_QR_BASE_64, $value);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setPixQrBase64Post(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::MP_PIX_QR_BASE_64, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getPixQrCodeMeta(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_PIX_QR_CODE);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed
     */
    public function getPixQrCodePost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::MP_PIX_QR_CODE, $single);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setPixQrCodePost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::MP_PIX_QR_CODE, $value);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixQrCodeData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::MP_PIX_QR_CODE, $value);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed
     */
    public function getPixExpirationDatePost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::PIX_EXPIRATION_DATE, $single);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setPixExpirationDatePost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::PIX_EXPIRATION_DATE, $value);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     */
    public function setPixExpirationDateData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::PIX_EXPIRATION_DATE, $value);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed
     */
    public function getPixOnPost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::PIX_ON, $single);
    }

    /**
     * @param int $postId
     * @param mixed $value
     *
     * @return void
     */
    public function setPixOnPost(int $postId, $value): void
    {
        $this->orderMeta->setPost($postId, self::PIX_ON, $value);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixOnData(\WC_Order $order, $value): void
    {
        $this->orderMeta->setData($order, self::PIX_ON, $value);
    }

    /**
     * Update an order's payments metadata
     *
     * @param string $orderId
     * @param array $paymentsId
     *
     * @return void
     */
    public function updatePaymentsOrderMetadata(string $orderId, array $paymentsId)
    {
        $paymentIdMetadata = count($this->getPaymentIdsPost($orderId));

        if (count($paymentsId) > 0) {
            if ($paymentIdMetadata === 0) {
                $this->setPaymentIdsPost($orderId, implode(', ', $paymentsId));
            }

            foreach ($paymentsId as $paymentId) {
                $paymentDetailKey = 'Mercado Pago - Payment ' . $paymentId;
                $paymentDetailMetadata = count($this->orderMeta->getPost($orderId, $paymentDetailKey));

                if ($paymentDetailMetadata === 0) {
                    $this->orderMeta->setPost($orderId, $paymentDetailKey, '[Date ' . gmdate('Y-m-d H:i:s') . ']');
                }
            }
        }
    }
}
