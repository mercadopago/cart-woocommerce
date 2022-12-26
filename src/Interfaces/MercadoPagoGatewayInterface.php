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
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment(int $order_id): array;

    /**
     * @return void
     */
    public function webhook(): void;
}
