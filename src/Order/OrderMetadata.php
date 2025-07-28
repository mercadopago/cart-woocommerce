<?php

namespace MercadoPago\Woocommerce\Order;

use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class OrderMetadata
{
    private const IS_PRODUCTION_MODE = 'is_production_mode';

    private const USED_GATEWAY = '_used_gateway';

    private const DISCOUNT = 'Mercado Pago: discount';

    private const COMMISSION = 'Mercado Pago: commission';

    private const MP_INSTALLMENTS = 'mp_installments';

    private const MP_TRANSACTION_DETAILS = 'mp_transaction_details';

    private const MP_TRANSACTION_AMOUNT = 'mp_transaction_amount';

    private const MP_TOTAL_PAID_AMOUNT = 'mp_total_paid_amount';

    private const PAYMENTS_IDS = '_Mercado_Pago_Payment_IDs';

    private const MERCADOPAGO_PAYMENT = 'Mercado Pago - Payment';

    private const PAYMENT_DETAILS = 'PAYMENT_ID: DATE';

    private const TICKET_TRANSACTION_DETAILS = '_transaction_details_ticket';

    private const MP_PIX_QR_BASE_64 = 'mp_pix_qr_base64';

    private const MP_PIX_QR_CODE = 'mp_pix_qr_code';

    private const PIX_EXPIRATION_DATE = 'checkout_pix_date_expiration';

    private const PIX_ON = 'pix_on';

    private const BLOCKS_PAYMENT = 'blocks_payment';

    private const SYNC_CRON_ERROR = 'mp_sync_order_error_count';

    private const CHECKOUT_TYPE = 'checkout_type';

    private const CHECKOUT = 'checkout';

    private OrderMeta $orderMeta;

    private Logs $logs;

    /**
     * Metadata constructor
     *
     * @param OrderMeta $orderMeta
     * @param Logs $logs
     */
    public function __construct(OrderMeta $orderMeta, Logs $logs)
    {
        $this->orderMeta = $orderMeta;
        $this->logs = $logs;
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getUsedGatewayData(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::USED_GATEWAY);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setUsedGatewayData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::USED_GATEWAY, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getIsProductionModeData(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::IS_PRODUCTION_MODE);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setIsProductionModeData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::IS_PRODUCTION_MODE, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getDiscountData(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::DISCOUNT);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setDiscountData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::DISCOUNT, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getCommissionData(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::COMMISSION);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setCommissionData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::COMMISSION, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getInstallmentsMeta(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_INSTALLMENTS);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setInstallmentsData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::MP_INSTALLMENTS, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getTransactionDetailsMeta(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_TRANSACTION_DETAILS);
    }

    /**
     * @param WC_Order $order
     * @param string $value
     *
     * @return void
     */
    public function setTransactionDetailsData(WC_Order $order, string $value): void
    {
        $this->orderMeta->update($order, self::MP_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getTransactionAmountMeta(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_TRANSACTION_AMOUNT);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setTransactionAmountData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::MP_TRANSACTION_AMOUNT, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getTotalPaidAmountMeta(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_TOTAL_PAID_AMOUNT);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setTotalPaidAmountData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::MP_TOTAL_PAID_AMOUNT, $value);
    }

    /**
     * @param WC_Order $order
     * @param bool $single
     *
     * @return mixed
     */
    public function getPaymentsIdMeta(WC_Order $order, bool $single = true)
    {
        return $this->orderMeta->get($order, self::PAYMENTS_IDS, $single);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPaymentsIdData(WC_Order $order, $value): void
    {
        $this->orderMeta->add($order, self::PAYMENTS_IDS, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getTicketTransactionDetailsMeta(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::TICKET_TRANSACTION_DETAILS);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setTicketTransactionDetailsData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::TICKET_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getPixQrBase64Meta(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_PIX_QR_BASE_64);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getPixOnMeta(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::PIX_ON);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixQrBase64Data(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::MP_PIX_QR_BASE_64, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getPixQrCodeMeta(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::MP_PIX_QR_CODE);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixQrCodeData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::MP_PIX_QR_CODE, $value);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     */
    public function setPixExpirationDateData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::PIX_EXPIRATION_DATE, $value);
    }

    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getPixExpirationDateData(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::PIX_EXPIRATION_DATE);
    }

    /**
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setPixOnData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::PIX_ON, $value);
    }

    /**
     * Set custom metadata in the order
     *
     * @param WC_Order $order
     * @param mixed $data
     *
     * @return void
     */
    public function setCustomMetadata(WC_Order $order, $data): void
    {
        $installments = isset($data['installments']) ? (float) $data['installments'] : 0.0;
        $installmentAmount = isset($data['transaction_details']['installment_amount']) ? (float) $data['transaction_details']['installment_amount'] : 0.0;
        $totalPaidAmount = isset($data['transaction_details']['total_paid_amount']) ? (float) $data['transaction_details']['total_paid_amount'] : 0.0;
        $transactionAmount = isset($data['transaction_amount']) ? (float) $data['transaction_amount'] : 0.0;

        $this->setInstallmentsData($order, $installments);
        $this->setTransactionDetailsData($order, $installmentAmount);
        $this->setTransactionAmountData($order, $transactionAmount);
        $this->setTotalPaidAmountData($order, $totalPaidAmount);
        $this->updatePaymentsOrderMetadata($order, $data);

        $order->save();
    }

    /**
     * Update an order's payments metadata
     *
     * @param WC_Order $order
     * @param array $paymentData
     *
     * @return void
     */
    public function updatePaymentsOrderMetadata(WC_Order $order, array $paymentData): void
    {
        $this->initializePaymentMetadata($order, $paymentData);
        $this->updatePaymentDetails($order, $paymentData);
        $this->updateLatestPaymentId($order);
        $this->addFeeDetails($order, $paymentData);
        $this->setMercadoPagoPaymentId($order, [$paymentData['id']]);
    }

    /**
     * Set payment id in the order
     *
     * @param WC_Order $order
     * @param array $paymentsId [1234567890]
     *
     * @example Mercado Pago - Payment 1234567890
     *
     * @return void
     */
    public function setMercadoPagoPaymentId(WC_Order $order, array $paymentsId)
    {
        $paymentsIdMetadata = $this->getPaymentsIdMeta($order);

        if (empty($paymentsIdMetadata)) {
            $this->setPaymentsIdData($order, implode(', ', $paymentsId));
        }

        foreach ($paymentsId as $paymentId) {
            $date                  = Date::getNowDate('Y-m-d H:i:s');
            $paymentDetailKey      = self::MERCADOPAGO_PAYMENT . " $paymentId";
            $paymentDetailMetadata = $this->orderMeta->get($order, $paymentDetailKey);

            if (empty($paymentDetailMetadata)) {
                $this->orderMeta->update($order, $paymentDetailKey, "[Date $date]");
            }
        }
    }

    /**
     * Initialize payment metadata if not exists
     *
     * @param WC_Order $order
     * @param array $paymentData
     *
     * @return void
     */
    private function initializePaymentMetadata(WC_Order $order, array $paymentData): void
    {
        $paymentsIdMetadata = $this->getPaymentsIdMeta($order);

        if (empty($paymentsIdMetadata)) {
            $paymentId = $this->extractPaymentId($paymentData);
            if (!empty($paymentId)) {
                $this->setPaymentsIdData($order, $paymentId);
            }
        }
    }

    /**
     * Extract payment ID from payment data array safely
     *
     * Handles both associative arrays ['id' => '123'] and indexed arrays ['123']
     *
     * @param array $paymentData
     *
     * @return string|null
     */
    private function extractPaymentId(array $paymentData): ?string
    {
        if (isset($paymentData['id']) && !empty($paymentData['id'])) {
            return (string) $paymentData['id'];
        }

        if (isset($paymentData[0]) && !empty($paymentData[0]) && is_numeric($paymentData[0])) {
            return (string) $paymentData[0];
        }

        $this->logs->file->error('Invalid payment data format in extractPaymentId', 'OrderMetadata', $paymentData);

        return null;
    }

    /**
     * Update payment details with new payment information
     *
     * @param WC_Order $order
     * @param array $paymentData
     *
     * @return void
     */
    private function updatePaymentDetails(WC_Order $order, array $paymentData): void
    {
        $paymentDetailKey = self::PAYMENT_DETAILS;
        $paymentDetailValue = $this->formatPaymentDetail($paymentData);
        $existingMetadata = $this->orderMeta->get($order, $paymentDetailKey);

        if (!empty($existingMetadata)) {
            $paymentDetailValue = $existingMetadata . ",\n" . $paymentDetailValue;
        }

        if (!empty($paymentDetailValue)) {
            $this->orderMeta->update($order, $paymentDetailKey, $paymentDetailValue);
        }
    }

    /**
     * Format payment detail string
     *
     * @param array $paymentData
     *
     * @return string
     */
    private function formatPaymentDetail(array $paymentData): string
    {
        $paymentId = $paymentData['id'] ?? null;
        $dateCreated = $paymentData['date_created'] ?? null;

        if (empty($paymentId) || empty($dateCreated)) {
            return '';
        }

        return "{$paymentId}: {$dateCreated}";
    }

    /**
     * Update the latest payment ID in metadata
     *
     * @param WC_Order $order
     *
     * @return void
     */
    private function updateLatestPaymentId(WC_Order $order): void
    {
        $paymentDetails = $this->getPaymentDetails($order);

        if (count($paymentDetails) <= 1) {
            return;
        }

        $latestPayment = $this->findLatestPayment($paymentDetails);

        if ($latestPayment !== null) {
            $this->orderMeta->update($order, self::PAYMENTS_IDS, $latestPayment);
        }
    }

    /**
     * Add fee details to the order metadata
     *
     * @param WC_Order $order
     * @param array $paymentData
     *
     * @example mercadopago_fee: 3.3
     *
     * @return void
     */
    private function addFeeDetails(WC_Order $order, array $paymentData): void
    {
        $feeDetails = $paymentData['fee_details'] ?? [];

        if (empty($feeDetails)) {
            return;
        }

        foreach ($feeDetails as $feeDetail) {
            if (is_array($feeDetail) && isset($feeDetail['type'], $feeDetail['amount'])) {
                $this->orderMeta->update($order, $feeDetail['type'], $feeDetail['amount']);
            } else {
                $this->logs->file->error('Invalid fee detail format', 'OrderMetadata', $feeDetail);
            }
        }
    }

    /**
     * Get payment details from metadata
     *
     * @param WC_Order $order
     *
     * @return array
     */
    private function getPaymentDetails(WC_Order $order): array
    {
        $paymentDetailKey = self::PAYMENT_DETAILS;
        $paymentDetailValue = $this->orderMeta->get($order, $paymentDetailKey);

        return explode(",\n", $paymentDetailValue);
    }

    /**
     * Find the latest payment based on date
     *
     * @param array $paymentDetails
     *
     * @return string|null
     */
    private function findLatestPayment(array $paymentDetails): ?string
    {
        if (empty($paymentDetails)) {
            return '';
        }

        $latestPayment = '';
        $latestDate = '';
        foreach ($paymentDetails as $payment) {
            $parts = explode(': ', $payment);
            if (count($parts) !== 2) {
                $this->logs->file->error('Failed to get previous payments. Invalid format', 'OrderMetadata', ['payment' => $payment]);
                return null;
            }

            [$id, $date] = $parts;

            if (empty($latestDate) || strtotime($date) > strtotime($latestDate)) {
                $latestDate = $date;
                $latestPayment = $id;
            }
        }

        return $latestPayment;
    }

    /**
     * Set supertoken metadata in the order
     *
     * @param WC_Order $order
     * @param mixed $data
     * @param mixed $transactionMetadata
     *
     * @return void
     */
    public function setSupertokenMetadata(WC_Order $order, $data, $transactionMetadata): void
    {
        if (isset($data['installments']) && isset($data['transaction_details']['installment_amount']) && $data['transaction_details']['installment_amount'] > 0) {
            $installments      = (float) $data['installments'];
            $installmentAmount = (float) $data['transaction_details']['installment_amount'];

            $this->setInstallmentsData($order, $installments);
            $this->setTransactionDetailsData($order, $installmentAmount);
        }

        $totalPaidAmount   = (float) $data['transaction_details']['total_paid_amount'];
        $transactionAmount = (float) $data['transaction_amount'];

        $this->setTransactionAmountData($order, $transactionAmount);
        $this->setTotalPaidAmountData($order, $totalPaidAmount);
        $this->updatePaymentsOrderMetadata($order, ['id' => $data['id']]);
        $this->setCheckoutDetails($order, $transactionMetadata);
        $order->save();
    }

    /**
     * Set checkout details in the order
     *
     * @param WC_Order $order
     * @param mixed $transactionMetadata
     *
     * @return void
     */
    private function setCheckoutDetails(WC_Order $order, $transactionMetadata): void
    {
        $this->orderMeta->update($order, self::CHECKOUT, $transactionMetadata->checkout);
        $this->orderMeta->update($order, self::CHECKOUT_TYPE, $transactionMetadata->checkout_type);
    }

    /**
     * Update an order's payments metadata
     *
     * @param WC_Order $order
     * @param string $value
     *
     * @return void
     */
    public function markPaymentAsBlocks(WC_Order $order, string $value)
    {
        $this->orderMeta->update($order, self::BLOCKS_PAYMENT, $value);
    }

    /**
     * Update an order's payments metadata
     *
     * @param WC_Order $order
     *
     * @return mixed
     */
    public function getPaymentBlocks(WC_Order $order)
    {
        return $this->orderMeta->get($order, self::BLOCKS_PAYMENT);
    }

    private function getSyncCronErrorCountValue(WC_Order $order): int
    {
        $errorCount = $this->orderMeta->get($order, self::SYNC_CRON_ERROR);
        if ($errorCount === null || empty($errorCount)) {
            return 0;
        }
        return $errorCount;
    }

    public function incrementSyncCronErrorCount(WC_Order $order): void
    {
        $errorCount = $this->getSyncCronErrorCountValue($order);
        if ($errorCount === 0) {
            $this->orderMeta->add($order, self::SYNC_CRON_ERROR, 1);
        } else {
            $this->orderMeta->update($order, self::SYNC_CRON_ERROR, (int) $errorCount + 1);
        }
        $order->save();
    }

    public function getSyncCronErrorCount(WC_Order $order): int
    {
        return $this->getSyncCronErrorCountValue($order);
    }
}
