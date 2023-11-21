<?php

namespace MercadoPago\Woocommerce\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsBlock extends AbstractBlock {
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
        return [
            'test_mode_title'           => $this->storeTranslations['test_mode_title'],
            'test_mode_description'     => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'       => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'        => $this->links['docs_integration_test'],
            'checkout_benefits_title'   => $this->storeTranslations['checkout_benefits_title'],
            'checkout_benefits_items'   => wp_json_encode($checkoutBenefitsItems),
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
