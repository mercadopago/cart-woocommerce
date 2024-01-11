<?php

namespace MercadoPago\Woocommerce\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

class TicketBlock extends AbstractBlock
{
    /**
     * @var string
     */
    protected $scriptName = 'ticket';

    /**
     * @var string
     */
    protected $name = 'woo-mercado-pago-ticket';

    /**
     * CustomBlock constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->storeTranslations = $this->mercadopago->storeTranslations->ticketCheckout;
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
