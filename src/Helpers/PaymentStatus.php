<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class PaymentStatus
{
    /**
     * Get Status Type
     *
     * @param string $paymentStatus
     *
     * @return string
     */
    public static function getStatusType(string $paymentStatus): string
    {
        $paymentStatusMap = [
            'approved'     => 'success',
            'authorized'   => 'success',
            'pending'      => 'pending',
            'in_process'   => 'pending',
            'in_mediation' => 'pending',
            'rejected'     => 'rejected',
            'canceled'     => 'rejected',
            'refunded'     => 'refunded',
            'charged_back' => 'charged_back',
            'generic'      => 'rejected'
        ];

        return array_key_exists($paymentStatus, $paymentStatusMap)
            ? $paymentStatusMap[$paymentStatus]
            : $paymentStatusMap['generic'];
    }

    /**
     * Get Card Description
     *
     * @param $translationsArray
     * @param $paymentStatusDetail
     * @param $isCreditCard
     *
     * @return array
     */
    public static function getCardDescription($translationsArray, $paymentStatusDetail, $isCreditCard): array
    {
        $alertTitleTranslationKey  = 'alert_title_' . $paymentStatusDetail;
        $descriptionTranslationKey = 'description_' . $paymentStatusDetail;

        $alertTitle = array_key_exists($alertTitleTranslationKey, $translationsArray)
            ? $translationsArray[$alertTitleTranslationKey]
            : $translationsArray['alert_title_generic'];

        $description = array_key_exists($descriptionTranslationKey, $translationsArray)
            ? $translationsArray[$descriptionTranslationKey]
            : $translationsArray['description_generic'];

        $creditCardDescriptionKey = $descriptionTranslationKey . '_cc';
        if ($isCreditCard && array_key_exists($creditCardDescriptionKey, $translationsArray)) {
            $description = $translationsArray[$creditCardDescriptionKey];
        }

        return [
            'alert_title' => $alertTitle,
            'description' => $description,
        ];
    }
}
