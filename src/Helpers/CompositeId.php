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
    private const SEPARATOR = '|';

    /**
     * Generate id from place
     *
     * @param $paymentMethodId
     * @param $paymentPlaceId
     *
     * @return string
     */
    public function generateIdFromPlace($paymentMethodId, $paymentPlaceId): string
    {
        return $paymentMethodId . self::SEPARATOR . $paymentPlaceId;
    }

    /**
     * Composite id parse
     *
     * @param $compositeId
     *
     * @return array
     */
    private function parse($compositeId): array
    {
        $exploded = explode(self::SEPARATOR, $compositeId);

        return [
            'payment_method_id' => $exploded[0],
            'payment_place_id'  => $exploded[1] ?? null,
        ];
    }

    /**
     * Get payment method id
     *
     * @param $compositeId
     *
     * @return array
     */
    public function getPaymentMethodId($compositeId): array
    {
        return $this->parse($compositeId)['payment_method_id'];
    }

    /**
     * Get payment place id
     *
     * @param $compositeId
     *
     * @return array
     */
    public function getPaymentPlaceId($compositeId): array
    {
        return $this->parse($compositeId)['payment_place_id'];
    }
}
