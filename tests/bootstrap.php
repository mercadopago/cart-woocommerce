<?php

declare(strict_types=1);

define('TESTS_ROOT_DIR', dirname(__DIR__));
define('ROOT_DIR', dirname(TESTS_ROOT_DIR));
define('ABSPATH', __DIR__ . '/');
define('MP_PLATFORM_ID', 'WOOCOMMERCE_MP_TEST');
define('MP_PRODUCT_ID_DESKTOP', 'WOOCOMMERCE_MP_TEST_DESKTOP');

require_once __DIR__ . '/../vendor/autoload.php';

WP_Mock::activateStrictMode(); // Each test must declare it's own mock expectations
WP_Mock::bootstrap();
Hamcrest\Util::registerGlobalFunctions();

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $code;
        private $message;

        public function __construct($code, $message)
        {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}
