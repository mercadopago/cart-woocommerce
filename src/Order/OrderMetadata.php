<?php

namespace MercadoPago\Woocommerce\Order;

use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;
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

    public const CURRENCY_RATIO = '_currency_ratio';

    public const APPLIED_REFUND_IDS = '_mp_applied_refund_ids';

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
     * Get the checkout_type stored on the order so refund metrics can be segmented by
     * product bucket (super_token, credit_card, pix…) in Datadog. Returns null for legacy
     * orders that predate the checkout_type metadata.
     *
     * @param WC_Order|null $order
     *
     * @return string|null
     */
    public function getCheckoutType(?WC_Order $order): ?string
    {
        if (!$order) {
            return null;
        }

        $checkoutType = $this->orderMeta->get($order, self::CHECKOUT_TYPE);

        return !empty($checkoutType) ? (string) $checkoutType : null;
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
     * Set currency ratio data in the order
     *
     * @param WC_Order $order
     * @param mixed $value
     *
     * @return void
     */
    public function setCurrencyRatioData(WC_Order $order, $value): void
    {
        $this->orderMeta->update($order, self::CURRENCY_RATIO, $value);
        $order->save();
    }

    /**
     * Get the list of refund IDs already applied to the order (refund deduplication barrier).
     *
     * The metadata is stored as a JSON-encoded array of strings. Legacy orders (meta
     * absent) and empty values resolve to an empty array. The return is always a
     * normalized array of strings.
     *
     * @param WC_Order $order
     *
     * @return array
     */
    public function getAppliedRefundIds(WC_Order $order): array
    {
        $raw = $this->orderMeta->get($order, self::APPLIED_REFUND_IDS, true);

        if (empty($raw)) {
            return [];
        }

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                // Meta exists but is not a decodable JSON array (corrupted write or DB
                // truncation). Returning [] here means "no refund applied yet", which is
                // false and would let a later notification re-create every already-applied
                // refund. Log at error level so it surfaces in monitoring; still fall back
                // to [] because the value-based barrier (OrderStatus::refundedFlow) remains
                // as the dedup safety net.
                $this->logs->file->error(
                    'Corrupted applied-refund-ids meta on order ' . $order->get_id()
                    . '; expected JSON array, got: ' . (string) $raw,
                    __CLASS__
                );
                return [];
            }
        }

        return array_values(array_map('strval', $decoded));
    }

    /**
     * Append a refund ID to the applied-refunds list, guarding against duplicates.
     *
     * Reloads the order meta from the store (HPOS-safe) before evaluating the current
     * list, so concurrent notification/panel writes are observed. If the refund ID is
     * already present (strict string comparison), nothing is written. Otherwise the ID
     * is appended and the list is persisted as JSON before the caller completes the flow.
     *
     * HPOS approach: uses WC_Order CRUD methods exclusively (read_meta_data, update_meta_data,
     * save). Under HPOS these operate on wc_orders_meta; under legacy storage they use
     * wp_postmeta. No direct $wpdb query is needed because WC_Order::read_meta_data(true)
     * bypasses the in-memory cache and fetches fresh rows from whichever backend is active.
     *
     * Failure handling: this method never throws. The refund itself has already been
     * committed (MP + WooCommerce) by the time it runs, so a persistence failure here must
     * not abort the caller (e.g. a multi-payment loop) nor be swallowed by an outer catch.
     * Both failure modes are logged at error level and the value-based barrier in
     * OrderStatus::refundedFlow (totalRefundedMP <= totalRefundedWC) remains as the dedup
     * safety net for the next notification.
     *
     * @param WC_Order $order
     * @param string $refundId
     *
     * @return void
     */
    public function addAppliedRefundId(WC_Order $order, string $refundId): void
    {
        // Force a fresh read from the data store (HPOS-safe) before appending, so
        // concurrent notification/panel writes are observed.
        $order->read_meta_data(true);

        $appliedRefundIds = $this->getAppliedRefundIds($order);

        if (in_array($refundId, $appliedRefundIds, true)) {
            return;
        }

        $appliedRefundIds[] = $refundId;

        $encoded = wp_json_encode($appliedRefundIds);
        if ($encoded === false) {
            // Never persist a literal "false": a later json_decode would yield null and
            // getAppliedRefundIds would silently return [], wiping the whole dedup history.
            // Bail out keeping the previous (valid) meta value untouched.
            $this->logs->file->error(
                'Failed to JSON-encode applied refund ids for order ' . $order->get_id()
                . ' (refund_id=' . $refundId . '); keeping previous value',
                __CLASS__
            );
            return;
        }

        try {
            $this->orderMeta->update($order, self::APPLIED_REFUND_IDS, $encoded);
            $order->save();
        } catch (\Throwable $e) {
            $this->logs->file->error(
                'Failed to persist applied refund id ' . $refundId . ' for order '
                . $order->get_id() . ': ' . $e->getMessage(),
                __CLASS__
            );
        }
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
        // Use extractPaymentId for null-safety — consistent with initializePaymentMetadata.
        $paymentId = $this->extractPaymentId($paymentData);
        if ($paymentId !== null) {
            $this->setMercadoPagoPaymentId($order, [$paymentId]);
        }
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

    public function updateOrderCustomFieldsAfterSync(WC_Order $order, array $paymentsData): void
    {
        $paymentIds = array_column($paymentsData, 'id');
        $this->setPaymentsIdData($order, PaymentMetadata::joinPaymentIds($paymentIds));

        foreach ($paymentsData as $payment) {
            $metaPrefix = 'Mercado Pago - ' . $payment['id'];

            $mappedPayment = [
                'total_amount' => $payment['transaction_amount'] ?? 0,
                'payment_type_id' => $payment['payment_type_id'] ?? '',
                'payment_method_id' => $payment['payment_method_id'] ?? '',
                'paid_amount' => $payment['transaction_details']['total_paid_amount'] ?? 0,
                'coupon_amount' => $payment['coupon_amount'] ?? 0,
                'refunded_amount' => $payment['transaction_amount_refunded'] ?? 0,
            ];

            if (strpos($mappedPayment['payment_type_id'], 'card') !== false) {
                $this->orderMeta->update($order, $metaPrefix . PaymentMetadata::INSTALLMENTS_META_SUFFIX, $payment['installments'] ?? 0);
                $this->orderMeta->update($order, $metaPrefix . PaymentMetadata::INSTALLMENT_AMOUNT_META_SUFFIX, $payment['transaction_details']['installment_amount'] ?? 0);
                $this->orderMeta->update($order, $metaPrefix . PaymentMetadata::TRANSACTION_AMOUNT_META_SUFFIX, $payment['transaction_amount'] ?? 0);
                $this->orderMeta->update($order, $metaPrefix . PaymentMetadata::TOTAL_PAID_AMOUNT_META_SUFFIX, $payment['transaction_details']['total_paid_amount'] ?? 0);

                if (isset($payment['card']) && !empty($payment['card']['last_four_digits'])) {
                    $this->orderMeta->update($order, $metaPrefix . PaymentMetadata::CARD_LAST_FOUR_DIGITS_META_SUFFIX, $payment['card']['last_four_digits']);
                }
            }

            $this->orderMeta->update(
                $order,
                PaymentMetadata::getPaymentMetaKey($payment['id']),
                PaymentMetadata::formatPaymentMetadata($mappedPayment, $mappedPayment['refunded_amount'])
            );
        }

        $order->save();
    }

    /**
     * Check if metadata field exists
     *
     * @param WC_Order $order
     * @param string $key
     * @param string $value
     *
     * @return bool
    */
    public function hasMetadataField(WC_Order $order, string $key, string $value): bool
    {
        $existingMetadata = $order->get_meta($key);
        return !empty($existingMetadata) && strpos($existingMetadata, $value) !== false;
    }

    /**
     * Get metadata field value
     *
     * @param WC_Order $order
     * @param string $key
     * @param string $value
     *
     * @return string|false
    */
    public function getMetadataFieldValue(WC_Order $order, string $key, string $value)
    {
        $existingMetadata = $order->get_meta($key);
        if (!empty($existingMetadata) && preg_match('/\[' . $value . ' ([0-9.]+)\]/', $existingMetadata, $matches)) {
            return $matches[1];
        }
        return false;
    }
}
