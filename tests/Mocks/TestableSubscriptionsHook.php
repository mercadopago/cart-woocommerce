<?php

namespace MercadoPago\Woocommerce\Tests\Mocks;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers as WCHelpers;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Order\OrderMetadata;

/**
 * Concrete subclass of Subscriptions used in behaviour unit tests.
 *
 * No overrides — WCS presence is determined by SubscriptionsHelper::isWcsActive(),
 * which returns false in the test bootstrap (WC_Subscriptions is not defined),
 * so registerHooks() is never called during behaviour tests.
 *
 * For tests that need WCS hooks to be registered, use @runInSeparateProcess
 * and define WC_Subscriptions via eval() at the start of the test.
 */
class TestableSubscriptionsHook extends \MercadoPago\Woocommerce\Hooks\Subscriptions
{
    public function __construct(
        AutomaticPaymentsClient $apClient,
        SubscriptionsHelper $subscriptionsHelper,
        Store $store,
        Logs $logs,
        WCHelpers $helpers,
        OrderMetadata $orderMetadata
    ) {
        parent::__construct($apClient, $subscriptionsHelper, $store, $logs, $helpers, $orderMetadata);
    }
}
