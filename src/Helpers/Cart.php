<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Blocks\AbstractBlock;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

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

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var StoreTranslations
     */
    protected $storeTranslations;

    /**
     * @param Country $country
     * @param Currency $currency
     * @param Session $session
     * @param StoreTranslations $storeTranslations
     */
    public function __construct(
        Country $country,
        Currency $currency,
        Session $session,
        StoreTranslations $storeTranslations
    ) {
        global $woocommerce;

        $this->woocommerce       = $woocommerce;
        $this->country           = $country;
        $this->currency          = $currency;
        $this->session           = $session;
        $this->storeTranslations = $storeTranslations;
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
        $total    = $this->getTotal();

        return Numbers::calculateByCurrency($currency, $total, $ratio);
    }

    /**
     * Add plugin discount value on WC_Cart fees
     *
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function addDiscountOnFees(AbstractGateway $gateway): void
    {
        $discount     = $this->calculateSubtotalWithDiscount($gateway);
        $discountName = $this->storeTranslations->commonCheckout['cart_discount'];

        if ($discount > 0) {
            $this->addFee($discountName, -$discount);
        }
    }

    /**
     * Add plugin commission value on WC_Cart fees
     *
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function addCommissionOnFees(AbstractGateway $gateway): void
    {
        $commission     = $this->calculateSubtotalWithCommission($gateway);
        $commissionName = $this->storeTranslations->commonCheckout['cart_commission'];

        if ($commission > 0) {
            $this->addFee($commissionName, $commission);
        }
    }

    /**
     * Add plugin and commission to WC_Cart fees
     *
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function addDiscountAndCommissionOnFees(AbstractGateway $gateway)
    {
        $selectedGateway = $this->session->getSession('chosen_payment_method');

        if ($selectedGateway && $selectedGateway == $gateway::ID) {
            $this->addDiscountOnFees($gateway);
            $this->addCommissionOnFees($gateway);
        }
    }

    /**
     * Add plugin and commission to WC_Cart fees from Blocks
     *
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function addDiscountAndCommissionOnFeesFromBlocks(AbstractGateway $gateway)
    {
        $selectedGateway = $this->session->getSession(AbstractBlock::GATEWAY_SESSION_KEY);

        if ($selectedGateway && $selectedGateway == $gateway::ID) {
            $this->addDiscountOnFees($gateway);
            $this->addCommissionOnFees($gateway);
        }
    }

    /**
     * Remove plugin discount value on WC_Cart fees
     *
     * @return void
     */
    public function removeDiscountOnFees(): void
    {
        $discountName = $this->storeTranslations->commonCheckout['cart_discount'];
        $this->addFee($discountName, 0);
    }

    /**
     * Remove plugin commission value on WC_Cart fees
     *
     * @return void
     */
    public function removeCommissionOnFees(): void
    {
        $commissionName = $this->storeTranslations->commonCheckout['cart_commission'];
        $this->addFee($commissionName, 0);
    }

    /**
     * Remove plugin and commission to WC_Cart fees
     *
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function removeDiscountAndCommissionOnFees(AbstractGateway $gateway)
    {
        $selectedGateway = $this->session->getSession('chosen_payment_method');

        if ($selectedGateway && $selectedGateway == $gateway::ID) {
            $this->removeDiscountOnFees();
            $this->removeCommissionOnFees();
        }
    }

    /**
     * Remove plugin and commission to WC_Cart fees
     *
     * @param AbstractGateway $gateway
     *
     * @return void
     */
    public function removeDiscountAndCommissionOnFeesFromBlocks(AbstractGateway $gateway)
    {
        $selectedGateway = $this->session->getSession(AbstractBlock::GATEWAY_SESSION_KEY);

        if ($selectedGateway && $selectedGateway == $gateway::ID) {
            $this->removeDiscountOnFees();
            $this->removeCommissionOnFees();
        }
    }

    /**
     * Add fee to WC_Cart
     *
     * @param string $name
     * @param float $value
     * @return void
     */
    public function addFee(string $name, float $value): void
    {
        $this->getCart()->add_fee($name, $value, true);
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
     * Empty WC_Cart
     *
     * @return void
     */
    public function emptyCart(): void
    {
        $this->getCart()->empty_cart();
    }

    /**
     * Calculate WC_Cart total and dispatch actions
     *
     * @return void
     */
    public function calculateTotal(): void
    {
        $this->getCart()->calculate_totals();
    }
}
