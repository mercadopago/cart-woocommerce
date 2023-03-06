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
    private const COMMISSION = 'Mercado Pago: comission';

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
     * @param $order
     * @param string|array $value
     */
    public function setIsProductionModeData($order, $value): void
    {
        $this->orderMeta->setData($order, self::IS_PRODUCTION_MODE, $value);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setUsedGatewayData($order, $value): void
    {
        $this->orderMeta->setData($order, self::USED_GATEWAY, $value);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setUsedGatewayPost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::USED_GATEWAY, $metaValue);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setDiscountData($order, $value): void
    {
        $this->orderMeta->setData($order, self::DISCOUNT, $value);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setDiscountPost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::DISCOUNT, $metaValue);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setCommissionData($order, $value): void
    {
        $this->orderMeta->setData($order, self::COMMISSION, $value);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setCommissionPost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::COMMISSION, $metaValue);
    }

    /**
     * @param $order
     *
     * @return mixed|string
     */
    public function getInstallmentsMeta($order)
    {
        return $this->orderMeta->get($order, self::MP_INSTALLMENTS);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function addInstallmentsData($order, $value): void
    {
        $this->orderMeta->addData($order, self::MP_INSTALLMENTS, $value);
    }

    /**
     * @param $order
     *
     * @return mixed|string
     */
    public function getTransactionDetailsMeta($order)
    {
        return $this->orderMeta->get($order, self::MP_TRANSACTION_DETAILS);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function addTransactionDetailsData($order, string $value): void
    {
        $this->orderMeta->addData($order, self::MP_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param $order
     *
     * @return mixed|string
     */
    public function getTransactionAmountMeta($order)
    {
        return $this->orderMeta->get($order, self::MP_TRANSACTION_AMOUNT);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed|string
     */
    public function getTransactionAmountPost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::MP_TRANSACTION_AMOUNT, $single);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function addTransactionAmountData($order, $value): void
    {
        $this->orderMeta->addData($order, self::MP_TRANSACTION_AMOUNT, $value);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setTransactionAmountData($order, $value): void
    {
        $this->orderMeta->setData($order, self::MP_TRANSACTION_AMOUNT, $value);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setTransactionAmountPost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::MP_TRANSACTION_AMOUNT, $metaValue);
    }

    /**
     * @param $order
     *
     * @return mixed|string
     */
    public function getTotalPaidAmountMeta($order)
    {
        return $this->orderMeta->get($order, self::MP_TOTAL_PAID_AMOUNT);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function addTotalPaidAmountData($order, $value): void
    {
        $this->orderMeta->addData($order, self::MP_TOTAL_PAID_AMOUNT, $value);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed|string
     */
    public function getPaymentIdsPost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::PAYMENTS_IDS, $single);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setPaymentIdsPost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::PAYMENTS_IDS, $metaValue);
    }

    /**
     * @param $order
     *
     * @return mixed|string
     */
    public function getTicketTransactionDetailsMeta($order)
    {
        return $this->orderMeta->get($order, self::TICKET_TRANSACTION_DETAILS);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed|string
     */
    public function getTicketTransactionDetailsPost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::TICKET_TRANSACTION_DETAILS, $single);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setTicketTransactionDetailsData($order, $value): void
    {
        $this->orderMeta->setData($order, self::TICKET_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setTicketTransactionDetailsPost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::TICKET_TRANSACTION_DETAILS, $metaValue);
    }

    /**
     * @param $order
     *
     * @return mixed|string
     */
    public function getPixQrBase64Meta($order)
    {
        return $this->orderMeta->get($order, self::MP_PIX_QR_BASE_64);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed|string
     */
    public function getPixQrBase64Post(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::MP_PIX_QR_BASE_64, $single);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setPixQrBase64Data($order, $value): void
    {
        $this->orderMeta->setData($order, self::MP_PIX_QR_BASE_64, $value);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setPixQrBase64Post(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::MP_PIX_QR_BASE_64, $metaValue);
    }

    /**
     * @param $order
     *
     * @return mixed|string
     */
    public function getPixQrCodeMeta($order)
    {
        return $this->orderMeta->get($order, self::MP_PIX_QR_CODE);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed|string
     */
    public function getPixQrCodePost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::MP_PIX_QR_CODE, $single);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setPixQrCodePost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::MP_PIX_QR_CODE, $metaValue);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setPixQrCodeData($order, $value): void
    {
        $this->orderMeta->setData($order, self::MP_PIX_QR_CODE, $value);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed|string
     */
    public function getPixExpirationDatePost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::PIX_EXPIRATION_DATE, $single);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setPixExpirationDatePost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::PIX_EXPIRATION_DATE, $metaValue);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setPixExpirationDateData($order, $value): void
    {
        $this->orderMeta->setData($order, self::PIX_EXPIRATION_DATE, $value);
    }

    /**
     * @param int $postId
     * @param bool $single
     *
     * @return mixed|string
     */
    public function getPixOnPost(int $postId, bool $single = false)
    {
        return $this->orderMeta->getPost($postId, self::PIX_ON, $single);
    }

    /**
     * @param int $postId
     * @param $metaValue
     */
    public function setPixOnPost(int $postId, $metaValue): void
    {
        $this->orderMeta->setPost($postId, self::PIX_ON, $metaValue);
    }

    /**
     * @param $order
     * @param string|array $value
     */
    public function setPixOnData($order, $value): void
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
            if (0 === $paymentIdMetadata) {
                $this->setPaymentIdsPost($orderId, implode(', ', $paymentsId));
            }

            foreach ($paymentsId as $paymentId) {
                $paymentDetailKey = 'Mercado Pago - Payment ' . $paymentId;
                $paymentDetailMetadata = count($this->orderMeta->getPost($orderId, $paymentDetailKey));

                if (0 === $paymentDetailMetadata) {
                    $this->orderMeta->setPost(
                        $orderId,
                        $paymentDetailKey,
                        '[Date ' . gmdate('Y-m-d H:i:s') . ']'
                    );
                }
            }
        }
    }
}
