<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMethods
{
    private const SEPARATOR = '_';

    private array $enabledPaymentMethods = [];

    private Url $url;

    /**
     * Url constructor
     *
     * @param Url $url
     */
    public function __construct(Url $url)
    {
        $this->url = $url;
    }

    /**
     * Generate ID from payment place
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
     * Parse composite ID
     *
     * @param $compositeId
     * @return array
     */
    private function parseCompositeId($compositeId): array
    {
        $exploded = explode(self::SEPARATOR, $compositeId);

        return [
            'payment_method_id' => $exploded[0],
            'payment_place_id'  => $exploded[1] ?? '',
        ];
    }

    /**
     * Get Payment Method ID
     *
     * @param $compositeId
     *
     * @return string
     */
    public function getPaymentMethodId($compositeId): string
    {
        return $this->parseCompositeId($compositeId)['payment_method_id'];
    }

    /**
     * Get Payment Place ID
     *
     * @param $compositeId
     *
     * @return string
     */
    public function getPaymentPlaceId($compositeId): string
    {
        return $this->parseCompositeId($compositeId)['payment_place_id'];
    }

    public function getEnabledPaymentMethods(): array
    {
        if (!empty($this->enabledPaymentMethods)) {
            return $this->enabledPaymentMethods;
        }

        if (!function_exists('WC') || !method_exists(WC()->payment_gateways(), 'get_available_payment_gateways')) {
            return [];
        }

        $paymentMethods = WC()->payment_gateways()->get_available_payment_gateways();

        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->get_option('enabled') === 'yes') {
                $this->enabledPaymentMethods[] = $paymentMethod->id;
            }

            if (is_callable([$paymentMethod, 'getWalletButtonEnabled']) && $paymentMethod->getWalletButtonEnabled()) {
                $this->enabledPaymentMethods[] = 'woo-mercado-pago-wallet-button';
            }
        }

        return $this->enabledPaymentMethods;
    }

    /**
     * Treat ticket payment methods with composite IDs
     *
     * @param array $paymentMethods
     *
     * @return array
     */
    public function treatTicketPaymentMethods(array $paymentMethods): array
    {
        $treatedPaymentMethods = [];

        foreach ($paymentMethods as $paymentMethod) {
            $treatedPaymentMethod = [];

            if (isset($paymentMethod['payment_places'])) {
                foreach ($paymentMethod['payment_places'] as $place) {
                    $paymentPlaceId                  = $this->generateIdFromPlace($paymentMethod['id'], $place['payment_option_id']);
                    $treatedPaymentMethod['id']      = $paymentPlaceId;
                    $treatedPaymentMethod['value']   = $paymentPlaceId;
                    $treatedPaymentMethod['rowText'] = $place['name'];
                    $treatedPaymentMethod['img']     = $place['thumbnail'];
                    $treatedPaymentMethod['alt']     = $place['name'];
                    $treatedPaymentMethods[]         = $treatedPaymentMethod;
                }
            } else {
                $treatedPaymentMethod['id']      = $paymentMethod['id'];
                $treatedPaymentMethod['value']   = $paymentMethod['id'];
                $treatedPaymentMethod['rowText'] = $paymentMethod['name'];
                $treatedPaymentMethod['img']     = $paymentMethod['secure_thumbnail'];
                $treatedPaymentMethod['alt']     = $paymentMethod['name'];
                $treatedPaymentMethods[]         = $treatedPaymentMethod;
            }
        }

        return $treatedPaymentMethods;
    }
}
