<?php

declare(strict_types=1);

error_reporting(~E_DEPRECATED); // Ignore deprecated warnings
define('ABSPATH', __DIR__ . '/');
define('MP_PLATFORM_ID', 'WOOCOMMERCE_MP_TEST');
define('MP_PRODUCT_ID_DESKTOP', 'WOOCOMMERCE_MP_TEST_DESKTOP');
define('MP_VERSION', random()->semver());
define('MP_SUPER_TOKEN_USE_BUNDLE', false); // Default to false for tests
// Active variant derived from the PHP source of truth (PLUGIN_SUPER_TOKEN_VERSION), so there is
// no hardcoded value to keep in sync with src/WoocommerceMercadoPago.php. Fail loud if it is
// missing — mirroring getActiveSuperTokenVersion() on the JS side — so a renamed/removed constant
// never silently runs the suite against the wrong variant.
preg_match(
    "/private const PLUGIN_SUPER_TOKEN_VERSION\s*=\s*'([^']+)'/",
    (string) file_get_contents(__DIR__ . '/../src/WoocommerceMercadoPago.php'),
    $mpSuperTokenVersionMatch
);
if (empty($mpSuperTokenVersionMatch[1])) {
    throw new \RuntimeException(
        'PLUGIN_SUPER_TOKEN_VERSION not found in src/WoocommerceMercadoPago.php — cannot resolve the active super-token variant.'
    );
}
define('MP_SUPER_TOKEN_VERSION', $mpSuperTokenVersionMatch[1]);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Mocks/WoocommerceGatewayStub.php';
require_once __DIR__ . '/Mocks/WoocommerceBlocksStub.php';
require_once __DIR__ . '/Mocks/SdkExceptionsStub.php';

WP_Mock::activateStrictMode(); // Each test must declare it's own mock expectations
WP_Mock::bootstrap();
Hamcrest\Util::registerGlobalFunctions();

function random(string $locale = "pt_BR")
{
    static $faker;
    return $faker ??= Faker\Factory::create($locale);
}
