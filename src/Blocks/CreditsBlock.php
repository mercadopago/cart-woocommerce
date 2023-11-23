<?php

namespace MercadoPago\Woocommerce\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsBlock extends AbstractBlock
{
    /**
     * @var string
     */
    protected $scriptName = 'credits';

    /**
     * @var string
     */
    protected $name = 'woo-mercado-pago-credits';

    /**
     * CustomBlock constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->storeTranslations = $this->mercadopago->storeTranslations->creditsCheckout;
    }

    /**
     * Set payment block script params
     *
     * @return array
     */
    public function getScriptParams(): array
    {
        $checkoutBenefitsItems = $this->getBenefits();
        $checkoutRedirectSrc   = $this->mercadopago->url->getPluginFileUrl(
            'assets/images/checkouts/basic/cho-pro-redirect-v2',
            '.png',
            true
        );
        $testMode = (get_option("checkbox_checkout_test_mode") == "no") ? false : true;
        return [
            'test_mode_title'           => $this->storeTranslations['test_mode_title'],
            'test_mode_description'     => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'       => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'        => $this->links['docs_integration_test'],
            'checkout_benefits_title'   => $this->storeTranslations['checkout_benefits_title'],
            'checkout_benefits_items'   => wp_json_encode($checkoutBenefitsItems),
            'checkout_redirect_text'    => $this->storeTranslations['checkout_redirect_text'],
            'checkout_redirect_src'     => $checkoutRedirectSrc,
            'checkout_redirect_alt'     => $this->storeTranslations['checkout_redirect_alt'],
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'test_mode' => $testMode,
        ];
    }

    /**
     * Get benefits items
     *
     * @return array
     */
    private function getBenefits(): array
    {
        return [
            $this->storeTranslations['checkout_benefits_1'],
            $this->storeTranslations['checkout_benefits_2'],
            $this->storeTranslations['checkout_benefits_3'],
        ];
    }
}
