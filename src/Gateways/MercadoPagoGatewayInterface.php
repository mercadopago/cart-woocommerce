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
    public function init_form_fields(): void;

    /**
     * @return void
     */
    public function payment_scripts(): void;

    /**
     * @return void
     */
    public function payment_fields(): void;

    /**
     * @return bool
     */
    public function validate_fields(): bool;

    /**
     * @param $orderId
     * @return array
     */
    public function process_payment($orderId): array;

    /**
     * @return void
     */
    public function webhook(): void;
}
