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
	 * @param $paymenStatus
     *
     * @return string
     */
    public static function getStatusType($paymentStatus): string
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

		return array_key_exists($paymentStatus, $paymentStatusMap) ? $paymentStatusMap[$paymentStatus] : $paymentStatusMap['generic'];
    }

    /**
	 * Get Card Description
	 *
	 * @param $paymentStatusDetail
	 * @param $isCreditCard
	 *
	 * @return array
	 */
	public static function getCardDescription($translationsArray, $paymentStatusDetail, $isCreditCard) {
		$alertTitle  = array_key_exists($paymentStatusDetail,$translationsArray) ? $translationsArray[$paymentStatusDetail] : $translationsArray['generic'];
		$description = array_key_exists($paymentStatusDetail,$translationsArray) ? $translationsArray[$paymentStatusDetail] . ($isCreditCard ?: '_cc') : $translationsArray['generic'];

		return [
			'alert_title' => $alertTitle,
			'description' => $description,
		];
	}
}