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
        return $this->gateway->getPaymentFieldsParams();
    }
}
