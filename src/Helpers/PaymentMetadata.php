<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Helpers\Date;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadata
{
    private const FIELD_SEPARATOR = '/';
    private const PAYMENT_ID_SEPARATOR = ', ';

    private const DATE_TEMPLATE = '[Date %s]';
    private const AMOUNT_TEMPLATE = '[Amount %s]';
    private const PAYMENT_TYPE_TEMPLATE = '[Payment Type %s]';
    private const PAYMENT_METHOD_TEMPLATE = '[Payment Method %s]';
    private const PAID_TEMPLATE = '[Paid %s]';
    private const COUPON_TEMPLATE = '[Coupon %s]';
    private const REFUND_TEMPLATE = '[Refund %s]';

    public const PAYMENT_META_PREFIX = 'Mercado Pago - Payment ';
    public const PAYMENT_IDS_META_KEY = '_Mercado_Pago_Payment_IDs';
    public const PAYMENT_TYPE_META_SUFFIX = ' - payment_type';
    public const INSTALLMENTS_META_SUFFIX = ' - installments';
    public const INSTALLMENT_AMOUNT_META_SUFFIX = ' - installment_amount';
    public const TRANSACTION_AMOUNT_META_SUFFIX = ' - transaction_amount';
    public const TOTAL_PAID_AMOUNT_META_SUFFIX = ' - total_paid_amount';
    public const CARD_LAST_FOUR_DIGITS_META_SUFFIX = ' - card_last_four_digits';
    public const FIELD_PATTERNS = [
        'Date' => 'date',
        'Amount' => 'amount',
        'Payment Type' => 'payment_type',
        'Payment Method' => 'payment_method',
        'Paid' => 'paid',
        'Coupon' => 'coupon',
        'Refund' => 'refund',
    ];

    /**
     * Parse payment field data from array to structured object
     * @param array $fieldArray
     * [
     *    '[Date 2024-01-15 10:30:00]',
     *    '[Amount 150.75]',
     *    '[Payment Type credit_card]',
     *    '[Payment Method visa]',
     *    '[Paid 150.75]',
     *    '[Coupon 0]',
     *    '[Refund 25.5]'
     * ]
     * @return object
     * [
     *    'date' => '2024-01-15 10:30:00',
     *    'amount' => 150.75,           // float
     *    'payment_type' => 'credit_card',
     *    'payment_method' => 'visa',
     *    'paid' => 150.75,             // float
     *    'coupon' => 0.0,              // float
     *    'refund' => 25.5              // float
     * ];
     */

    public static function parsePaymentFieldData(array $fieldArray): object
    {
        $paymentData = new \stdClass();

        foreach ($fieldArray as $element) {
            $element = trim($element);

            if (preg_match('/\[(.+)\]/', $element, $matches)) {
                $content = trim($matches[1]);

                $matched = false;
                foreach (self::FIELD_PATTERNS as $pattern => $property) {
                    if (strpos($content, $pattern . ' ') === 0) {
                        $fieldValue = trim(substr($content, strlen($pattern . ' ')));

                        if (is_numeric($fieldValue)) {
                            $paymentData->$property = (float) $fieldValue;
                        } else {
                            $paymentData->$property = $fieldValue;
                        }
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    $lastSpacePos = strrpos($content, ' ');
                    if ($lastSpacePos !== false) {
                        $fieldName = substr($content, 0, $lastSpacePos);
                        $fieldValue = substr($content, $lastSpacePos + 1);

                        $propertyName = strtolower(str_replace(' ', '_', trim($fieldName)));
                        $fieldValue = trim($fieldValue);

                        if (is_numeric($fieldValue)) {
                            $paymentData->$propertyName = (float) $fieldValue;
                        } else {
                            $paymentData->$propertyName = $fieldValue;
                        }
                    }
                }
            }
        }

        return $paymentData;
    }

    /**
     * Format payment metadata string
     */
    public static function formatPaymentMetadata(array $paymentData, float $refundedAmount): string
    {
        $fields = [
            sprintf(self::DATE_TEMPLATE, $paymentData['date'] ?? Date::getNowDate('Y-m-d H:i:s')),
            sprintf(self::AMOUNT_TEMPLATE, $paymentData['total_amount'] ?? 0),
            sprintf(self::PAYMENT_TYPE_TEMPLATE, $paymentData['payment_type_id'] ?? ''),
            sprintf(self::PAYMENT_METHOD_TEMPLATE, $paymentData['payment_method_id'] ?? ''),
            sprintf(self::PAID_TEMPLATE, $paymentData['paid_amount'] ?? 0),
            sprintf(self::COUPON_TEMPLATE, $paymentData['coupon_amount'] ?? 0),
            sprintf(self::REFUND_TEMPLATE, $refundedAmount ?? 0),
        ];

        return implode(self::FIELD_SEPARATOR, $fields);
    }

    /**
     * Get payment meta key
     *
     * @param string $paymentId
     * @return string
     */
    public static function getPaymentMetaKey(string $paymentId): string
    {
        return self::PAYMENT_META_PREFIX . $paymentId;
    }

    /**
     * Join payment IDs for storage
     *
     * @param array $paymentIds
     * @return string
     */
    public static function joinPaymentIds(array $paymentIds): string
    {
        return implode(self::PAYMENT_ID_SEPARATOR, $paymentIds);
    }

    /**
     * Extract payment field data from meta string
     *
     * @param string $metaString
     * @return object
     */
    public static function extractPaymentDataFromMeta(string $metaString): object
    {
        $fieldArray = explode(self::FIELD_SEPARATOR, $metaString);
        return self::parsePaymentFieldData($fieldArray);
    }
}
