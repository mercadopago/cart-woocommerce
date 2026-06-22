<?php

namespace MercadoPago\Woocommerce\Tests\Endpoints;

use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Endpoints\CheckoutValidation;
use MercadoPago\Woocommerce\Helpers\CheckoutValidator;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use Mockery;
use WP_Error;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CheckoutValidationTest extends TestCase
{
    use WoocommerceMock;

    public function testRegistersWCAjaxEndpoint(): void
    {
        $endpointsMock = Mockery::mock(Endpoints::class);
        $nonceMock     = Mockery::mock(Nonce::class);

        $endpointsMock
            ->shouldReceive('registerWCAjaxEndpoint')
            ->once()
            ->withArgs([CheckoutValidation::VALIDATION_ENDPOINT, Mockery::type('callable')]);

        new CheckoutValidation($endpointsMock, $nonceMock);

        $this->assertTrue(true);
    }

    public function testValidateCheckoutReturnsValidWhenNoErrors(): void
    {
        $endpointsMock = Mockery::mock(Endpoints::class);
        $endpointsMock->shouldReceive('registerWCAjaxEndpoint')->once();

        $nonceMock = Mockery::mock(Nonce::class);
        $nonceMock
            ->shouldReceive('validateNonce')
            ->once()
            ->withArgs(['woocommerce-process_checkout', 'nonce-value']);

        Mockery::mock('overload:' . Form::class)
            ->shouldReceive('sanitizedPostData')
            ->once()
            ->withArgs(['woocommerce-process-checkout-nonce'])
            ->andReturn('nonce-value');

        $errorsMock = Mockery::mock('overload:' . WP_Error::class);
        $errorsMock->shouldReceive('get_error_codes')->andReturn([]);

        Mockery::mock('overload:' . CheckoutValidator::class)
            ->shouldReceive('validate')
            ->once()
            ->andReturn($errorsMock);

        Mockery::mock('alias:' . Datadog::class)
            ->shouldReceive('getInstance')->andReturnSelf()
            ->shouldReceive('sendEvent')->andReturnNull();

        WP_Mock::userFunction('wp_send_json_success')
            ->once()
            ->with(['valid' => true, 'errors' => []]);

        $endpoint = new CheckoutValidation($endpointsMock, $nonceMock);
        $endpoint->mercadopagoValidateCheckout();

        $this->assertTrue(true);
    }

    public function testValidateCheckoutReturnsErrorsWhenInvalidWithoutMeteringFormErrors(): void
    {
        $endpointsMock = Mockery::mock(Endpoints::class);
        $endpointsMock->shouldReceive('registerWCAjaxEndpoint')->once();

        $nonceMock = Mockery::mock(Nonce::class);
        $nonceMock->shouldReceive('validateNonce')->once();

        Mockery::mock('overload:' . Form::class)
            ->shouldReceive('sanitizedPostData')
            ->andReturn('nonce-value');

        $errorsMock = Mockery::mock('overload:' . WP_Error::class);
        $errorsMock->shouldReceive('get_error_codes')->andReturn(['billing_postcode']);
        $errorsMock->shouldReceive('get_error_data')->withArgs(['billing_postcode'])->andReturn(['id' => 'billing_postcode']);
        $errorsMock->shouldReceive('get_error_messages')->withArgs(['billing_postcode'])->andReturn(['Postcode is a required field.']);

        Mockery::mock('overload:' . CheckoutValidator::class)
            ->shouldReceive('validate')
            ->once()
            ->andReturn($errorsMock);

        WP_Mock::userFunction('wp_strip_all_tags', ['return_arg' => 0]);

        // Form errors are an expected outcome and MUST NOT be metered — only latency is sent.
        $datadog = Mockery::mock('alias:' . Datadog::class);
        $datadog->shouldReceive('getInstance')->andReturnSelf();
        $datadog->shouldReceive('sendEvent')->with('MP_CUSTOM_CHECKOUT_AJAX_VALIDATION_LATENCY', Mockery::any())->once();
        $datadog->shouldReceive('sendEvent')->with('MP_CUSTOM_CHECKOUT_AJAX_VALIDATION_ERROR', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())->never();

        WP_Mock::userFunction('wp_send_json_success')
            ->once()
            ->with([
                'valid'  => false,
                'errors' => [
                    [
                        'field'   => 'billing_postcode',
                        'code'    => 'billing_postcode',
                        'message' => 'Postcode is a required field.',
                    ],
                ],
            ]);

        $endpoint = new CheckoutValidation($endpointsMock, $nonceMock);
        $endpoint->mercadopagoValidateCheckout();

        $this->assertTrue(true);
    }

    public function testValidateCheckoutMetersUnexpectedErrorWithContextAndDoesNotBlock(): void
    {
        $endpointsMock = Mockery::mock(Endpoints::class);
        $endpointsMock->shouldReceive('registerWCAjaxEndpoint')->once();

        $nonceMock = Mockery::mock(Nonce::class);
        $nonceMock->shouldReceive('validateNonce')->once();

        Mockery::mock('overload:' . Form::class)
            ->shouldReceive('sanitizedPostData')
            ->andReturn('nonce-value');

        Mockery::mock('overload:' . CheckoutValidator::class)
            ->shouldReceive('validate')
            ->once()
            ->andThrow(new \RuntimeException('boom'));

        // Unexpected errors ARE metered, with enough context (exception type + message) to diagnose.
        $datadog = Mockery::mock('alias:' . Datadog::class);
        $datadog->shouldReceive('getInstance')->andReturnSelf();
        $datadog
            ->shouldReceive('sendEvent')
            ->with('MP_CUSTOM_CHECKOUT_AJAX_VALIDATION_ERROR', 'RuntimeException', 'boom')
            ->once();

        WP_Mock::userFunction('wp_send_json_error')
            ->once()
            ->with(['error' => 'unexpected_error']);

        $endpoint = new CheckoutValidation($endpointsMock, $nonceMock);
        $endpoint->mercadopagoValidateCheckout();

        $this->assertTrue(true);
    }
}
