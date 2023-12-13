<?php

namespace MercadoPago\Woocommerce\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

class BasicBlock extends AbstractBlock
{
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
        return $this->gateway->getPaymentFieldsParams();
    }
}
