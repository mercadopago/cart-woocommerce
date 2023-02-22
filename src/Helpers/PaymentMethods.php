<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Gateways\CreditsGateway;

if (!defined('ABSPATH')) {
    exit;
}

final class PaymentMethods
{

    /**
     * @const
     */
	const SEPARATOR = '|';

    /**
     * @var Url
     */
    private $url;

    /**
     * Url constructor
     */
    public function __construct(Url $url)
    {
        $this->url = $url;
    }

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
	private function parseCompositeId($compositeId): array
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
     * @param string $compositeId
     * 
     * @return array
     */
	public function getPaymentMethodId($compositeId): array
    {
		return $this->parseCompositeId($compositeId)['payment_method_id'];
	}

    /**
     * Get Payment Place ID
     * 
     * @param string $compositeId
     * 
     * @return array
     */
	public function getPaymentPlaceId($compositeId): array
    {
		return $this->parseCompositeId($compositeId)['payment_place_id'];
	}

    /**
     * Treat ticket payment methods with composite IDs
     * 
     * @param array $paymentMethods
     * 
     * @return array
     */
	public function treatTicketPaymentMethods($paymentMethods): array
    {
        $treatedPaymentMethods = [];

        foreach ( $paymentMethods as $paymentMethod ) {
			$treatedPaymentMethod = [];
			if ( isset($paymentMethod['payment_places']) ) {
				foreach ( $paymentMethod['payment_places'] as $place ) {
					$paymentPlaceId                  = $this->generateIdFromPlace($paymentMethod['id'], $place['payment_option_id']);
					$treatedPaymentMethod['id']      = $paymentPlaceId;
					$treatedPaymentMethod['value']   = $paymentPlaceId;
					$treatedPaymentMethod['rowText'] = $place['name'];
					$treatedPaymentMethod['img']     = $place['thumbnail'];
					$treatedPaymentMethod['alt']     = $place['name'];
					array_push( $treatedPaymentMethods, $treatedPaymentMethod);
				}
			} else {
				$treatedPaymentMethod['id']      = $paymentMethod['id'];
				$treatedPaymentMethod['value']   = $paymentMethod['id'];
				$treatedPaymentMethod['rowText'] = $paymentMethod['name'];
				$treatedPaymentMethod['img']     = $paymentMethod['secure_thumbnail'];
				$treatedPaymentMethod['alt']     = $paymentMethod['name'];
				array_push( $treatedPaymentMethods, $treatedPaymentMethod);
			}
		}

		return $treatedPaymentMethods;
	}

    /**
     * Treat basic payment methods
     * 
     * @param array $paymentMethods
     * 
     * @return array
     */
	public function treatBasicPaymentMethods($paymentMethods): array
    {
        if ( CreditsGateway::isAvailable() ) {
            $paymentMethods[] = [
                'src' => $this->url->getPluginFileUrl('/assets/images/icons/icon-credits', '.png', true),
                'alt' => 'Credits image'
            ];
        }

        return $paymentMethods;
	}
}
