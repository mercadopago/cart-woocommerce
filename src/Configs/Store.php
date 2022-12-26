<?php

namespace MercadoPago\Woocommerce\Configs;

use MercadoPago\Woocommerce\Hooks\Options;

if (!defined('ABSPATH')) {
    exit;
}

class Store
{
    /**
     * @const
     */
    private const STORE_ID = '_mp_store_identificator';

    /**
     * @const
     */
    private const STORE_NAME = 'mp_statement_descriptor';

    /**
     * @const
     */
    private const STORE_CATEGORY = '_mp_category_id';

    /**
     * @const
     */
    private const CHECKOUT_COUNTRY = 'checkout_country';

    /**
     * @const
     */
    private const WOOCOMMERCE_COUNTRY = 'woocommerce_default_country';

    /**
     * @const
     */
    private const INTEGRATOR_ID = '_mp_integrator_id';

    /**
     * @const
     */
    private const CUSTOM_DOMAIN = '_mp_custom_domain';

    /**
     * @const
     */
    private const DEBUG_MODE = '_mp_debug_mode';

    /**
     * @const
     */
    private const CHECKBOX_CHECKOUT_PRODUCTION_MODE = 'checkbox_checkout_production_mode';

    /**
     * @const
     */
    private const CHECKBOX_CHECKOUT_TEST_MODE = 'checkbox_checkout_test_mode';

    /**
     * @var Options
     */
    private $options;

    /**
     * Store constructor
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function getStoreId(): string
    {
        return $this->options->get(self::STORE_ID, '');
    }

    /**
     * @param string $storeId
     */
    public function setStoreId(string $storeId): void
    {
        $this->options->set(self::STORE_ID, $storeId);
    }

    /**
     * @return string
     */
    public function getStoreName(): string
    {
        return $this->options->get(self::STORE_NAME, '');
    }

    /**
     * @param string $storeName
     */
    public function setStoreName(string $storeName): void
    {
        $this->options->set(self::STORE_NAME, $storeName);
    }

    /**
     * @return string
     */
    public function getStoreCategory(): string
    {
        return $this->options->get(self::STORE_CATEGORY, '');
    }

    /**
     * @param string $storeCategory
     */
    public function setStoreCategory(string $storeCategory): void
    {
        $this->options->set(self::STORE_CATEGORY, $storeCategory);
    }

    /**
     * @return string
     */
    public function getCheckoutCountry(): string
    {
        return $this->options->get(self::CHECKOUT_COUNTRY, '');
    }

    /**
     * @param string $checkoutCountry
     */
    public function setCheckoutCountry(string $checkoutCountry): void
    {
        $this->options->set(self::CHECKOUT_COUNTRY, $checkoutCountry);
    }

    /**
     * @return string
     */
    public function getWoocommerceCountry(): string
    {
        return $this->options->get(self::WOOCOMMERCE_COUNTRY, '');
    }

    /**
     * @param string $woocommerceCountry
     */
    public function setWoocommerceCountry(string $woocommerceCountry): void
    {
        $this->options->set(self::WOOCOMMERCE_COUNTRY, $woocommerceCountry);
    }

    /**
     * @return string
     */
    public function getIntegratorId(): string
    {
        return $this->options->get(self::INTEGRATOR_ID, '');
    }

    /**
     * @param string $integratorId
     */
    public function setIntegratorId(string $integratorId): void
    {
        $this->options->set(self::INTEGRATOR_ID, $integratorId);
    }

    /**
     * @return string
     */
    public function getCustomDomain(): string
    {
        return $this->options->get(self::CUSTOM_DOMAIN, '');
    }

    /**
     * @param string $customDomain
     */
    public function setCustomDomain(string $customDomain): void
    {
        $this->options->set(self::CUSTOM_DOMAIN, $customDomain);
    }

    /**
     * @return string
     */
    public function getDebugMode(): string
    {
        return $this->options->get(self::DEBUG_MODE, 'no');
    }

    /**
     * @param string $debugMode
     */
    public function setDebugMode(string $debugMode): void
    {
        $this->options->set(self::DEBUG_MODE, $debugMode);
    }

    /**
     * @return string
     */
    public function getCheckboxCheckoutProductionMode(): string
    {
        return $this->options->get(self::CHECKBOX_CHECKOUT_PRODUCTION_MODE, '');
    }

    /**
     * @param string $checkboxCheckoutProductionMode
     */
    public function setCheckboxCheckoutProductionMode(string $checkboxCheckoutProductionMode): void
    {
        $this->options->set(self::CHECKBOX_CHECKOUT_PRODUCTION_MODE, $checkboxCheckoutProductionMode);
    }

    /**
     * @return string
     */
    public function getCheckboxCheckoutTestMode(): string
    {
        return $this->options->get(self::CHECKBOX_CHECKOUT_TEST_MODE, '');
    }

    /**
     * @param string $checkboxCheckoutTestMode
     */
    public function setCheckboxCheckoutTestMode(string $checkboxCheckoutTestMode): void
    {
        $this->options->set(self::CHECKBOX_CHECKOUT_TEST_MODE, $checkboxCheckoutTestMode);
    }
}
