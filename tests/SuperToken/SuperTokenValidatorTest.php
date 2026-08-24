<?php

namespace MercadoPago\Woocommerce\Tests\SuperToken;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\SuperToken\SuperTokenCheckout;
use MercadoPago\Woocommerce\SuperToken\SuperTokenValidator;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SuperTokenValidatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SuperTokenValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SuperTokenValidator();
    }

    public function testGetMissingFieldsReturnsEmptyWhenAllPresent(): void
    {
        $checkout = new SuperTokenCheckout([
            'authorized_pseudotoken' => 'pseudo',
            'amount'                 => '100',
            'payment_method_id'      => 'visa',
            'payment_type_id'        => 'credit_card',
            'installments'           => '3',
        ]);

        $this->assertSame([], $this->validator->getMissingFields($checkout));
    }

    public function testGetMissingFieldsListsMissingRequiredFieldsInOrder(): void
    {
        $checkout = new SuperTokenCheckout([
            'payment_type_id' => 'debit_card',
        ]);

        $this->assertSame(
            ['authorized_pseudotoken', 'amount', 'payment_method_id'],
            $this->validator->getMissingFields($checkout)
        );
    }

    public function testGetMissingFieldsTreatsZeroAmountAsPresent(): void
    {
        $checkout = new SuperTokenCheckout([
            'authorized_pseudotoken' => 'pseudo',
            'amount'                 => '0',
            'payment_method_id'      => 'account_money',
            'payment_type_id'        => 'account_money',
        ]);

        $this->assertSame([], $this->validator->getMissingFields($checkout));
    }

    public function testGetMissingFieldsRequiresInstallmentsForCreditCard(): void
    {
        $checkout = new SuperTokenCheckout([
            'authorized_pseudotoken' => 'pseudo',
            'amount'                 => '100',
            'payment_method_id'      => 'visa',
            'payment_type_id'        => 'credit_card',
            'installments'           => '0',
        ]);

        $this->assertSame(['installments_required_for_credit'], $this->validator->getMissingFields($checkout));
    }

    public function testGetMissingFieldsDoesNotRequireInstallmentsForDebitCard(): void
    {
        $checkout = new SuperTokenCheckout([
            'authorized_pseudotoken' => 'pseudo',
            'amount'                 => '100',
            'payment_method_id'      => 'debvisa',
            'payment_type_id'        => 'debit_card',
        ]);

        $this->assertSame([], $this->validator->getMissingFields($checkout));
    }

    public function testReportTelemetryDoesNotSendEventWhenValidationPassesEvenIfTokensDiffer(): void
    {
        $checkout = new SuperTokenCheckout([
            'token'                  => 'token-a',
            'authorized_pseudotoken' => 'token-b',
            'super_token_validation' => 'true',
        ]);

        $gateway = $this->buildGatewayMock();
        $gateway->datadog->shouldNotReceive('sendEvent');

        $this->validator->reportTelemetry($checkout, $gateway, 'flow-1');
    }

    public function testReportTelemetrySendsValidationFailedEventWhenValidationIsFalse(): void
    {
        $checkout = new SuperTokenCheckout([
            'token'                  => 'same-token',
            'authorized_pseudotoken' => 'same-token',
            'super_token_validation' => 'false',
        ]);

        $gateway = $this->buildGatewayMock();
        $gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with(
                'super_token_validation_failed',
                'true',
                'INCOMPLETE_SUPER_TOKEN_VALIDATION',
                'super_token',
                [
                    'site_id'         => 'MLB',
                    'environment'     => 'homol',
                    'cust_id'         => 'cust-1',
                    'sdk_instance_id' => 'flow-1',
                ]
            );

        $this->validator->reportTelemetry($checkout, $gateway, 'flow-1');
    }

    public function testReportTelemetryDoesNotSendEventWhenValidationIsBooleanFalse(): void
    {
        $checkout = new SuperTokenCheckout([
            'token'                  => 'same-token',
            'authorized_pseudotoken' => 'same-token',
            'super_token_validation' => false,
        ]);

        $gateway = $this->buildGatewayMock();
        $gateway->datadog->shouldNotReceive('sendEvent');

        $this->validator->reportTelemetry($checkout, $gateway, 'flow-1');
    }

    /**
     * @return AbstractGateway|Mockery\MockInterface
     */
    private function buildGatewayMock()
    {
        $gateway = Mockery::mock(AbstractGateway::class);
        $gateway->datadog = Mockery::mock(Datadog::class);
        $gateway->mercadopago = Mockery::mock(WoocommerceMercadoPago::class);
        $gateway->mercadopago->sellerConfig = Mockery::mock(Seller::class);
        $gateway->mercadopago->storeConfig = Mockery::mock(Store::class);

        $gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $gateway->mercadopago->sellerConfig->shouldReceive('getCustIdFromAT')->andReturn('cust-1');
        $gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->andReturn(true);

        return $gateway;
    }
}
