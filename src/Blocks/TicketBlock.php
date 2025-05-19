<?php

namespace MercadoPago\Woocommerce\Blocks;

use MercadoPago\Woocommerce\Helpers\Template;

if (!defined('ABSPATH')) {
    exit;
}

class TicketBlock extends AbstractBlock
{
    protected $scriptName = 'ticket';

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
        return [
            'content' => Template::html(
                'public/checkouts/ticket-checkout',
                $this->gateway->getPaymentFieldsParams()
            ),
            'icon' => $this->gateway->icon
        ];
    }
}
