<?php

namespace MercadoPago\Woocommerce\Blocks;

use MercadoPago\Woocommerce\Helpers\Template;

if (!defined('ABSPATH')) {
    exit;
}

class BasicBlock extends AbstractBlock
{
    protected $scriptName = 'basic';

    protected $name = 'woo-mercado-pago-basic';

    /**
     * Set payment block script params
     */
    public function getScriptParams(): array
    {
        return [
            'content' => Template::html(
                'public/checkouts/basic-checkout-container',
                $this->gateway->getPaymentFieldsParams()
            ),
            'icon' => $this->gateway->icon
        ];
    }
}
