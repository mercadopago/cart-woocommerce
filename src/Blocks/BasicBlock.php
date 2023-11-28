<?php

namespace MercadoPago\Woocommerce\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

class BasicBlock extends AbstractBlock {
    /**
     * @var string
     */
    protected $scriptName = 'basic';

    /**
     * @var string
     */
    protected $name = 'woo-mercado-pago-basic';

    /**
     * BasicBlock constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->storeTranslations = $this->mercadopago->storeTranslations->basicCheckout;
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