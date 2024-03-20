<?php

namespace MercadoPago\Woocommerce\Configs;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
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
    private const CUSTOM_DOMAIN_OPTIONS = '_mp_custom_domain_options';

    /**
     * @const
     */
    private const DEBUG_MODE = '_mp_debug_mode';

    /**
     * @const
     */
    private const DISMISSED_REVIEW_NOTICE = '_mp_dismiss_review';

    /**
     * @const
     */
    private const ANY_ORDER_COMPLETED = '_mp_any_order_completed';

    /**
     * @const
     */
    private const DISMISSED_SAVED_CARDS_NOTICE = '_mp_dismiss_saved_cards_notice';

    /**
     * @const
     */
    private const CHECKBOX_CHECKOUT_PRODUCTION_MODE = 'checkbox_checkout_production_mode';

    /**
     * @const
     */
    private const CHECKBOX_CHECKOUT_TEST_MODE = 'checkbox_checkout_test_mode';

    /**
     * @const
     */
    private const GATEWAY_TITLE = 'title';

    /**
     * @const
     */
    private const CHECKOUT_EXPIRATION_DATE_PIX = 'expiration_date';

    /**
     * @const
     */
    private $availablePaymentGateways = [];

    /**
     * @var Options
     */
    private $options;

    /**
     * Store constructor
     *
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->getCheckboxCheckoutTestMode() === 'yes';
    }

    /**
     * @return bool
     */
    public function isProductionMode(): bool
    {
        return $this->getCheckboxCheckoutTestMode() !== 'yes';
    }

    /**
     * @return string
     */
    public function getTestMode(): string
    {
        return $this->getCheckboxCheckoutTestMode();
    }

    /**
     * @return string
     */
    public function getProductionMode(): string
    {
        return $this->getCheckboxCheckoutTestMode() === 'yes' ? 'no' : 'yes';
    }

    /**
     * @param string $default
     *
     * @return string
     */
    public function getStoreId(string $default = 'WC-'): string
    {
        return $this->options->get(self::STORE_ID, $default);
    }

    /**
     * @param string $storeId
     */
    public function setStoreId(string $storeId): void
    {
        $this->options->set(self::STORE_ID, $storeId);
    }

    /**
     * @param string $default
     *
     * @return string
     */
    public function getStoreName(string $default = ''): string
    {
        $storeName = $this->options->get(self::STORE_NAME, $default);
        return empty($storeName) ? $default : $storeName;
    }

    /**
     * @param string $storeName
     */
    public function setStoreName(string $storeName): void
    {
        $this->options->set(self::STORE_NAME, $storeName);
    }

    /**
     * @param string $default
     *
     * @return string
     */
    public function getStoreCategory(string $default = ''): string
    {
        return $this->options->get(self::STORE_CATEGORY, $default);
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
     * @param string $default
     * @return string
     */
    public function getWoocommerceCountry(string $default = ''): string
    {
        return $this->options->get(self::WOOCOMMERCE_COUNTRY, $default);
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
    public function getCustomDomainOptions(): string
    {
        return $this->options->get(self::CUSTOM_DOMAIN_OPTIONS, 'yes');
    }

    /**
     * @param string $customDomainOptions
     */
    public function setCustomDomainOptions(string $customDomainOptions): void
    {
        $this->options->set(self::CUSTOM_DOMAIN_OPTIONS, $customDomainOptions);
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
     * @return int
     */
    public function getDismissedReviewNotice(): int
    {
        return $this->options->get(self::DISMISSED_REVIEW_NOTICE, 0);
    }

    /**
     * @param int $dismissedReviewNotice
     */
    public function setDismissedReviewNotice(int $dismissedReviewNotice): void
    {
        $this->options->set(self::DISMISSED_REVIEW_NOTICE, $dismissedReviewNotice);
    }
    /**
     * @return int
     */
    public function getAnyOrderCompleted(): int
    {
        return $this->options->get(self::ANY_ORDER_COMPLETED, 0);
    }

    /**
     * @param int $anyOrderCompleted
     */
    public function setAnyOrderCompleted(int $anyOrderCompleted): void
    {
        $this->options->set(self::ANY_ORDER_COMPLETED, $anyOrderCompleted);
    }

    /**
     * @return int
     */
    public function getDismissedSavedCardsNotice(): int
    {
        return $this->options->get(self::DISMISSED_SAVED_CARDS_NOTICE, 0);
    }

    /**
     * @param int $dismissedSavedCardsNotice
     */
    public function setDismissedSavedCardsNotice(int $dismissedSavedCardsNotice): void
    {
        $this->options->set(self::DISMISSED_SAVED_CARDS_NOTICE, $dismissedSavedCardsNotice);
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
        return $this->options->get(self::CHECKBOX_CHECKOUT_TEST_MODE, 'yes');
    }

    /**
     * @param string $checkboxCheckoutTestMode
     */
    public function setCheckboxCheckoutTestMode(string $checkboxCheckoutTestMode): void
    {
        $this->options->set(self::CHECKBOX_CHECKOUT_TEST_MODE, $checkboxCheckoutTestMode);
    }

    /**
     * @return array<string>
     */
    public function getAvailablePaymentGateways(): array
    {
        return $this->availablePaymentGateways;
    }

    /**
     * @param string $paymentGateway
     */
    public function addAvailablePaymentGateway(string $paymentGateway): void
    {
        if (!in_array($paymentGateway, $this->availablePaymentGateways, true)) {
            $this->availablePaymentGateways[] = $paymentGateway;
        }
    }

    /**
     * @param AbstractGateway $gateway
     * @param $default
     *
     * @return mixed|string
     */
    public function getGatewayTitle(AbstractGateway $gateway, $default)
    {
        return $this->options->getGatewayOption($gateway, self::GATEWAY_TITLE, $default);
    }

    /**
     * @param AbstractGateway $gateway
     * @param string $default
     *
     * @return string
     */
    public function getCheckoutDateExpirationPix(AbstractGateway $gateway, string $default): string
    {
        return $this->options->getGatewayOption($gateway, self::CHECKOUT_EXPIRATION_DATE_PIX, $default);
    }
}
