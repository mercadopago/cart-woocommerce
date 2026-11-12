<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use InvalidArgumentException;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixAttemptRepository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;

class SmartPixAttemptRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testCreatesAndPersistsANewAttemptBeforeItCanBeUsed(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('read_meta_data')->with(true)->twice();

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')
            ->with($order)
            ->twice()
            ->andReturn('', OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getIdempotencyKey')
            ->with($order)
            ->twice()
            ->andReturn('', 'attempt_aaaaaaaaaaaaaaaa');
        $metadata->shouldReceive('persistSmartPixAttempt')
            ->with($order, 'attempt_aaaaaaaaaaaaaaaa')
            ->once();

        $repository = new SmartPixAttemptRepository($metadata);

        $this->assertSame(
            [
                'payment_contract' => OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
                'idempotency_key'  => 'attempt_aaaaaaaaaaaaaaaa',
            ],
            $repository->createOrRead($order, 'attempt_aaaaaaaaaaaaaaaa')
        );
    }

    public function testReturnsTheOriginalAttemptForASecondCandidate(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('read_meta_data')->with(true)->times(3);

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')
            ->with($order)
            ->times(3)
            ->andReturn(
                '',
                OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
                OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            );
        $metadata->shouldReceive('getIdempotencyKey')
            ->with($order)
            ->times(3)
            ->andReturn('', 'attempt_aaaaaaaaaaaaaaaa', 'attempt_aaaaaaaaaaaaaaaa');
        $metadata->shouldReceive('persistSmartPixAttempt')
            ->with($order, 'attempt_aaaaaaaaaaaaaaaa')
            ->once();

        $repository = new SmartPixAttemptRepository($metadata);

        $firstAttempt = $repository->createOrRead($order, 'attempt_aaaaaaaaaaaaaaaa');
        $retryAttempt = $repository->createOrRead($order, 'attempt_bbbbbbbbbbbbbbbb');

        $this->assertSame($firstAttempt, $retryAttempt);
    }

    public function testFailsClosedWhenAttemptPersistenceFails(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('read_meta_data')->with(true)->once();

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->once()->andReturn('');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->once()->andReturn('');
        $metadata->shouldReceive('persistSmartPixAttempt')
            ->with($order, 'attempt_aaaaaaaaaaaaaaaa')
            ->once()
            ->andThrow(new RuntimeException('Persistence failed.'));

        $repository = new SmartPixAttemptRepository($metadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Persistence failed.');

        $repository->createOrRead($order, 'attempt_aaaaaaaaaaaaaaaa');
    }

    public function testRejectsAnEmptyCandidateIdempotencyKeyWithoutTouchingMetadata(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('read_meta_data');

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPaymentContract');
        $metadata->shouldNotReceive('getIdempotencyKey');
        $metadata->shouldNotReceive('persistSmartPixAttempt');

        $repository = new SmartPixAttemptRepository($metadata);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('idempotency key must not be empty');

        $repository->createOrRead($order, '');
    }

    /**
     * @dataProvider nonStringMetadataProvider
     */
    public function testRejectsNonStringMetadataOnInitialRead($paymentContract, $idempotencyKey): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('read_meta_data')->with(true)->once();

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->once()->andReturn($paymentContract);
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->once()->andReturn($idempotencyKey);
        $metadata->shouldNotReceive('persistSmartPixAttempt');

        $repository = new SmartPixAttemptRepository($metadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('metadata is incomplete or incompatible');

        $repository->createOrRead($order, 'attempt_aaaaaaaaaaaaaaaa');
    }

    /**
     * @dataProvider nonStringMetadataProvider
     */
    public function testRejectsNonStringMetadataOnPostSaveVerification($persistedContract, $persistedIdempotencyKey): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('read_meta_data')->with(true)->twice();

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')
            ->with($order)
            ->twice()
            ->andReturn('', $persistedContract);
        $metadata->shouldReceive('getIdempotencyKey')
            ->with($order)
            ->twice()
            ->andReturn('', $persistedIdempotencyKey);
        $metadata->shouldReceive('persistSmartPixAttempt')
            ->with($order, 'attempt_aaaaaaaaaaaaaaaa')
            ->once();

        $repository = new SmartPixAttemptRepository($metadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('persistence could not be verified');

        $repository->createOrRead($order, 'attempt_aaaaaaaaaaaaaaaa');
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public function nonStringMetadataProvider(): array
    {
        return [
            'array payment contract' => [['unexpected'], 'attempt_aaaaaaaaaaaaaaaa'],
            'array idempotency key' => [OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, ['unexpected']],
        ];
    }

    /**
     * @dataProvider incompleteAttemptProvider
     */
    public function testRejectsIncompleteOrIncompatibleAttemptMetadata(
        string $paymentContract,
        string $idempotencyKey
    ): void {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('read_meta_data')->with(true)->once();

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->once()->andReturn($paymentContract);
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->once()->andReturn($idempotencyKey);
        $metadata->shouldNotReceive('persistSmartPixAttempt');

        $repository = new SmartPixAttemptRepository($metadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('metadata is incomplete or incompatible');

        $repository->createOrRead($order, 'attempt_aaaaaaaaaaaaaaaa');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function incompleteAttemptProvider(): array
    {
        return [
            'idempotency key only' => ['', 'attempt_aaaaaaaaaaaaaaaa'],
            'payment contract only' => [OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1, ''],
            'incompatible contract' => ['legacy_payments_v1', 'attempt_aaaaaaaaaaaaaaaa'],
        ];
    }

    /**
     * @dataProvider unverifiedPersistenceProvider
     */
    public function testFailsClosedWhenPersistedMetadataCannotBeVerified(
        string $persistedContract,
        string $persistedIdempotencyKey
    ): void {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('read_meta_data')->with(true)->twice();

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')
            ->with($order)
            ->twice()
            ->andReturn('', $persistedContract);
        $metadata->shouldReceive('getIdempotencyKey')
            ->with($order)
            ->twice()
            ->andReturn('', $persistedIdempotencyKey);
        $metadata->shouldReceive('persistSmartPixAttempt')
            ->with($order, 'attempt_aaaaaaaaaaaaaaaa')
            ->once();

        $repository = new SmartPixAttemptRepository($metadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('persistence could not be verified');

        $repository->createOrRead($order, 'attempt_aaaaaaaaaaaaaaaa');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function unverifiedPersistenceProvider(): array
    {
        return [
            'missing contract after reload' => ['', 'attempt_aaaaaaaaaaaaaaaa'],
            'different key after reload' => [
                OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
                'attempt_bbbbbbbbbbbbbbbb',
            ],
            'incompatible contract after reload' => ['legacy_payments_v1', 'attempt_aaaaaaaaaaaaaaaa'],
        ];
    }
}
