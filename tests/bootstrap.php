<?php

declare(strict_types=1);

error_reporting(~E_DEPRECATED); // Ignore deprecated warnings
define('ABSPATH', __DIR__ . '/');
define('MP_PLATFORM_ID', 'WOOCOMMERCE_MP_TEST');
define('MP_PRODUCT_ID_DESKTOP', 'WOOCOMMERCE_MP_TEST_DESKTOP');
define('MP_VERSION', random()->semver());
define('MP_SUPER_TOKEN_USE_BUNDLE', false); // Default to false for tests

require_once __DIR__ . '/../vendor/autoload.php';

WP_Mock::activateStrictMode(); // Each test must declare it's own mock expectations
WP_Mock::bootstrap();
Hamcrest\Util::registerGlobalFunctions();

function random(string $locale = "pt_BR")
{
    static $faker;
    return $faker ??= Faker\Factory::create($locale);
}
