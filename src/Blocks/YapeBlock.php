<?php

namespace MercadoPago\Woocommerce\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

class YapeBlock extends AbstractBlock
{
    protected $scriptName = 'yape';

    protected $name = 'woo-mercado-pago-yape';

    /**
     * YapeBlock constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->storeTranslations = $this->mercadopago->storeTranslations->yapeCheckout;
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
