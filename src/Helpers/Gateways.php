<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Store;

if (!defined('ABSPATH')) {
    exit;
}

final class Gateways
{
    private Store $store;

    /**
     * Gateways constructor
     *
     * @param Store $store
     */
    public function __construct(Store $store)
    {
        $this->store     = $store;
    }

    /**
     * Determines if there are currently enabled payment gateways
     *
     * @return array
     */
    public function getEnabledPaymentGateways(): array
    {
        $enabledPaymentGateways = array();
        $paymentGateways = $this->store->getAvailablePaymentGateways();
        foreach ($paymentGateways as $gateway) {
            $gateway = new $gateway();

            if (isset($gateway->settings['enabled']) && 'yes' === $gateway->settings['enabled']) {
                $enabledPaymentGateways[] = $gateway->id;
            }
        }

        return $enabledPaymentGateways;
    }
}
