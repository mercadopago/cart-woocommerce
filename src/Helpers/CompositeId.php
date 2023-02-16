<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class CompositeId
{

    /**
     * @const
     */
	const SEPARATOR = '|';

    /**
     * Generate ID from payment place
     * 
     * @return string
     */
    public function generateIdFromPlace($paymentMethodId, $paymentPlaceId): string
    {
		return $paymentMethodId . self::SEPARATOR . $paymentPlaceId;
	}


    /**
     * Parse composite ID
     * 
     * @return array
     */
	private function parse($compositeId): array
    {
		$exploded = explode(self::SEPARATOR, $compositeId);

		return [
			'payment_method_id' => $exploded[0],
			'payment_place_id' => isset($exploded[1]) ? $exploded[1] : null,
		];
	}

    /**
     * Get Payment Method ID
     * 
     * @return array
     */
	public function getPaymentMethodId($compositeId)
    {
		return $this->parse($compositeId)['payment_method_id'];
	}

    /**
     * Get Payment Place ID
     * 
     * @return array
     */
	public function getPaymentPlaceId($compositeId)
    {
		return $this->parse($compositeId)['payment_place_id'];
	}

}
