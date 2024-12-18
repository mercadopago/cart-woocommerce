<?php
declare(strict_types=1);

define('TESTS_ROOT_DIR', dirname(__DIR__));
define('ROOT_DIR', dirname(TESTS_ROOT_DIR));
define('ABSPATH', __DIR__ . '/');

require_once __DIR__ . '/../vendor/autoload.php';

WP_Mock::activateStrictMode(); // Each test must declare it's own mock expectations
WP_Mock::bootstrap();

Hamcrest\Util::registerGlobalFunctions();
