<?php

namespace MercadoPago\Woocommerce\Notification;

use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookNotification extends AbstractNotification
{

    /**
     * @var Requester`
     */
    public $requester;

    /**
     * AbstractNotification constructor
     */
    public function __construct(Logs $logs, Requester $requester)
    {
        parent::__construct($logs);
        $this->requester = $requester;
    }

}