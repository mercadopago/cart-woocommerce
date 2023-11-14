<?php

namespace MercadoPago\Woocommerce\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

class CustomBlock extends AbstractBlock {
    /**
     * @var string
     */
    protected $scriptName = 'custom';

    /**
     * @var string
     */
    protected $name = 'woo-mercado-pago-custom';

    /**
     * CustomBlock constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->storeTranslations = $this->mercadopago->storeTranslations->customCheckout;
    }

    /**
     * Set payment block script params
     *
     * @return array
     */
    public function getScriptParams(): array
    {
        return [
            'test_mode_title'       => $this->storeTranslations['test_mode_title'],
            'test_mode_description' => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'   => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'    => $this->links['docs_integration_test'],
        ];
    }
}