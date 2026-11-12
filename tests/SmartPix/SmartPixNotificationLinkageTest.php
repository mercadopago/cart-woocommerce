<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationLinkage;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use WC_Order;
use WP_Mock;

class SmartPixNotificationLinkageTest extends TestCase
{
    private const REJECTED = [
        'linked' => false,
        'category' => 'invalid_linkage',
        'order' => null,
        'attempt_key' => null,
        'order_reference' => null,
    ];

    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testResolveReturnsPersistedContextForALinkedSmartPixOrder(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_data_store')->once()->andReturn($store);
        $order->shouldReceive('read_meta_data')->with(true)->once();
        $order->shouldReceive('get_payment_method')->with('edit')->once()->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->with('edit')->once()->andReturn('BRL');
        $order->shouldNotReceive('save');
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->once()->andReturn('MLB');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->once()
            ->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->once()->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('ORDER_SYNTHETIC_001');
        $metadata->shouldNotReceive('persistSmartPixAttempt');
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 1, 'return' => 'WC-']);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = (new SmartPixNotificationLinkage($metadata, $seller))->resolve('WC-321', 'ORDER_SYNTHETIC_001');

        $this->assertSame([
            'linked' => true,
            'category' => null,
            'order' => $order,
            'attempt_key' => 'SYNTHETIC_KEY',
            'order_reference' => 'ORDER_SYNTHETIC_001',
            'provider_reference_missing' => false,
        ], $result);
    }

    /** @dataProvider providerReferenceProvider */
    public function testResolveChecksTheProviderOrderOnlyAgainstAPersistedOne($stored, ?bool $missing): void
    {
        $overrides = ['provider' => $stored];
        $order = $this->order($overrides);
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = $this->linkage($order, $overrides)->resolve('WC-321', 'ORDER_SYNTHETIC_001', 'ORDSYNTHETIC001');

        if ($missing === null) {
            $this->assertSame(self::REJECTED, $result);
            return;
        }
        $this->assertTrue($result['linked']);
        $this->assertSame($missing, $result['provider_reference_missing']);
    }

    public static function providerReferenceProvider(): array
    {
        return [
            'same persisted provider Order' => ['ORDSYNTHETIC001', false],
            'creation answer without references' => ['', true],
            'never persisted' => [null, true],
            'another persisted provider Order' => ['ORDSYNTHETIC999', null],
        ];
    }

    public function testResolveUsesTheConfiguredStoreIdentificatorAsPrefix(): void
    {
        $order = $this->order();
        $this->expectPrefix('STORE_A-');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = $this->linkage($order)->resolve('STORE_A-321', 'ORDER_SYNTHETIC_001');

        $this->assertTrue($result['linked']);
        $this->assertSame($order, $result['order']);
        $this->assertSame('SYNTHETIC_KEY', $result['attempt_key']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
    }

    /** @dataProvider invalidAttemptReferenceProvider */
    public function testResolveRejectsAnInvalidAttemptReferenceBeforeLoadingTheOrder(string $prefix, string $attemptReference): void
    {
        $this->expectPrefix($prefix);
        $this->forbidLoadAndLogging();
        $seller = Mockery::mock(Seller::class);
        $seller->shouldNotReceive('getSiteId');

        $result = (new SmartPixNotificationLinkage($this->forbiddenMetadata(), $seller))
            ->resolve($attemptReference, 'ORDER_SYNTHETIC_001');

        $this->assertSame(self::REJECTED, $result);
    }

    public function invalidAttemptReferenceProvider(): array
    {
        return [
            'empty reference' => ['WC-', ''],
            'missing prefix' => ['WC-', '321'],
            'wrong prefix' => ['WC-', 'XX-321'],
            'prefix with different case' => ['WC-', 'wc-321'],
            'default prefix with custom store identificator' => ['STORE_A-', 'WC-321'],
            'prefix only' => ['WC-', 'WC-'],
            'non-numeric id' => ['WC-', 'WC-abc'],
            'mixed id' => ['WC-', 'WC-321abc'],
            'zero id' => ['WC-', 'WC-0'],
            'leading-zero id' => ['WC-', 'WC-0321'],
            'negative id' => ['WC-', 'WC--321'],
            'signed id' => ['WC-', 'WC-+321'],
            'decimal id' => ['WC-', 'WC-321.0'],
            'exponent id' => ['WC-', 'WC-3e2'],
            'whitespace id' => ['WC-', 'WC- 321'],
            'trailing newline' => ['WC-', "WC-321\n"],
            'overflowing 19-digit id' => ['WC-', 'WC-9223372036854775808'],
            'overflowing 20-digit id' => ['WC-', 'WC-18446744073709551616'],
        ];
    }

    /**
     * @dataProvider invalidStoreIdentificatorProvider
     * @param mixed $prefix
     */
    public function testResolveRejectsAnUnusableStoreIdentificator($prefix): void
    {
        $this->expectPrefix($prefix);
        $this->forbidLoadAndLogging();

        $result = (new SmartPixNotificationLinkage($this->forbiddenMetadata(), Mockery::mock(Seller::class)))
            ->resolve('WC-321', 'ORDER_SYNTHETIC_001');

        $this->assertSame(self::REJECTED, $result);
    }

    public function invalidStoreIdentificatorProvider(): array
    {
        return [
            'empty string' => [''],
            'false' => [false],
            'null' => [null],
            'integer' => [123],
            'array' => [['WC-']],
        ];
    }

    /** @dataProvider invalidOrderReferenceProvider */
    public function testResolveRejectsAnInvalidOrdersReferenceBeforeLoadingTheOrder(string $orderReference): void
    {
        $this->expectPrefix('WC-');
        $this->forbidLoadAndLogging();

        $result = (new SmartPixNotificationLinkage($this->forbiddenMetadata(), Mockery::mock(Seller::class)))
            ->resolve('WC-321', $orderReference);

        $this->assertSame(self::REJECTED, $result);
    }

    public function invalidOrderReferenceProvider(): array
    {
        return [
            'empty' => [''],
            'too short' => ['AB'],
            'too long' => [str_repeat('A', 129)],
            'path traversal' => ['../ORDER_SYNTHETIC_001'],
            'external URL' => ['https://example.test/order'],
            'leading whitespace' => [' ORDER_SYNTHETIC_001'],
            'trailing whitespace' => ['ORDER_SYNTHETIC_001 '],
            'trailing newline' => ["ORDER_SYNTHETIC_001\n"],
            'leading separator' => ['_ORDER_SYNTHETIC_001'],
            'query string' => ['ORDER_SYNTHETIC_001?x=1'],
        ];
    }

    public function testResolveAcceptsAnOrdersReferenceAtTheMaximumLength(): void
    {
        $reference = str_repeat('A', 128);
        $order = $this->order();
        $this->expectPrefix('WC-');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = $this->linkage($order, ['reference' => $reference])->resolve('WC-321', $reference);

        $this->assertTrue($result['linked']);
        $this->assertSame($reference, $result['order_reference']);
    }

    /**
     * @dataProvider unloadableOrderProvider
     * @param mixed $loaded
     */
    public function testResolveRejectsAMissingOrMismatchedOrder($loaded): void
    {
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $loaded]);

        $result = (new SmartPixNotificationLinkage($this->forbiddenMetadata(), Mockery::mock(Seller::class)))
            ->resolve('WC-321', 'ORDER_SYNTHETIC_001');

        $this->assertSame(self::REJECTED, $result);
    }

    public function unloadableOrderProvider(): array
    {
        $mismatched = Mockery::mock(WC_Order::class);
        $mismatched->shouldReceive('get_id')->andReturn(999);
        $mismatched->shouldNotReceive('get_data_store');
        $mismatched->shouldNotReceive('read_meta_data');
        $mismatched->shouldNotReceive('save');

        return [
            'false' => [false],
            'null' => [null],
            'non-order object' => [new stdClass()],
            'id mismatch' => [$mismatched],
        ];
    }

    /**
     * @dataProvider incompatibleContextProvider
     * @param array<string, mixed> $overrides
     */
    public function testResolveRejectsAnIncompatibleOrderContext(array $overrides): void
    {
        $order = $this->order($overrides);
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = $this->linkage($order, $overrides)->resolve('WC-321', 'ORDER_SYNTHETIC_001');

        $this->assertSame(self::REJECTED, $result);
    }

    public function testResolveReturnsASupersededAttemptOnlyForItsOwnReference(): void
    {
        $overrides = ['contract' => '', 'superseded' => ['attempt_key' => 'SYNTHETIC_KEY', 'attempt_reference' => 'WC-321']];
        $order = $this->order($overrides);
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = $this->linkage($order, $overrides)->resolve('WC-321', 'ORDER_SYNTHETIC_001');

        $this->assertSame([
            'linked' => false,
            'category' => SmartPixNotificationLinkage::CATEGORY_SUPERSEDED,
            'order' => $order,
            'attempt_key' => 'SYNTHETIC_KEY',
            'order_reference' => null,
        ], $result);
    }

    /** @dataProvider foreignSupersededProvider */
    public function testResolveRejectsASupersededAttemptOutsideItsContext(array $overrides): void
    {
        $order = $this->order($overrides);
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $this->assertSame(self::REJECTED, $this->linkage($order, $overrides)->resolve('WC-321', 'ORDER_SYNTHETIC_001'));
    }

    public function foreignSupersededProvider(): array
    {
        $superseded = ['attempt_key' => 'SYNTHETIC_KEY', 'attempt_reference' => 'STORE-321'];
        return [
            'another attempt reference' => [['contract' => '', 'superseded' => $superseded]],
            'non-BRL currency' => [['contract' => '', 'currency' => 'USD',
                'superseded' => ['attempt_key' => 'SYNTHETIC_KEY', 'attempt_reference' => 'WC-321']]],
        ];
    }

    public function incompatibleContextProvider(): array
    {
        return [
            'non-Pix payment method' => [['method' => 'woo-mercado-pago-custom']],
            'empty payment method' => [['method' => '']],
            'non-BRL currency' => [['currency' => 'USD']],
            'non-MLB seller site' => [['site' => 'MLA']],
            'Payments contract' => [['contract' => 'payments_v1']],
            'missing contract' => [['contract' => null]],
            'empty contract' => [['contract' => '']],
            'empty attempt key' => [['key' => '']],
            'missing attempt key' => [['key' => null]],
            'non-string attempt key' => [['key' => ['SYNTHETIC_KEY']]],
            'integer attempt key' => [['key' => 123]],
            'non-string persisted reference' => [['reference' => ['ORDER_SYNTHETIC_001']]],
            'malformed persisted reference' => [['reference' => '../ORDER_SYNTHETIC_001']],
            'different persisted reference' => [['reference' => 'ORDER_SYNTHETIC_002']],
            'case-only different persisted reference' => [['reference' => 'order_synthetic_001']],
            'whitespace-padded persisted reference' => [['reference' => 'ORDER_SYNTHETIC_001 ']],
        ];
    }

    /**
     * @testWith [null]
     *           [""]
     */
    public function testResolveMarksAnUnpaidAttemptWithoutPersistedReferenceAsPendingLinkage(?string $reference): void
    {
        $order = $this->order();
        $order->shouldReceive('is_paid')->once()->andReturn(false);
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = $this->linkage($order, ['reference' => $reference])->resolve('WC-321', 'PPORDSYNTHETIC001');

        $this->assertSame([
            'linked' => false,
            'category' => 'pending_linkage',
            'order' => $order,
            'attempt_key' => 'SYNTHETIC_KEY',
            'order_reference' => null,
        ], $result);
    }

    public function testResolveRejectsAPaidAttemptWithoutPersistedReference(): void
    {
        $order = $this->order();
        $order->shouldReceive('is_paid')->once()->andReturn(true);
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = $this->linkage($order, ['reference' => ''])->resolve('WC-321', 'PPORDSYNTHETIC001');

        $this->assertSame(self::REJECTED, $result);
    }

    public function testResolveRejectsAProvidedReferenceThatDiffersOnlyByCase(): void
    {
        $order = $this->order();
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $result = $this->linkage($order)->resolve('WC-321', 'order_synthetic_001');

        $this->assertSame(self::REJECTED, $result);
    }

    public function testResolvePropagatesOrderLookupFailures(): void
    {
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1])
            ->andThrow(new RuntimeException('synthetic lookup failure'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('synthetic lookup failure');

        (new SmartPixNotificationLinkage($this->forbiddenMetadata(), Mockery::mock(Seller::class)))
            ->resolve('WC-321', 'ORDER_SYNTHETIC_001');
    }

    public function testResolvePropagatesDataStoreReadFailures(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once()->andThrow(new RuntimeException('synthetic read failure'));
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_data_store')->once()->andReturn($store);
        $order->shouldNotReceive('read_meta_data');
        $order->shouldNotReceive('save');
        $this->expectPrefix('WC-');
        $this->forbidLogging();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('synthetic read failure');

        (new SmartPixNotificationLinkage($this->forbiddenMetadata(), Mockery::mock(Seller::class)))
            ->resolve('WC-321', 'ORDER_SYNTHETIC_001');
    }

    public function testCompleteReferencesRefusesAReferenceThatConflictsWithThePersistedOnes(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('read_meta_data');
        $order->shouldNotReceive('save');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('persistSmartPixReferences')->once()->with($order, [
            'order_reference' => 'ORDER_SYNTHETIC_001',
            'payment_reference' => 'PPPAYSYNTHETIC001',
            'provider_order_reference' => 'ORDSYNTHETICPROVIDER001',
        ])->andThrow(new \InvalidArgumentException('Conflicting Smart Pix reference.'));
        $metadata->shouldNotReceive('getMpOrderId');
        $this->forbidMetadataWrites($metadata);
        $response = [
            'order_reference' => 'ORDER_SYNTHETIC_001',
            'observed' => ['payment_id' => 'PPPAYSYNTHETIC001', 'payment_reference' => 'ORDSYNTHETICPROVIDER001'],
        ];

        $completed = (new SmartPixNotificationLinkage($metadata, Mockery::mock(Seller::class)))
            ->completeReferences($order, 'ORDER_SYNTHETIC_001', 'ORDSYNTHETICPROVIDER001', $response);

        $this->assertFalse($completed);
    }

    /** @param mixed $prefix */
    private function expectPrefix($prefix): void
    {
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 1, 'return' => $prefix]);
    }

    private function forbidLogging(): void
    {
        WP_Mock::userFunction('wc_get_logger', ['times' => 0]);
    }

    private function forbidLoadAndLogging(): void
    {
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $this->forbidLogging();
    }

    /** Metadata that must never be read or written. */
    private function forbiddenMetadata(): OrderMetadata
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPaymentContract');
        $metadata->shouldNotReceive('getIdempotencyKey');
        $metadata->shouldNotReceive('getPpOrderId');
        $this->forbidMetadataWrites($metadata);
        return $metadata;
    }

    /** @param \Mockery\MockInterface $metadata */
    private function forbidMetadataWrites($metadata): void
    {
        $metadata->shouldNotReceive('persistSmartPixAttempt');
        $metadata->shouldNotReceive('setPaymentContract');
        $metadata->shouldNotReceive('setPpOrderId');
        $metadata->shouldNotReceive('setIdempotencyKey');
    }

    /**
     * Fully refreshed linked Smart Pix order whose facts can be overridden per scenario.
     *
     * @param array<string, mixed> $overrides
     * @return WC_Order&\Mockery\MockInterface
     */
    private function order(array $overrides = [])
    {
        $order = Mockery::mock(WC_Order::class);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_data_store')->once()->andReturn($store);
        $order->shouldReceive('read_meta_data')->with(true)->once();
        $order->shouldReceive('get_payment_method')->with('edit')
            ->andReturn(array_key_exists('method', $overrides) ? $overrides['method'] : PixGateway::ID);
        $order->shouldReceive('get_currency')->with('edit')
            ->andReturn(array_key_exists('currency', $overrides) ? $overrides['currency'] : 'BRL');
        $order->shouldNotReceive('save');
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $order->shouldNotReceive('add_order_note');
        $order->shouldNotReceive('update_meta_data');
        return $order;
    }

    public function testTheReviewLeaseHolderRecordsTheSupersededReviewOnceOnFreshMetadata(): void
    {
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('is_paid')->andReturn(false);
        $order->shouldReceive('read_meta_data')->once()->with(true)->ordered();
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('isSupersededSmartPixSettled')->once()->with($order, 'ORDER_SYNTHETIC_001')
            ->ordered()->andReturn(false);
        $order->shouldReceive('add_order_note')->once()->with(Mockery::pattern('/ORDER_SYNTHETIC_001/'))->ordered();
        $metadata->shouldReceive('settleSupersededSmartPixReview')->once()->with($order, 'ORDER_SYNTHETIC_001')
            ->ordered();
        $metadata->shouldNotReceive('setPpOrderReconcileState');
        $lease = ['name' => 'mp_smart_pix_superseded_review_lock_321', 'value' => 'LEASE'];
        $lock = Mockery::mock(SmartPixStatusLock::class);
        $lock->shouldReceive('acquire')->once()->with(321)->andReturn($lease);
        $lock->shouldReceive('release')->once()->with($lease)->andReturn(true);

        $linkage = new SmartPixNotificationLinkage($metadata, Mockery::mock(Seller::class), $lock);

        $this->assertTrue($linkage->review($order, 'ORDER_SYNTHETIC_001'));
    }

    public function testAReviewInterruptedAfterTheNoteIsRecordedAgainOnTheNextDelivery(): void
    {
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('is_paid')->andReturn(true);
        $order->shouldReceive('add_order_note')->twice()->with(Mockery::pattern('/ORDER_SYNTHETIC_001/'));
        $metadata = Mockery::mock(OrderMetadata::class);
        // The marker is only written by the settle call, so the failed first save leaves it unset.
        $metadata->shouldReceive('isSupersededSmartPixSettled')->twice()->with($order, 'ORDER_SYNTHETIC_001')
            ->andReturn(false);
        $metadata->shouldReceive('settleSupersededSmartPixReview')->twice()->with($order, 'ORDER_SYNTHETIC_001')
            ->andReturnUsing(static function (): void {
                static $calls = 0;
                if (++$calls === 1) {
                    throw new RuntimeException('SYNTHETIC_INTERRUPTED_SAVE');
                }
            });

        $linkage = new SmartPixNotificationLinkage($metadata, Mockery::mock(Seller::class));

        try {
            $linkage->review($order, 'ORDER_SYNTHETIC_001');
            $this->fail('The interrupted save must surface to the receiver.');
        } catch (RuntimeException $interrupted) {
            $this->assertSame('SYNTHETIC_INTERRUPTED_SAVE', $interrupted->getMessage());
        }
        $this->assertTrue($linkage->review($order, 'ORDER_SYNTHETIC_001'));
    }

    public function testAFailedNoteLeavesTheReviewUnrecordedForTheNextDelivery(): void
    {
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('is_paid')->andReturn(false);
        $order->shouldReceive('add_order_note')->once()->andThrow(new RuntimeException('SYNTHETIC_NOTE_FAILURE'));
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('isSupersededSmartPixSettled')->once()->andReturn(false);
        $metadata->shouldNotReceive('settleSupersededSmartPixReview');

        $linkage = new SmartPixNotificationLinkage($metadata, Mockery::mock(Seller::class));

        $this->expectException(RuntimeException::class);
        $linkage->review($order, 'ORDER_SYNTHETIC_001');
    }

    public function testAConcurrentSupersededReviewWithoutTheLeaseRecordsNothing(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldNotReceive('read_meta_data', 'add_order_note');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('isSupersededSmartPixSettled', 'settleSupersededSmartPixReview', 'setPpOrderReconcileState');
        $lock = Mockery::mock(SmartPixStatusLock::class);
        $lock->shouldReceive('acquire')->once()->with(321)->andReturn(null);
        $lock->shouldNotReceive('release');

        $linkage = new SmartPixNotificationLinkage($metadata, Mockery::mock(Seller::class), $lock);

        $this->assertFalse($linkage->review($order, 'ORDER_SYNTHETIC_001'));
    }

    public function testTheReviewLeaseIsReleasedWhenRecordingTheReviewFails(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('read_meta_data')->once()->with(true);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('isSupersededSmartPixSettled')->once()->andThrow(new RuntimeException('SYNTHETIC_STORAGE'));
        $lease = ['name' => 'mp_smart_pix_superseded_review_lock_321', 'value' => 'LEASE'];
        $lock = Mockery::mock(SmartPixStatusLock::class);
        $lock->shouldReceive('acquire')->once()->with(321)->andReturn($lease);
        $lock->shouldReceive('release')->once()->with($lease)->andReturn(true);

        $linkage = new SmartPixNotificationLinkage($metadata, Mockery::mock(Seller::class), $lock);

        $this->expectException(RuntimeException::class);
        $linkage->review($order, 'ORDER_SYNTHETIC_001');
    }

    /** @param array<string, mixed> $overrides */
    private function linkage(WC_Order $order, array $overrides = []): SmartPixNotificationLinkage
    {
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')
            ->andReturn(array_key_exists('site', $overrides) ? $overrides['site'] : 'MLB');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn(
            array_key_exists('contract', $overrides) ? $overrides['contract'] : OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
        );
        $metadata->shouldReceive('getIdempotencyKey')->with($order)
            ->andReturn(array_key_exists('key', $overrides) ? $overrides['key'] : 'SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)
            ->andReturn(array_key_exists('reference', $overrides) ? $overrides['reference'] : 'ORDER_SYNTHETIC_001');
        $metadata->shouldReceive('getMpOrderId')->with($order)
            ->andReturn(array_key_exists('provider', $overrides) ? $overrides['provider'] : '');
        $metadata->shouldReceive('getSupersededSmartPixAttempt')->with($order)
            ->andReturn($overrides['superseded'] ?? null);
        $this->forbidMetadataWrites($metadata);
        return new SmartPixNotificationLinkage($metadata, $seller);
    }
}
