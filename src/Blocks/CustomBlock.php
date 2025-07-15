<?php

namespace MercadoPago\Woocommerce\Blocks;

use MercadoPago\Woocommerce\Helpers\Template;

if (!defined('ABSPATH')) {
    exit;
}

class CustomBlock extends AbstractBlock
{
    protected $scriptName = 'custom';

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
            'content' => Template::html(
                'public/checkouts/custom-checkout',
                $this->gateway->getPaymentFieldsParams()
            ),
            'icon' => $this->gateway->icon,
        ];
    }
}
