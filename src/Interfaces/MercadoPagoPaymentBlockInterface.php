<?php

namespace MercadoPago\Woocommerce\Interfaces;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

if (!defined('ABSPATH')) {
    exit;
}

interface MercadoPagoPaymentBlockInterface
{
    /**
     * Initializes the payment method type
     */
    public function initialize();

    /**
     * Returns if this payment method should be active
     *
     * @return boolean
     */
    public function is_active(): bool;

    /**
     * Returns an array of scripts/handles to be registered for this payment method
     *
     * @return array
     */
    public function get_payment_method_script_handles(): array;

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script
     *
     * @return array
     */
    public function get_payment_method_data(): array;

    /**
     * Returns an array of supported features
     *
     * @return array
     */
    public function get_supported_features(): array;

    /**
     * Set block payment gateway
     *
     * @return ?AbstractGateway
     */
    public function setGateway(): ?AbstractGateway;

    /**
     * Set payment block script params
     *
     * @return array
     */
    public function getScriptParams(): array;
}
