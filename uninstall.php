<?php

use MercadoPago\Woocommerce\Api\Core\CoreApiClient;
use MercadoPago\Woocommerce\Api\Core\FunnelClient;
use MercadoPago\Woocommerce\Api\Http\WordPressHttpClient;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Funnel\Uninstall;
use MercadoPago\Woocommerce\Hooks\Options;

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

try {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoload) && is_readable($autoload)) {
        require_once $autoload;

        $uninstall = new Uninstall(
            new Store(new Options()),
            new FunnelClient(new CoreApiClient(new WordPressHttpClient('https://api.mercadopago.com')))
        );
        $uninstall->send();
    }
} catch (\Throwable $error) {
    try {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error(
                'Unable to report plugin uninstallation to the integration funnel.',
                ['source' => 'woocommerce-mercadopago']
            );
        }
    } catch (\Throwable $loggingError) {
        // Logging must not prevent credential cleanup when WooCommerce is unavailable.
    }
}

delete_option('_mp_access_token_prod');
delete_option('_mp_public_key_prod');
delete_option('_mp_public_key_test');
delete_option('_mp_access_token_test');
