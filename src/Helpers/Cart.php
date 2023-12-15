<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

if (!defined('ABSPATH')) {
    exit;
}

final class Cart
{
    /**
     * @var \WooCommerce
     */
    protected $woocommerce;

    /**
     * @var Country
     */
    protected $country;

    /**
     * @var Currency
     */
    protected $currency;

    public function __construct(Country $country, Currency $currency)
    {
        global $woocommerce;

        $this->woocommerce = $woocommerce;
        $this->country     = $country;
        $this->currency    = $currency;
    }

    /**
     * Get WC_Cart
     *
     * @return \WC_Cart|null
     */
    public function getCart(): ?\WC_Cart
    {
        return $this->woocommerce->cart;
    }

    /**
     * Get WC_Cart total
     *
     * @return float
     */
    public function getTotal(): float
    {
        return $this->getCart()->__get('total');
    }

    /**
     * Get WC_Cart contents total
     *
     * @return float
     */
    public function getContentsTotal(): float
    {
        return $this->getCart()->get_cart_contents_total();
    }

    /**
     * Get WC_Cart contents total tax
     *
     * @return float
     */
    public function getContentsTotalTax(): float
    {
        return $this->getCart()->get_cart_contents_tax();
    }

    /**
     * Get subtotal with contents total and contents total tax
     *
     * @return float
     */
    public function getSubtotal(): float
    {
        $cartSubtotal    = $this->getContentsTotal();
        $cartSubtotalTax = $this->getContentsTotalTax();

        return $cartSubtotal + $cartSubtotalTax;
    }

    /**
     * Calculate WC_Cart subtotal with plugin discount
     *
     * @param AbstractGateway $gateway
     *
     * @return float
     */
    public function calculateSubtotalWithDiscount(AbstractGateway $gateway): float
    {
        $ratio    = $this->currency->getRatio($gateway);
        $currency = $this->country->getCountryConfigs()['currency'];
        $discount = $this->getSubtotal() * ($gateway->discount / 100);

        return Numbers::calculateByCurrency($currency, $discount, $ratio);
    }

    /**
     * Calculate WC_Cart subtotal with plugin commission
     *
     * @param AbstractGateway $gateway
     *
     * @return float
     */
    public function calculateSubtotalWithCommission(AbstractGateway $gateway): float
    {
        $ratio      = $this->currency->getRatio($gateway);
        $currency   = $this->country->getCountryConfigs()['currency'];
        $commission = $this->getSubtotal() * ($gateway->commission / 100);

        return Numbers::calculateByCurrency($currency, $commission, $ratio);
    }

    /**
     * Calculate WC_Cart total with plugin discount and commission
     *
     * @param AbstractGateway $gateway
     *
     * @return float
     */
    public function calculateTotalWithDiscountAndCommission(AbstractGateway $gateway): float
    {
        $ratio    = $this->currency->getRatio($gateway);
        $currency = $this->country->getCountryConfigs()['currency'];

        $subtotal   = $this->getSubtotal();
        $discount   = $this->calculateSubtotalWithDiscount($gateway);
        $commission = $this->calculateSubtotalWithCommission($gateway);

        $total           = $this->getTotal();
        $amount          = $subtotal - $discount + $commission;
        $calculatedTotal = $total + $amount;

        return Numbers::calculateByCurrency($currency, $calculatedTotal, $ratio);
    }

    /**
     * Verify if WC_Cart exists and is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->getCart() !== null;
    }

    /**
     * @return void
     */
    public function emptyCart(): void
    {
        $this->getCart()->empty_cart();
    }
}