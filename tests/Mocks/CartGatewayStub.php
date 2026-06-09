<?php

namespace MercadoPago\Woocommerce\Tests\Mocks;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Concrete AbstractGateway with a non-empty ID, used to exercise the gateway-ID
 * guard in Cart::addDiscountAndCommissionOnFeesFromBlocks(). Autoloaded lazily so
 * the parent WC_Payment_Gateway mock (created in WoocommerceMock @before) exists.
 */
class CartGatewayStub extends AbstractGateway
{
    public const ID = 'woo-mercado-pago-test';

    public function getCheckoutName(): string
    {
        return '';
    }

    public function formFieldsMainSection(): array
    {
        return [];
    }

    public function proccessPaymentInternal($order): array
    {
        return [];
    }

    public function getPaymentFieldsParams(): array
    {
        return [];
    }
}
