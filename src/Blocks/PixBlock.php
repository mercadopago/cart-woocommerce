<?php

namespace MercadoPago\Woocommerce\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

class PixBlock extends AbstractBlock
{
    /**
     * @var string
     */
    protected $scriptName = 'pix';

    /**
     * @var string
     */
    protected $name = 'woo-mercado-pago-pix';

    /**
     * CustomBlock constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->storeTranslations = $this->mercadopago->storeTranslations->pixCheckout;
    }

    /**
     * Set payment block script params
     *
     * @return array
     */
    public function getScriptParams(): array
    {
        $pix_template_src   = $this->mercadopago->url->getPluginFileUrl(
            'assets/images/checkouts/pix/pix',
            '.png',
            true
        );
        $testMode = (get_option("checkbox_checkout_test_mode") == "no") ? false : true;
        return [
            'test_mode_title'           => $this->storeTranslations['test_mode_title'],
            'test_mode_description'     => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'       => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'        => $this->links['docs_integration_test'],
            'pix_template_title'        => $this->storeTranslations['pix_template_title'],
            'pix_template_subtitle'     => $this->storeTranslations['pix_template_subtitle'],
            'pix_template_alt'          => $this->storeTranslations['pix_template_alt'],
            'pix_template_src'          => $pix_template_src,
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'test_mode' => $testMode,
        ];
    }
}
