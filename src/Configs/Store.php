<?php

namespace MercadoPago\Woocommerce\Configs;

if (!defined('ABSPATH')) {
    exit;
}

class Store
{
    /**
     * @const
     */
    const SITE_ID = '_site_id_v1';

    /**
     * @const
     */
    const STORE_ID = '_mp_store_identificator';

    /**
     * @const
     */
    const STORE_NAME = 'mp_statement_descriptor';

    /**
     * @const
     */
    const STORE_CATEGORY = '_mp_category_id';

    /**
     * @const
     */
    const CHECKOUT_COUNTRY = 'checkout_country';

    /**
     * @const
     */
    const WOOCOMMERCE_COUNTRY = 'woocommerce_default_country';

    /**
     * @const
     */
    const INTEGRATOR_ID = '_mp_integrator_id';

    /**
     * @const
     */
    const CUSTOM_DOMAIN = '_mp_custom_domain';

    /**
     * @const
     */
    const DEBUG_MODE = '_mp_debug_mode';

    /**
     * @const
     */
    const CHECKBOX_CHECKOUT_PRODUCTION_MODE = 'checkbox_checkout_production_mode';

    /**
     * @const
     */
    const CHECKBOX_CHECKOUT_TEST_MODE = 'checkbox_checkout_test_mode';

    /**
     * @var Store
     */
    private static $instance = '';

    /**
     * Get Store Configs instance
     *
     * @return Store
     */
    public static function getInstance(): Store
    {
        if ('' === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return string
     */
    public function getSiteId(): string
    {
        return get_option(self::SITE_ID, '');
    }

    /**
     * @param string $siteId
     */
    public function setSiteId(string $siteId): void
    {
        update_option(self::SITE_ID, $siteId);
    }

    /**
     * @return string
     */
    public function getStoreId(): string
    {
        return get_option(self::STORE_ID, '');
    }

    /**
     * @param string $storeId
     */
    public function setStoreId(string $storeId): void
    {
        update_option(self::STORE_ID, $storeId);
    }

    /**
     * @return string
     */
    public function getStoreName(): string
    {
        return get_option(self::STORE_NAME, '');
    }

    /**
     * @param string $storeName
     */
    public function setStoreName(string $storeName): void
    {
        update_option(self::STORE_NAME, $storeName);
    }

    /**
     * @return string
     */
    public function getStoreCategory(): string
    {
        return get_option(self::STORE_CATEGORY, '');
    }

    /**
     * @param string $storeCategory
     */
    public function setStoreCategory(string $storeCategory): void
    {
        update_option(self::STORE_CATEGORY, $storeCategory);
    }

    /**
     * @return string
     */
    public function getCheckoutCountry(): string
    {
        return get_option(self::CHECKOUT_COUNTRY, '');
    }

    /**
     * @param string $checkoutCountry
     */
    public function setCheckoutCountry(string $checkoutCountry): void
    {
        update_option(self::CHECKOUT_COUNTRY, $checkoutCountry);
    }

    /**
     * @return string
     */
    public function getWoocommerceCountry(): string
    {
        return get_option(self::WOOCOMMERCE_COUNTRY, '');
    }

    /**
     * @param string $woocommerceCountry
     */
    public function setWoocommerceCountry(string $woocommerceCountry): void
    {
        update_option(self::WOOCOMMERCE_COUNTRY, $woocommerceCountry);
    }

    /**
     * @return string
     */
    public function getIntegratorId(): string
    {
        return get_option(self::INTEGRATOR_ID, '');
    }

    /**
     * @param string $integratorId
     */
    public function setIntegratorId(string $integratorId): void
    {
        update_option(self::INTEGRATOR_ID, $integratorId);
    }

    /**
     * @return string
     */
    public function getCustomDomain(): string
    {
        return get_option(self::CUSTOM_DOMAIN, '');
    }

    /**
     * @param string $customDomain
     */
    public function setCustomDomain(string $customDomain): void
    {
        update_option(self::CUSTOM_DOMAIN, $customDomain);
    }

    /**
     * @return string
     */
    public function getDebugMode(): string
    {
        return get_option(self::DEBUG_MODE, 'no');
    }

    /**
     * @param string $debugMode
     */
    public function setDebugMode(string $debugMode): void
    {
        update_option(self::DEBUG_MODE, $debugMode);
    }

    /**
     * @return string
     */
    public function getCheckboxCheckoutProductionMode(): string
    {
        return get_option(self::CHECKBOX_CHECKOUT_PRODUCTION_MODE, '');
    }

    /**
     * @param string $checkboxCheckoutProductionMode
     */
    public function setCheckboxCheckoutProductionMode(string $checkboxCheckoutProductionMode): void
    {
        update_option(self::CHECKBOX_CHECKOUT_PRODUCTION_MODE, $checkboxCheckoutProductionMode);
    }

    /**
     * @return string
     */
    public function getCheckboxCheckoutTestMode(): string
    {
        return get_option(self::CHECKBOX_CHECKOUT_TEST_MODE, '');
    }

    /**
     * @param string $checkboxCheckoutTestMode
     */
    public function setCheckboxCheckoutTestMode(string $checkboxCheckoutTestMode): void
    {
        update_option(self::CHECKBOX_CHECKOUT_TEST_MODE, $checkboxCheckoutTestMode);
    }
}
