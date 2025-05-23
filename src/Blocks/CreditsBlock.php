<?php

namespace MercadoPago\Woocommerce\Blocks;

use MercadoPago\Woocommerce\Helpers\Template;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsBlock extends AbstractBlock
{
    protected $scriptName = 'credits';

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
        return [
            'content' => Template::html(
                'public/checkouts/credits-checkout-container',
                $this->gateway->getPaymentFieldsParams()
            ),
            'icon' => $this->gateway->icon
        ];
    }
}
