<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use InvalidArgumentException;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixValidator;
use MercadoPago\Woocommerce\SmartPix\SmartPixExistingPixException;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixValidatorTest extends TestCase
{
    use SmartPixOrderDetailsFixture;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testValidOrderUsesServerFinancialValuesAndDoesNotReturnProof(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->twice()->andReturn(321);
        $order->shouldReceive('get_payment_method')->once()->andReturn(PixGateway::ID);
        $order->shouldReceive('is_paid')->once()->andReturn(false);
        $order->shouldReceive('needs_payment')->once()->andReturn(true);
        $order->shouldReceive('get_currency')->once()->andReturn('BRL');
        $order->shouldReceive('get_total')->once()->andReturn('4.00');
        $this->stubOrderDetails($order);

        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->once()->andReturn('MLB');
        $seller->shouldNotReceive('getCollectorId');
        $seller->shouldReceive('getCredentialsAccessToken')->once()->andReturn('SYNTHETIC_SELLER_CREDENTIAL');

        $result = (new SmartPixValidator($seller))->validate($order, 321, 'classic', [
            'token'                => 'SYNTHETIC_OPAQUE_PROOF%2F',
            'openFinanceAccountId' => '00000000-0000-4000-8000-000000000000',
        ]);

        $this->assertSame([
            'surface'           => 'classic',
            'country'           => 'MLB',
            'payment_contract'  => 'orders_smart_pix_v1',
            'attempt_reference' => 'WC-321',
            'order_reference'   => null,
            'amount'            => ['value' => '4.00', 'currency' => 'BRL'],
            'refund_amount'     => null,
            'order_details'     => $this->expectedOrderDetails(),
        ], $result);
        $this->assertStringNotContainsString('SYNTHETIC_OPAQUE_PROOF', json_encode($result));
    }

    public function testExistingBillingCpfIsNormalizedWithoutCheckoutDocumentFields(): void
    {
        [$validator, $order] = $this->fixture([]);
        $result = $validator->validate($order, 321, 'classic', $this->proof());

        $this->assertSame(
            ['type' => 'CPF', 'number' => '12345678909'],
            $result['order_details']['payer']['identification']
        );
    }

    /** @dataProvider documentFreeSurfaceProvider */
    public function testPaymentDoesNotRequireABuyerDocument(string $surface, ?string $key): void
    {
        [$validator, $order] = $this->fixture(['details' => ['billing_cpf' => '']]);
        $result = $validator->validate($order, 321, $surface, $this->proof(), $key);

        $this->assertArrayNotHasKey('identification', $result['order_details']['payer']);
        $this->assertSame($surface, $result['surface']);
        $this->assertSame('4.00', $result['amount']['value']);
    }

    public static function documentFreeSurfaceProvider(): array
    {
        return [
            'classic' => ['classic', null],
            'blocks' => ['blocks', null],
            'order pay' => ['order_pay', 'wc_order_synthetic_key'],
        ];
    }

    public function testExactExtraDecimalsDoNotBlockValidOrder(): void
    {
        [$validator, $order] = $this->fixture(['total' => '4.000']);
        $result = $validator->validate($order, 321, 'classic', $this->proof());

        $this->assertSame('WC-321', $result['attempt_reference']);
        $this->assertSame('4.00', $result['amount']['value']);
    }

    public function testMissingLegacyCollectorCacheDoesNotBlockTrustedSellerCredential(): void
    {
        [$validator, $order] = $this->fixture(['collector' => '']);
        $result = $validator->validate($order, 321, 'classic', $this->proof());
        $this->assertSame('MLB', $result['country']);
        $this->assertSame('4.00', $result['amount']['value']);
    }

    /**
     * @dataProvider validCheckoutProvider
     */
    public function testSupportedContextsPreserveTheAuthoritativeDecimal(
        string $surface,
        string $total,
        string $expectedAmount,
        ?string $key
    ): void {
        [$validator, $order] = $this->fixture(['total' => $total]);
        $proof = $this->proof();
        $result = $validator->validate($order, 321, $surface, $proof, $key);

        $this->assertSame($expectedAmount, $result['amount']['value']);
        $this->assertSame($surface, $result['surface']);
        $this->assertSame('SYNTHETIC_OPAQUE_PROOF%2F', $proof['token']);
    }

    public static function validCheckoutProvider(): array
    {
        return [
            'blocks' => ['blocks', '4', '4.00', null],
            'order pay' => ['order_pay', '4.0', '4.00', 'wc_order_synthetic_key'],
            'extra zero decimals' => ['classic', '4.0000', '4.00', null],
            'one decimal followed by zeros' => ['blocks', '4.1000', '4.10', null],
            'zero fractional digits' => ['classic', '4.000000', '4.00', null],
            'one cent' => ['classic', '0.01', '0.01', null],
            'decimal precision' => ['classic', '999999999999.99', '999999999999.99', null],
        ];
    }

    /** @dataProvider technicalReferenceProvider */
    public function testTechnicalReferenceUsesTheServerOrderId(int $id): void
    {
        [$validator, $order] = $this->fixture(['id' => $id]);
        $result = $validator->validate($order, $id, 'classic', $this->proof());
        $this->assertSame('WC-' . $id, $result['attempt_reference']);
    }

    public static function technicalReferenceProvider(): array
    {
        return [
            'short id' => [1],
            'standard id' => [321],
            'maximum native id' => [PHP_INT_MAX],
        ];
    }

    public function testTechnicalReferenceUsesTheConfiguredStoreIdentificator(): void
    {
        WP_Mock::tearDown();
        WP_Mock::setUp();
        WP_Mock::userFunction('get_option', [
            'args' => ['_mp_store_identificator', 'WC-'],
            'times' => 1,
            'return' => 'LOJA-',
        ]);

        [$validator, $order] = $this->fixture(['id' => 321]);
        $result = $validator->validate($order, 321, 'classic', $this->proof());

        $this->assertSame('LOJA-321', $result['attempt_reference']);
    }

    /** @dataProvider invalidReferenceTypeProvider */
    public function testUntrustedOrderIdTypesCannotBecomeTechnicalReferences($id): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('get_id');
        $seller = Mockery::mock(Seller::class);
        $this->expectException(\TypeError::class);
        (new SmartPixValidator($seller))->validate($order, $id, 'classic', $this->proof());
    }

    public static function invalidReferenceTypeProvider(): array
    {
        return [['321'], ['../321'], [1.5], [null], [[]], [true]];
    }

    /**
     * @dataProvider invalidInputProvider
     * @param array<string, mixed> $changes
     */
    public function testInvalidInputIsRejectedByValidator(array $changes): void
    {
        [$validator, $order] = $this->fixture($changes);
        $proof = $changes['proof'] ?? $this->proof();

        $this->expectException(InvalidArgumentException::class);
        $validator->validate(
            !empty($changes['missing_order']) ? null : $order,
            $changes['expected_id'] ?? 321,
            $changes['surface'] ?? 'classic',
            $proof,
            $changes['key'] ?? null
        );
    }

    public static function invalidInputProvider(): array
    {
        $proof = [
            'token' => 'SYNTHETIC_OPAQUE_PROOF%2F',
            'openFinanceAccountId' => '00000000-0000-4000-8000-000000000000',
        ];
        $cases = [
            'missing order' => ['missing_order' => true],
            'unpersisted context' => ['expected_id' => 0],
            'negative context' => ['expected_id' => -1],
            'unpersisted order' => ['id' => 0],
            'negative order' => ['id' => -1],
            'another checkout order' => ['id' => 322],
            'unknown surface' => ['surface' => 'server'],
            'missing order pay key' => ['surface' => 'order_pay'],
            'another order pay key' => ['surface' => 'order_pay', 'key' => 'another_key'],
            'empty stored key' => ['surface' => 'order_pay', 'stored_key' => '', 'key' => 'another_key'],
            'another gateway' => ['gateway' => 'other_gateway'],
            'paid order' => ['paid' => true],
            'not payable' => ['payable' => false],
            'another currency' => ['currency' => 'USD'],
            'another seller country' => ['country' => 'MLA'],
            'missing credentials' => ['credential' => ''],
            'zero total' => ['total' => '0.00'],
            'negative total' => ['total' => '-4.00'],
            'subcent total' => ['total' => '4.005'],
            'nonzero fourth decimal' => ['total' => '4.0001'],
            'oversized total' => ['total' => str_repeat('1', 65)],
            'zero with extra decimals' => ['total' => '0.000'],
            'float total' => ['total' => 4.00],
            'exponent total' => ['total' => '4e0'],
            'trailing newline total' => ['total' => "4.00\n"],
            'missing proof' => ['proof' => []],
            'missing account' => ['proof' => ['token' => $proof['token']]],
            'empty token' => ['proof' => array_replace($proof, ['token' => ''])],
            'blank token' => ['proof' => array_replace($proof, ['token' => '   '])],
            'nonstring token' => ['proof' => array_replace($proof, ['token' => ['untrusted']])],
            'oversized token' => ['proof' => array_replace($proof, ['token' => str_repeat('a', 4097)])],
            'invalid account shape' => ['proof' => array_replace($proof, ['openFinanceAccountId' => 'invalid'])],
            'nonstrings account' => ['proof' => array_replace($proof, ['openFinanceAccountId' => 123])],
        ];
        foreach (['amount', 'currency', 'seller', 'country', 'sponsor_id', 'eligible', 'mp-smart-pix-selected', 'doc_type', 'doc_number'] as $field) {
            $cases['unexpected ' . $field] = ['proof' => $proof + [$field => '1']];
        }

        return array_map(static function (array $case): array {
            return [$case];
        }, $cases);
    }

    /**
     * @dataProvider invalidOrderDetailsProvider
     * @param array<string, mixed> $details
     */
    public function testUnusableOrderDetailsFailClosedAndEmitValidationOnce(array $details, string $message): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::ERROR, 'true', [
            'operation' => 'validation', 'source' => 'sync_response', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'category' => 'validation',
        ], 'checkout.validation: InvalidArgumentException [0]: ' . $message);
        $sink->shouldNotReceive('distribution');
        [$validator, $order] = $this->fixture(['details' => $details], new SmartPixTelemetry($sink));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $validator->validate($order, 321, 'classic', $this->proof());
    }

    public static function invalidOrderDetailsProvider(): array
    {
        return [
            'no product lines' => [['items' => []], 'Invalid Smart Pix order items.'],
            'invalid email' => [['email' => 'not-an-email'], 'Invalid Smart Pix payer.'],
            'missing address' => [['ip' => ''], 'Invalid Smart Pix buyer address.'],
        ];
    }

    public function testMissingLastNameIsSentEmptyLikeTheOtherGateways(): void
    {
        [$validator, $order] = $this->fixture(['details' => ['last_name' => '']]);

        $result = $validator->validate($order, 321, 'classic', $this->proof());

        $this->assertSame('', $result['order_details']['payer']['last_name']);
    }

    public function testNonReconcilingCartKeepsTheAuthoritativeAmount(): void
    {
        [$validator, $order] = $this->fixture(['total' => '4.00', 'details' => ['line_total' => '3.50']]);
        $result = $validator->validate($order, 321, 'classic', $this->proof());

        $this->assertSame(['value' => '4.00', 'currency' => 'BRL'], $result['amount']);
        $this->assertSame([[
            'id' => '321', 'title' => 'Consolidated Items', 'unit_price' => '4.00', 'quantity' => 1, 'type' => 'others',
        ]], $result['order_details']['items']);
    }

    /**
     * @param array<string, mixed> $changes
     * @return array{SmartPixValidator, WC_Order}
     */
    /** @dataProvider telemetryValidationFailureProvider */
    public function testValidationErrorsEmitOnceWithoutChangingTheException(array $changes, string $message): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::ERROR, 'true', [
            'operation' => 'validation', 'source' => 'sync_response', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'category' => 'validation',
        ], 'checkout.validation: InvalidArgumentException [0]: ' . $message);
        $sink->shouldNotReceive('distribution');
        [$validator, $order] = $this->fixture($changes, new SmartPixTelemetry($sink));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $validator->validate($order, 321, 'classic', $changes['proof'] ?? $this->proof());
    }

    public static function telemetryValidationFailureProvider(): array
    {
        return [
            [['id' => 322], 'Invalid Smart Pix order context.'],
            [['total' => '4.005'], 'Invalid Smart Pix order total.'],
            [['proof' => []], 'Invalid Smart Pix fields.'],
        ];
    }

    public function testTelemetryThrowableDoesNotReplaceValidationException(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->andThrow(new \Error('SYNTHETIC_PRIVATE_TELEMETRY'));
        $sink->shouldNotReceive('distribution');
        [$validator, $order] = $this->fixture(['total' => '4.005'], new SmartPixTelemetry($sink));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Smart Pix order total.');
        $validator->validate($order, 321, 'classic', $this->proof());
    }

    public function testSuccessfulValidationWithTelemetryPreservesExactMoneyAndEmitsNoErrors(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        [$validator, $order] = $this->fixture(['total' => '9007199254740993.01'], new SmartPixTelemetry($sink));
        $result = $validator->validate($order, 321, 'classic', $this->proof());
        $this->assertSame('9007199254740993.01', $result['amount']['value']);
        $this->assertSame('BRL', $result['amount']['currency']);
    }

    /** @dataProvider documentFreeSurfaceProvider */
    public function testOrderWithAConventionalPixQrIssuedIsRefusedBeforeOrders(string $surface, ?string $key): void
    {
        [, $order] = $this->fixture([]);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldNotReceive('getSiteId', 'getCredentialsAccessToken');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('hasConventionalPixPayment')->once()->with($order)->andReturn(true);
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::ERROR, 'true', Mockery::type('array'), 'checkout.validation: MercadoPago\\Woocommerce\\SmartPix\\SmartPixExistingPixException [0]: A conventional Pix QR of this order may still be paid.');

        $this->expectException(SmartPixExistingPixException::class);
        $this->expectExceptionMessage('A conventional Pix QR of this order may still be paid.');

        (new SmartPixValidator($seller, new SmartPixTelemetry($sink), null, $metadata))
            ->validate($order, 321, $surface, $this->proof(), $key);
    }

    public function testGivenAnInvalidOrderPayKeyWhenAnExistingPixIsPresentThenItsExistenceIsNotInspected(): void
    {
        [, $order] = $this->fixture([]);
        $seller = Mockery::mock(Seller::class);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('hasConventionalPixPayment');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Smart Pix order context.');

        (new SmartPixValidator($seller, null, null, $metadata))
            ->validate($order, 321, 'order_pay', $this->proof(), 'WRONG_ORDER_KEY');
    }

    private function fixture(array $changes, ?SmartPixTelemetry $telemetry = null): array
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($changes['id'] ?? 321);
        $order->shouldReceive('get_order_key')->andReturn($changes['stored_key'] ?? 'wc_order_synthetic_key');
        $order->shouldReceive('get_payment_method')->andReturn($changes['gateway'] ?? PixGateway::ID);
        $order->shouldReceive('is_paid')->andReturn($changes['paid'] ?? false);
        $order->shouldReceive('needs_payment')->andReturn($changes['payable'] ?? true);
        $order->shouldReceive('get_currency')->andReturn($changes['currency'] ?? 'BRL');
        $order->shouldReceive('get_total')->andReturn($changes['total'] ?? '4.00');
        if (isset($changes['details'])) {
            $this->stubOrderDetailsWith($order, $changes['details']);
        } else {
            $this->stubOrderDetails($order, is_string($changes['total'] ?? null) ? $changes['total'] : '4.00');
        }
        $order->shouldNotReceive('save');
        $order->shouldNotReceive('update_meta_data');
        $order->shouldNotReceive('payment_complete');

        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn($changes['country'] ?? 'MLB');
        $seller->shouldReceive('getCollectorId')->andReturn($changes['collector'] ?? '123456');
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn($changes['credential'] ?? 'SYNTHETIC_CREDENTIAL');

        return [new SmartPixValidator($seller, $telemetry), $order];
    }

    /**
     * @return array<string, string>
     */
    private function proof(): array
    {
        return [
            'token' => 'SYNTHETIC_OPAQUE_PROOF%2F',
            'openFinanceAccountId' => '00000000-0000-4000-8000-000000000000',
        ];
    }
}
