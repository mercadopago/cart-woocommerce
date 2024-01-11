<?php

namespace MercadoPago\Woocommerce\Order;

use MercadoPago\Woocommerce\Helpers\Date;
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
     * @const
     */
    private const BLOCKS_PAYMENT = 'blocks_payment';

    /**
     * @var OrderMeta
     */
    private $orderMeta;

    /**
     * Metadata constructor
     *
     * @param OrderMeta $orderMeta
     */
    public function __construct(OrderMeta $orderMeta)
    {
        $this->orderMeta = $orderMeta;
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getUsedGatewayData(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::USED_GATEWAY);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setUsedGatewayData(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::USED_GATEWAY, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getIsProductionModeData(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::IS_PRODUCTION_MODE);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setIsProductionModeData(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::IS_PRODUCTION_MODE, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getDiscountData(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::DISCOUNT);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setDiscountData(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::DISCOUNT, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getCommissionData(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::COMMISSION);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setCommissionData(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::COMMISSION, $value);
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
    public function setInstallmentsData(\WC_Order $order, $value): void
    {
        $this->orderMeta->add($order, self::MP_INSTALLMENTS, $value);
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
     * @param string $value
     *
     * @return void
     */
    public function setTransactionDetailsData(\WC_Order $order, string $value): void
    {
        $this->orderMeta->add($order, self::MP_TRANSACTION_DETAILS, $value);
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
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setTransactionAmountData(\WC_Order $order, $value): void
    {
        $this->orderMeta->add($order, self::MP_TRANSACTION_AMOUNT, $value);
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
    public function setTotalPaidAmountData(\WC_Order $order, $value): void
    {
        $this->orderMeta->add($order, self::MP_TOTAL_PAID_AMOUNT, $value);
    }

    /**
     * @param \WC_Order $order
     * @param bool $single
     *
     * @return mixed
     */
    public function getPaymentsIdMeta(\WC_Order $order, bool $single = true)
    {
        return $this->orderMeta->get($order, self::PAYMENTS_IDS, $single);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPaymentsIdData(\WC_Order $order, $value): void
    {
        $this->orderMeta->add($order, self::PAYMENTS_IDS, $value);
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
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setTicketTransactionDetailsData(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::TICKET_TRANSACTION_DETAILS, $value);
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
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getPixOnMeta(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::PIX_ON);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixQrBase64Data(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::MP_PIX_QR_BASE_64, $value);
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
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixQrCodeData(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::MP_PIX_QR_CODE, $value);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     */
    public function setPixExpirationDateData(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::PIX_EXPIRATION_DATE, $value);
    }

    /**
     * @param \WC_Order $order
     *
     * @return mixed
     */
    public function getPixExpirationDateData(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::PIX_EXPIRATION_DATE);
    }

    /**
     * @param \WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixOnData(\WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::PIX_ON, $value);
    }

    /**
     * Set custom metadata in the order
     *
     * @param \WC_Order $order
     * @param mixed $data
     *
     * @return void
     */
    public function setCustomMetadata(\WC_Order $order, $data): void
    {
        $installments      = (float) $data['installments'];
        $installmentAmount = (float) $data['transaction_details']['installment_amount'];
        $totalPaidAmount   = (float) $data['transaction_details']['total_paid_amount'];
        $transactionAmount = (float) $data['transaction_amount'];

         $this->setInstallmentsData($order, $installments);
         $this->setTransactionDetailsData($order, $installmentAmount);
         $this->setTransactionAmountData($order, $transactionAmount);
         $this->setTotalPaidAmountData($order, $totalPaidAmount);
         $this->updatePaymentsOrderMetadata($order, [$data['id']]);

        $order->save();
    }

    /**
     * Update an order's payments metadata
     *
     * @param \WC_Order $order
     * @param array $paymentsId
     *
     * @return void
     */
    public function updatePaymentsOrderMetadata(\WC_Order $order, array $paymentsId)
    {
        $paymentsIdMetadata = $this->getPaymentsIdMeta($order);

        if (empty($paymentsIdMetadata)) {
            $this->setPaymentsIdData($order, implode(', ', $paymentsId));
        }

        foreach ($paymentsId as $paymentId) {
            $date                  = Date::getNowDate('Y-m-d H:i:s');
            $paymentDetailKey      = "Mercado Pago - Payment $paymentId";
            $paymentDetailMetadata = $this->orderMeta->get($order, $paymentDetailKey);

            if (empty($paymentDetailMetadata)) {
                $this->orderMeta->update($order, $paymentDetailKey, "[Date $date]");
            }
        }
    }

    /**
     * Update an order's payments metadata
     *
     * @param \WC_Order $order
     * @param array $paymentsId
     *
     * @return void
     */
    public function markPaymentAsBlocks(\WC_Order $order, string $value)
    {
        $this->orderMeta->update($order, self::BLOCKS_PAYMENT, $value);
    }

    /**
     * Update an order's payments metadata
     *
     * @param \WC_Order $order
     * @param array $paymentsId
     *
     * @return void
     */
    public function getPaymentBlocks(\WC_Order $order)
    {
        return $this->orderMeta->get($order, self::BLOCKS_PAYMENT);
    }
}
