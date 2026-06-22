<?php

namespace {
    // Minimal stub of WooCommerce's WC_Checkout so CheckoutValidator can extend it
    // in the unit test environment. Only the members CheckoutValidator relies on.
    if (!class_exists('WC_Checkout')) {
        class WC_Checkout
        {
            public $postedData = [];

            public function get_posted_data()
            {
                return $this->postedData;
            }

            protected function validate_posted_data(&$data, &$errors)
            {
                // no-op: real WooCommerce validation is out of scope for this unit test
            }
        }
    }
}

namespace MercadoPago\Woocommerce\Tests\Helpers {

    use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
    use MercadoPago\Woocommerce\Helpers\CheckoutValidator;
    use PHPUnit\Framework\TestCase;
    use Mockery;
    use WP_Error;
    use WP_Mock;

    /**
     * @runTestsInSeparateProcesses
     * @preserveGlobalState disabled
     */
    class CheckoutValidatorTest extends TestCase
    {
        use WoocommerceMock;

        /**
         * WP_Mock cannot match the dynamic WP_Error instance passed to
         * woocommerce_after_checkout_validation (actions are keyed by spl_object_hash).
         * Disabling strict mode lets the WooCommerce hooks run as no-ops while we still
         * assert the plugin-specific behaviour (terms error + wc_clear_notices).
         */
        private function disableWpMockStrictMode(): void
        {
            $property = new \ReflectionProperty(WP_Mock::class, '__strict_mode');
            $property->setAccessible(true);
            $property->setValue(null, false);
        }

        public function testValidateClearsNoticesAndDoesNotAddTermsErrorWhenAccepted(): void
        {
            $this->disableWpMockStrictMode();
            WP_Mock::userFunction('__', ['return_arg' => 0]);
            WP_Mock::userFunction('wc_get_notices', ['return' => []]);
            WP_Mock::userFunction('wc_clear_notices')->once();

            $errorsMock = Mockery::mock('overload:' . WP_Error::class);
            $errorsMock->shouldReceive('add')->never();

            $validator = new CheckoutValidator();
            $validator->postedData = ['terms' => '1', 'terms-field' => '1'];

            $result = $validator->validate();

            $this->assertInstanceOf(WP_Error::class, $result);
        }

        public function testValidateAddsTermsErrorWhenTermsCheckboxNotAccepted(): void
        {
            $this->disableWpMockStrictMode();
            WP_Mock::userFunction('__', ['return_arg' => 0]);
            WP_Mock::userFunction('wc_get_notices', ['return' => []]);
            WP_Mock::userFunction('wc_clear_notices')->once();

            $errorsMock = Mockery::mock('overload:' . WP_Error::class);
            $errorsMock
                ->shouldReceive('add')
                ->once()
                ->withArgs(function ($code) {
                    return $code === 'terms';
                });

            $validator = new CheckoutValidator();
            $validator->postedData = ['terms-field' => '1'];

            $result = $validator->validate();

            $this->assertInstanceOf(WP_Error::class, $result);
        }

        public function testValidateFoldsCheckoutProcessErrorNoticesIntoErrors(): void
        {
            $this->disableWpMockStrictMode();
            WP_Mock::userFunction('__', ['return_arg' => 0]);
            // A third-party callback hooked to woocommerce_checkout_process reports its
            // failure via a WooCommerce error notice (not via the WP_Error object).
            WP_Mock::userFunction('wc_get_notices', [
                'return' => [
                    ['notice' => 'Third-party validation failed', 'data' => []],
                ],
            ]);
            WP_Mock::userFunction('wc_clear_notices')->once();

            $errorsMock = Mockery::mock('overload:' . WP_Error::class);
            $errorsMock
                ->shouldReceive('add')
                ->once()
                ->withArgs(function ($code, $message) {
                    return $code === 'checkout_process' && $message === 'Third-party validation failed';
                });

            $validator = new CheckoutValidator();
            // terms accepted, so the only error comes from the harvested notice
            $validator->postedData = ['terms' => '1', 'terms-field' => '1'];

            $result = $validator->validate();

            $this->assertInstanceOf(WP_Error::class, $result);
        }
    }
}
