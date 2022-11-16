<?php

namespace MercadoPago\Woocommerce\Gateways;

if (!defined('ABSPATH')) {
    exit;
}

interface MercadoPagoGatewayInterface
{
    /**
     * @return void
     */
    public function initFormFields(): void;

    /**
     * @return void
     */
    public function paymentScripts(): void;

    /**
     * @return void
     */
    public function paymentFields(): void;

    /**
     * @return bool
     */
    public function validateFields(): bool;

    /**
     * @param $orderId
     * @return array
     */
    public function processPayment($orderId): array;

    /**
     * @return void
     */
    public function webhook(): void;
}
