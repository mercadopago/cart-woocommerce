<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixStateApplicatorTest extends TestCase
{
    use AssertsApplicationDiagnostics;

    private $logger;

    public function testNativeStatusExceptionKeepsItsOriginalDiagnosticAndNeverClaimsPaymentRejection(): void
    {
        $events = [];
        $response = $this->response('rejected');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $pending->shouldReceive('update_status')->once()->with('failed')
            ->andThrow(new \RuntimeException('Original native persistence failure; token=PRIVATE_TOKEN; buyer@example.test'));
        $pending->shouldNotReceive('payment_complete');
        $persisted = $this->orderSnapshot($metadata, $response, 'pending', false);
        $this->forbidEffects($persisted);
        $this->logger->shouldReceive('warning')->once();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership(), new SmartPixTelemetry($this->recordingSink($events)));
        $result = $applicator->apply(321, 'attempt_fixture_query_success_001', $response);
        $this->assertSame('uncertain', $result['outcome']);
        $message = $events[0][3];
        $this->assertStringContainsString('Original native persistence failure', $message);
        $this->assertStringNotContainsString('PRIVATE_TOKEN', $message);
        $this->assertStringNotContainsString('buyer@example.test', $message);
        $this->assertApplicationDiagnostic($events, false);
        $this->assertSame([SmartPixMetricSchema::EFFECT], array_column($events, 0));
    }

    public function testSuccessfulTelemetryIsEmittedAfterVerifiedOwnershipCompletion(): void
    {
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'processing', true);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $owner = str_repeat('a', 64);
        $ownership->shouldReceive('acquire')->once()->globally()->ordered()->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $ownership->shouldReceive('start')->once()->globally()->ordered()->andReturn(true);
        $pending->shouldReceive('payment_complete')->once()->globally()->ordered()->andReturn(true);
        $ownership->shouldReceive('complete')->once()->globally()->ordered()->andReturn(true);
        $ownership->shouldNotReceive('markUncertain');
        $ownership->shouldNotReceive('releaseReserved');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $context = ['operation' => 'apply', 'source' => 'sync_response', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink->shouldReceive('count')->once()->globally()->ordered()->with(
            SmartPixMetricSchema::EFFECT,
            'applied',
            $context + ['reason_class' => 'native_verification', 'native_status' => 'processing']
        );
        $sink->shouldNotReceive('distribution');
        $observed = [];
        $telemetry = new SmartPixTelemetry($sink, null, null, static function ($id, $source) use (&$observed): void {
            $observed[] = [$id, $source];
        });
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, $telemetry);
        $this->assertSame(
            ['outcome' => 'applied', 'reason' => 'native_paid_state_verified', 'status' => 'processing'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'sync_response', 'classic')
        );
        $this->assertSame([[321, 'sync_response']], $observed);
    }

    public function testApprovalWithAStorePaidStatusIsVerifiedAndReportedWithoutTheStatusName(): void
    {
        // Given a store whose payment_complete() status is its own paid status.
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'paid-by-store', true);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->andReturn(['outcome' => 'acquired', 'owner' => str_repeat('a', 64)]);
        $ownership->shouldReceive('start')->once()->andReturn(true);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $ownership->shouldReceive('complete')->once()->andReturn(true);
        $ownership->shouldNotReceive('markUncertain');
        $ownership->shouldNotReceive('releaseReserved');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(
            SmartPixMetricSchema::EFFECT,
            'applied',
            ['operation' => 'apply', 'source' => 'sync_response', 'surface' => 'classic', 'payment_method' => 'smart_pix',
                'site_id' => 'MLB', 'reason_class' => 'native_verification', 'native_status' => 'paid_custom']
        );
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink, null, null, static function (): void {
        });
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, $telemetry);

        // When the approval is applied, then it is a verified success, not an uncertain effect.
        $this->assertSame(
            ['outcome' => 'applied', 'reason' => 'native_paid_state_verified', 'status' => 'paid-by-store'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'sync_response', 'classic')
        );
    }

    public function testApprovalReplayOnAStorePaidStatusIsNoChange(): void
    {
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $paid = $this->orderSnapshot($metadata, $response, 'paid-by-store', true);
        $this->forbidEffects($paid);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $paid]);
        $result = (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership()))
            ->apply(321, 'attempt_fixture_query_success_001', $response);
        $this->assertSame(['outcome' => 'no_change', 'reason' => 'approval_already_applied', 'status' => 'paid-by-store'], $result);
    }

    public function testTelemetryFailurePreservesCompletionAndCompletedReplay(): void
    {
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'processing', true);
        $replayed = $this->orderSnapshot($metadata, $response, 'processing', true);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $this->forbidEffects($persisted);
        $this->forbidEffects($replayed);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 3, 'return_in_order' => [$pending, $persisted, $replayed]]);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->twice()->andReturn(['outcome' => 'acquired', 'owner' => str_repeat('a', 64)], ['outcome' => 'completed', 'owner' => null]);
        $ownership->shouldReceive('start')->once()->andReturn(true);
        $journalCompleted = false;
        $ownership->shouldReceive('complete')->once()->andReturnUsing(static function () use (&$journalCompleted): bool {
            $journalCompleted = true;
            return true;
        });
        $ownership->shouldNotReceive('markUncertain');
        $ownership->shouldNotReceive('releaseReserved');
        $events = [];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->times(2)->andReturnUsing(function ($name, $value, $context) use (&$events, &$journalCompleted): void {
            $this->assertTrue($journalCompleted);
            $this->assertStringNotContainsString('attempt_fixture', json_encode($context));
            $events[] = [$name, $value];
            throw new \Error('SYNTHETIC_PRIVATE_TRANSPORT');
        });
        $sink->shouldNotReceive('distribution');
        $notes = [];
        $recordNotes = function ($native, string $state) use (&$notes, $persisted): void {
            $this->assertSame($persisted, $native);
            $notes[] = $state;
        };
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, new SmartPixTelemetry($sink), null, $recordNotes);
        $this->assertSame('applied', $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'sync_response', 'classic')['outcome']);
        $this->assertSame('no_change', $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'pending_query')['outcome']);
        $this->assertSame(['approved'], $notes);
        $this->assertSame([
            ['smart_pix_server_effect_result', 'applied'],
            ['smart_pix_server_effect_result', 'no_change'],
        ], $events);
    }

    public function testANoteFailureAfterAVerifiedApprovalKeepsItApplied(): void
    {
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'processing', true);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->andReturn(['outcome' => 'acquired', 'owner' => str_repeat('a', 64)]);
        $ownership->shouldReceive('start')->once()->andReturn(true);
        $ownership->shouldReceive('complete')->once()->andReturn(true);
        $ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
            'source' => 'mercadopago-smart-pix', 'reason' => 'status_note_failed',
        ]);
        $recordNotes = static function (): void {
            throw new \RuntimeException('SYNTHETIC_MAILER_FAILURE');
        };
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, null, null, $recordNotes);

        $result = $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'notification', 'server');

        $this->assertSame('applied', $result['outcome']);
        $this->assertSame('native_paid_state_verified', $result['reason']);
    }

    public function testBusyOwnershipIsObservedWithoutReadingOrExecutingNativeOrder(): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->andReturn(['outcome' => 'busy', 'owner' => null]);
        foreach (['start', 'complete', 'markUncertain', 'releaseReserved'] as $method) {
            $ownership->shouldNotReceive($method);
        }
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $context = ['operation' => 'apply', 'source' => 'notification', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(
            'smart_pix_server_effect_result',
            'busy',
            $context + ['reason_class' => 'ownership', 'native_status' => 'unknown']
        );
        $sink->shouldNotReceive('distribution');
        $applicator = new SmartPixStateApplicator(Mockery::mock(OrderMetadata::class), new SmartPixStateReducer(), Mockery::mock(Seller::class), $ownership, new SmartPixTelemetry($sink));
        $this->assertSame(
            ['outcome' => 'busy', 'reason' => 'native_effect_in_progress', 'status' => null],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $this->response('approved'), 'notification')
        );
    }

    public function testUncertainNativeExecutionIsRecordedOnlyAfterUncertainJournalWrite(): void
    {
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'pending', false);
        $pending->shouldReceive('payment_complete')->once()->globally()->ordered()->andReturn(false);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->andReturn(['outcome' => 'acquired', 'owner' => str_repeat('a', 64)]);
        $ownership->shouldReceive('start')->once()->andReturn(true);
        $ownership->shouldReceive('markUncertain')->once()->globally()->ordered()->andReturn(true);
        $ownership->shouldNotReceive('complete');
        $ownership->shouldNotReceive('releaseReserved');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->withArgs(static function ($name, $value, $context): bool {
            return $name === 'smart_pix_server_effect_result' && $value === 'uncertain' && $context['reason_class'] === 'native_verification';
        })->once()->globally()->ordered();
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, new SmartPixTelemetry($sink));
        $this->assertSame('uncertain', $applicator->apply(321, 'attempt_fixture_query_success_001', $response)['outcome']);
    }

    public function testApprovalCompletesPaymentAndVerifiesAuthoritativeOrder(): void
    {
        $response = (new SmartPixOrderNormalizer())->normalize([
            'id' => 'ORDER_SYNTHETIC_001', 'external_reference' => 'WC-321', 'type' => 'online',
            'site_id' => 'MLB', 'currency' => 'BRL', 'total_amount' => '125.5', 'total_paid_amount' => '125.5',
            'status' => 'processed', 'status_detail' => 'accredited',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC_001', 'amount' => '125.5', 'paid_amount' => '125.5',
                'status' => 'processed', 'status_detail' => 'accredited',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]],
        ], 'ORDER_SYNTHETIC_001', null, 'WC-321');
        $this->assertSame('terminal_success', $response['state']);
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'processing', true);
        $replayed = $this->orderSnapshot($metadata, $response, 'processing', true);
        $pending->shouldNotReceive('update_status');
        $pending->shouldNotReceive('save');
        $pending->shouldNotReceive('add_order_note');
        $this->forbidEffects($persisted);
        $this->forbidEffects($replayed);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 3, 'return_in_order' => [$pending, $persisted, $replayed]]);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $owner = str_repeat('a', 64);
        $ownership->shouldReceive('acquire')->with(321, 'attempt_fixture_query_success_001', 'complete_payment')
            ->once()->globally()->ordered()->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $ownership->shouldReceive('start')->with(321, 'attempt_fixture_query_success_001', 'complete_payment', $owner)
            ->once()->globally()->ordered()->andReturn(true);
        $pending->shouldReceive('payment_complete')->withNoArgs()->once()->globally()->ordered()->andReturn(true);
        $ownership->shouldReceive('complete')->with(321, 'attempt_fixture_query_success_001', 'complete_payment', $owner)
            ->once()->globally()->ordered()->andReturn(true);
        $ownership->shouldReceive('acquire')->with(321, 'attempt_fixture_query_success_001', 'complete_payment')
            ->once()->globally()->ordered()->andReturn(['outcome' => 'completed', 'owner' => null]);
        $ownership->shouldNotReceive('releaseReserved');
        $ownership->shouldNotReceive('markUncertain');
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership);
        $this->assertSame(
            ['outcome' => 'applied', 'reason' => 'native_paid_state_verified', 'status' => 'processing'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
        $this->assertSame(
            ['outcome' => 'no_change', 'reason' => 'approval_already_applied', 'status' => 'processing'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public function testRejectionAppliesNativeFailedStatusAndVerifiesPersistedFacts(): void
    {
        $response = $this->response('rejected');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'failed', false);
        $pending->shouldReceive('update_status')->with('failed')->once()->andReturn(true);
        foreach ([$pending, $persisted] as $order) {
            $order->shouldNotReceive('payment_complete');
            $order->shouldNotReceive('save');
            $order->shouldNotReceive('add_order_note');
        }
        $persisted->shouldNotReceive('update_status');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $result = (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership()))
            ->apply(321, 'attempt_fixture_query_success_001', $response);
        $this->assertSame(['outcome' => 'applied', 'reason' => 'native_unpaid_status_verified', 'status' => 'failed'], $result);
    }

    public function testQueriedFailedOrderMarksStoreOrderFailedOnceThroughOwnership(): void
    {
        $response = (new SmartPixOrderNormalizer())->normalize([
            'id' => 'synthetic_terminal_order', 'type' => 'online', 'external_reference' => 'WC-321',
            'site_id' => 'MLB', 'currency' => 'BRL', 'total_amount' => '125.50', 'total_paid_amount' => '0',
            'status' => 'failed', 'status_detail' => 'high_risk',
            'payments' => [[
                'id' => 'synthetic_terminal_payment', 'status' => 'failed', 'status_detail' => 'high_risk',
                'amount' => '125.50', 'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]],
        ], 'synthetic_terminal_order', null, 'WC-321');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'failed', false);
        $this->forbidManualEffects($pending);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $owner = str_repeat('b', 64);
        $key = 'attempt_fixture_query_success_001';
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->with(321, $key, 'mark_failed')->once()->globally()->ordered()
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $ownership->shouldReceive('start')->with(321, $key, 'mark_failed', $owner)->once()->globally()->ordered()->andReturn(true);
        $pending->shouldReceive('update_status')->with('failed')->once()->globally()->ordered()->andReturn(true);
        $ownership->shouldReceive('complete')->with(321, $key, 'mark_failed', $owner)->once()->globally()->ordered()->andReturn(true);
        $ownership->shouldNotReceive('markUncertain');
        $ownership->shouldNotReceive('releaseReserved');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $context = ['operation' => 'apply', 'source' => 'notification', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink->shouldReceive('count')->once()->globally()->ordered()->with(
            SmartPixMetricSchema::EFFECT,
            'applied',
            $context + ['reason_class' => 'native_verification', 'native_status' => 'failed']
        );
        // The shared terminal observer owns the rejection count.

        $result = (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, new SmartPixTelemetry($sink)))
            ->apply(321, $key, $response, 'notification', 'server');

        $this->assertSame(['outcome' => 'applied', 'reason' => 'native_unpaid_status_verified', 'status' => 'failed'], $result);
    }

    public function testRejectionAcceptsAnAuthoritativeTotalWithExtraFractionalZeros(): void
    {
        $response = $this->response('rejected');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'failed', false);
        $pending->shouldReceive('get_total')->with('edit')->andReturn('125.5000');
        $persisted->shouldReceive('get_total')->with('edit')->andReturn('125.5000');
        $pending->shouldReceive('update_status')->with('failed')->once()->andReturn(true);
        foreach ([$pending, $persisted] as $order) {
            $order->shouldNotReceive('payment_complete');
            $order->shouldNotReceive('save');
            $order->shouldNotReceive('add_order_note');
        }
        $persisted->shouldNotReceive('update_status');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $result = (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership()))
            ->apply(321, 'attempt_fixture_query_success_001', $response);
        $this->assertSame(['outcome' => 'applied', 'reason' => 'native_unpaid_status_verified', 'status' => 'failed'], $result);
    }

    public function testCancellationFromOnHoldUsesOnlyNativeStatusChange(): void
    {
        $response = $this->response('cancelled');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'on-hold', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'cancelled', false);
        $pending->shouldReceive('update_status')->with('cancelled')->once()->andReturn(true);
        $this->forbidManualEffects($pending);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $this->assertSame(
            ['outcome' => 'applied', 'reason' => 'native_unpaid_status_verified', 'status' => 'cancelled'],
            (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership()))
                ->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    /** @dataProvider monetaryConflictProvider */
    public function testFinancialConflictsRemainObservableWithoutNativeEffects(string $state, string $field, string $value, string $reason): void
    {
        [$applicator, $order, $response] = $this->context($state);
        $response['payments'][0]['amount'][$field] = $value;
        $this->forbidEffects($order);
        $this->assertSame(
            ['outcome' => 'conflict', 'reason' => $reason, 'status' => 'pending'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    /** @dataProvider existingRefundProvider */
    public function testExistingNativeRefundBlocksTerminalEffects(string $state): void
    {
        [$applicator, $order, $response] = $this->context($state);
        $order->shouldReceive('get_refunds')->andReturn([new \stdClass()]);
        $this->forbidEffects($order);
        $this->assertSame(
            ['outcome' => 'conflict', 'reason' => 'refund_context_conflict', 'status' => 'pending'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public static function existingRefundProvider(): array
    {
        return [['approved'], ['rejected'], ['cancelled']];
    }

    /** @dataProvider existingRefundProvider */
    public function testRefundAppearingAfterNativeEffectIsUncertainWithoutRetry(string $state): void
    {
        $response = $this->response($state);
        $approval = $state === 'approved';
        $target = $approval ? 'processing' : ($state === 'rejected' ? 'failed' : 'cancelled');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        if ($approval) {
            $pending->shouldReceive('payment_complete')->withNoArgs()->once()->andReturn(true);
            $pending->shouldNotReceive('update_status');
        } else {
            $pending->shouldReceive('update_status')->with($target)->once()->andReturn(true);
            $pending->shouldNotReceive('payment_complete');
        }
        $pending->shouldNotReceive('save');
        $pending->shouldNotReceive('add_order_note');
        // Every other persisted fact matches the expected native result.
        $persisted = $this->orderSnapshot($metadata, $response, $target, $approval);
        $persisted->shouldReceive('get_refunds')->andReturn([new \stdClass()]);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);

        $this->assertSame(
            [
                'outcome' => 'uncertain',
                'reason' => $approval ? 'native_completion_not_verified' : 'native_status_not_verified',
                'status' => null,
            ],
            (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership()))
                ->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public static function monetaryConflictProvider(): array
    {
        $cases = [];
        foreach (['approved', 'rejected', 'cancelled'] as $state) {
            $cases[] = [$state, 'value', '125.49', 'amount_mismatch'];
            $cases[] = [$state, 'currency', 'USD', 'currency_mismatch'];
        }
        return $cases;
    }

    /** @dataProvider incompatibleTotalProvider */
    public function testIncompatibleAuthoritativeTotalsCannotExecuteEffects($total): void
    {
        [$applicator, $order, $response] = $this->context('approved');
        $order->shouldReceive('get_total')->with('edit')->andReturn($total);
        $this->forbidEffects($order);
        $this->assertSame(
            ['outcome' => 'unsupported', 'reason' => 'unsupported_order_total', 'status' => null],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public static function incompatibleTotalProvider(): array
    {
        return [['125.5001'], ['1.255e2'], [125.50], ['0.0000'], [str_repeat('1', 65)]];
    }

    /** @dataProvider invalidCurrencyProvider */
    public function testMalformedCurrenciesAreRejectedBeforeLoadingTheOrder($currency): void
    {
        $response = $this->response('approved');
        $response['payments'][0]['amount']['currency'] = $currency;
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $applicator = new SmartPixStateApplicator(Mockery::mock(OrderMetadata::class), new SmartPixStateReducer(), Mockery::mock(Seller::class), $this->ownership());
        $this->assertSame(
            ['outcome' => 'unsupported', 'reason' => 'invalid_terminal_context', 'status' => null],
            $applicator->apply(321, 'synthetic_attempt', $response)
        );
    }

    public static function invalidCurrencyProvider(): array
    {
        return [['usd'], ['US'], ['USDD'], [null], [[]]];
    }

    /** @dataProvider preservedStateProvider */
    public function testReplayAndConflictingStatesNeverExecuteNativeEffects(string $state, string $status, bool $paid, string $outcome, string $reason): void
    {
        [$applicator, $order, $response] = $this->context($state, $status, $paid);
        $this->forbidEffects($order);
        $this->assertSame(['outcome' => $outcome, 'reason' => $reason, 'status' => $status], $applicator->apply(321, 'attempt_fixture_query_success_001', $response));
    }

    public static function preservedStateProvider(): array
    {
        return [
            ['approved', 'processing', true, 'no_change', 'approval_already_applied'],
            ['rejected', 'failed', false, 'no_change', 'terminal_already_applied'],
            ['cancelled', 'cancelled', false, 'no_change', 'terminal_already_applied'],
            ['rejected', 'cancelled', false, 'conflict', 'terminal_state_conflict'],
            ['cancelled', 'failed', false, 'conflict', 'terminal_state_conflict'],
            ['rejected', 'processing', true, 'conflict', 'paid_state_conflict'],
            ['cancelled', 'completed', true, 'conflict', 'paid_state_conflict'],
            ['rejected', 'processing', false, 'conflict', 'local_state_conflict'],
            ['cancelled', 'pending', true, 'conflict', 'local_state_conflict'],
            ['cancelled', 'checkout-draft', false, 'unsupported', 'unsupported_transition'],
        ];
    }

    /** @dataProvider contextGuardProvider */
    public function testServerGuardsPreventRejectionAndCancellation(string $state, string $case, string $outcome, string $reason, ?string $status): void
    {
        [$applicator, $order, $response] = $this->context($state);
        $attempt = 'attempt_fixture_query_success_001';
        switch ($case) {
            case 'amount':
                $response['payments'][0]['amount']['value'] = '125.49';
                break;
            case 'attempt':
                $attempt = 'other_synthetic_attempt';
                break;
            case 'reference':
                $response['order_reference'] = 'other_synthetic_order';
                break;
            case 'gateway':
                $order->shouldReceive('get_payment_method')->with('edit')->andReturn('other');
                break;
            case 'currency':
                $order->shouldReceive('get_currency')->with('edit')->andReturn('USD');
                break;
            case 'paid_date':
                $order->shouldReceive('get_date_paid')->with('edit')->andReturn(new \stdClass());
                break;
            case 'total':
                $order->shouldReceive('get_total')->with('edit')->andReturn('125.501');
                break;
        }
        $this->forbidEffects($order);
        $this->assertSame(['outcome' => $outcome, 'reason' => $reason, 'status' => $status], $applicator->apply(321, $attempt, $response));
    }

    public static function contextGuardProvider(): array
    {
        $cases = [
            ['amount', 'conflict', 'amount_mismatch', 'pending'],
            ['attempt', 'conflict', 'order_context_mismatch', null],
            ['reference', 'conflict', 'order_context_mismatch', null],
            ['gateway', 'conflict', 'order_context_mismatch', null],
            ['currency', 'conflict', 'order_context_mismatch', null],
            ['paid_date', 'conflict', 'paid_facts_disagree', 'pending'],
            ['total', 'unsupported', 'unsupported_order_total', null],
        ];
        $result = [];
        foreach (['rejected', 'cancelled'] as $state) {
            foreach ($cases as $case) {
                $result[] = array_merge([$state], $case);
            }
        }
        return $result;
    }

    /** @dataProvider invalidShapeProvider */
    public function testInvalidTerminalShapesNeverLoadOrders(string $case): void
    {
        $response = $this->response('rejected');
        switch ($case) {
            case 'extra':
                $response['raw_payload'] = 'SYNTHETIC private';
                break;
            case 'multiple':
                $response['payments'][] = $response['payments'][0];
                break;
            case 'pending':
                $response['state'] = 'processing';
                break;
            case 'error':
                $response['error']['code'] = 'UNREVIEWED_PROVIDER_CODE';
                break;
            case 'retry':
                $response['error']['retryable'] = true;
                break;
            case 'message':
                $response['error']['message'] = 'SYNTHETIC private';
                break;
            case 'float':
                $response['payments'][0]['amount']['value'] = 125.50;
                break;
            case 'transport':
                $response['payments'] = [];
                $response['error'] = ['category' => 'timeout', 'code' => 'TIMEOUT', 'retryable' => true];
                break;
        }
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $result = (new SmartPixStateApplicator(Mockery::mock(OrderMetadata::class), new SmartPixStateReducer(), Mockery::mock(Seller::class), $this->ownership()))
            ->apply(321, 'synthetic_attempt', $response);
        $this->assertSame(['outcome' => 'unsupported', 'reason' => 'invalid_terminal_context', 'status' => null], $result);
    }

    public static function invalidShapeProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], ['extra', 'multiple', 'pending', 'error', 'retry', 'message', 'float', 'transport']);
    }

    /** @dataProvider nativeFailureProvider */
    public function testNativeFailuresAndChangedPersistedFactsAreUncertainWithoutRetry(string $state, string $case, string $reason, ?string $status): void
    {
        $response = $this->response($state);
        $target = $state === 'rejected' ? 'failed' : 'cancelled';
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $effect = $pending->shouldReceive('update_status')->with($target)->once();
        if (in_array($case, ['throw', 'error', 'type_error'], true)) {
            $error = $case === 'error' ? new \Error('SYNTHETIC private hook failure')
                : ($case === 'type_error' ? new \TypeError('SYNTHETIC private hook failure')
                    : new \RuntimeException('SYNTHETIC private hook failure'));
            $effect->andThrow($error);
            $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
                'source' => 'mercadopago-smart-pix', 'reason' => 'native_status_threw',
            ]);
        } else {
            $effect->andReturn($case !== 'false');
        }
        $this->forbidManualEffects($pending);
        if ($case === 'reload') {
            $calls = 0;
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return' => static function () use (&$calls, $pending) {
                if (++$calls === 1) {
                    return $pending;
                }
                throw new \RuntimeException('SYNTHETIC private reload failure');
            }]);
        } else {
            $persisted = $this->orderSnapshot($metadata, $response, $case === 'unchanged' ? 'pending' : $target, false);
            if ($case === 'paid') {
                $persisted->shouldReceive('is_paid')->andReturn(true);
            } elseif ($case === 'amount') {
                $persisted->shouldReceive('get_total')->with('edit')->andReturn('125.49');
            } elseif ($case === 'transaction') {
                $persisted->shouldReceive('get_transaction_id')->with('edit')->andReturn('unexpected_synthetic_id');
            } elseif ($case === 'linkage') {
                $metadata->shouldReceive('getPpOrderId')->with($persisted)->andReturn('other_synthetic_order');
            } elseif ($case === 'paid_date') {
                $persisted->shouldReceive('get_date_paid')->with('edit')->andReturn(new \stdClass());
            }
            $this->forbidEffects($persisted);
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        }
        $result = (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership()))->apply(321, 'attempt_fixture_query_success_001', $response);
        $this->assertSame(['outcome' => 'uncertain', 'reason' => $reason, 'status' => $status], $result);
        $this->assertStringNotContainsString('private', json_encode($result));
    }

    public static function nativeFailureProvider(): array
    {
        $result = [];
        foreach (['rejected', 'cancelled'] as $state) {
            $target = $state === 'rejected' ? 'failed' : 'cancelled';
            foreach (['false', 'throw', 'error', 'type_error', 'reload', 'unchanged', 'paid', 'amount', 'transaction', 'linkage', 'paid_date'] as $case) {
                $reason = $case === 'reload' ? 'status_reload_failed'
                    : (in_array($case, ['false', 'throw', 'error', 'type_error'], true) ? 'native_status_reported_failure' : 'native_status_not_verified');
                $result[] = [$state, $case, $reason, in_array($case, ['false', 'throw', 'error', 'type_error'], true) ? $target : null];
            }
        }
        return $result;
    }

    /** @dataProvider approvalNativeFailureProvider */
    public function testApprovalNativeFailuresRemainUncertainWithoutRepeatingEffects(string $case, string $reason, ?string $status): void
    {
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $effect = $pending->shouldReceive('payment_complete')->withNoArgs()->once();
        if (in_array($case, ['throw', 'error', 'type_error', 'logging_error'], true)) {
            $error = $case === 'error' ? new \Error('SYNTHETIC private hook error')
                : ($case === 'type_error' ? new \TypeError('SYNTHETIC private hook type error')
                    : new \RuntimeException('SYNTHETIC private completion failure'));
            $effect->andThrow($error);
            $diagnostic = $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
                'source' => 'mercadopago-smart-pix', 'reason' => 'native_completion_threw',
            ]);
            if ($case === 'logging_error') {
                $diagnostic->andThrow(new \Error('SYNTHETIC private logging error'));
            }
        } else {
            $effect->andReturn($case !== 'false');
        }
        $pending->shouldNotReceive('update_status');
        $pending->shouldNotReceive('save');
        $pending->shouldNotReceive('add_order_note');
        if ($case === 'reload') {
            $calls = 0;
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return' => static function () use (&$calls, $pending) {
                if (++$calls === 1) {
                    return $pending;
                }
                throw new \RuntimeException('SYNTHETIC private reload failure');
            }]);
        } else {
            $persisted = $this->orderSnapshot($metadata, $response, $case === 'on_hold' ? 'on-hold' : 'processing', true);
            if ($case === 'not_paid') {
                $persisted->shouldReceive('is_paid')->andReturn(false);
            } elseif ($case === 'no_paid_date') {
                $persisted->shouldReceive('get_date_paid')->with('edit')->andReturn(null);
            } elseif ($case === 'transaction') {
                $persisted->shouldReceive('get_transaction_id')->with('edit')->andReturn('unexpected_synthetic_id');
            }
            $this->forbidEffects($persisted);
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        }
        $result = (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership()))
            ->apply(321, 'attempt_fixture_query_success_001', $response);
        $this->assertSame(['outcome' => 'uncertain', 'reason' => $reason, 'status' => $status], $result);
        $this->assertStringNotContainsString('private', json_encode($result));
    }

    public static function approvalNativeFailureProvider(): array
    {
        return [
            ['false', 'native_completion_reported_failure', 'processing'],
            ['throw', 'native_completion_reported_failure', 'processing'],
            ['error', 'native_completion_reported_failure', 'processing'],
            ['type_error', 'native_completion_reported_failure', 'processing'],
            ['logging_error', 'native_completion_reported_failure', 'processing'],
            ['not_paid', 'native_completion_not_verified', null],
            ['no_paid_date', 'native_completion_not_verified', null],
            ['on_hold', 'native_completion_not_verified', null],
            ['transaction', 'native_completion_not_verified', null],
            ['reload', 'completion_reload_failed', null],
        ];
    }

    /** @dataProvider throwableReadProvider */
    public function testThrowableDuringReadOrReloadIsSanitized(string $state, bool $reload): void
    {
        $response = $this->response($state);
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $reload ? $this->orderSnapshot($metadata, $response, 'pending', false) : Mockery::mock(WC_Order::class);
        if ($reload) {
            if ($state === 'approved') {
                $pending->shouldReceive('payment_complete')->once()->andReturn(true);
            } else {
                $pending->shouldReceive('update_status')->with($state === 'rejected' ? 'failed' : 'cancelled')->once()->andReturn(true);
            }
        } else {
            $this->forbidEffects($pending);
        }
        $calls = 0;
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => $reload ? 2 : 1,
            'return' => static function () use (&$calls, $pending, $reload) {
                if ($reload && ++$calls === 1) {
                    return $pending;
                }
                throw new \TypeError('SYNTHETIC_PRIVATE_READ');
            }]);
        $reason = !$reload ? 'order_read_failed' : ($state === 'approved' ? 'completion_reload_failed' : 'status_reload_failed');
        $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
            'source' => 'mercadopago-smart-pix', 'reason' => $reason,
        ]);
        $this->assertSame(
            ['outcome' => 'uncertain', 'reason' => $reason, 'status' => null],
            (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership()))
                ->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    /**
     * Each refused or uncertain outcome is reported once with the category of its reason class.
     * @dataProvider errorCategoryProvider
     */
    public function testRefusedOrUncertainOutcomesReportTheCategoryOfTheirReason(string $case, string $reason, string $category): void
    {
        $events = [];
        $telemetry = new SmartPixTelemetry($this->recordingSink($events));
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        if ($case === 'invalid') {
            $response['order_reference'] = '';
            WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        } elseif ($case === 'conflict') {
            $response['payments'][0]['amount']['value'] = '999.00';
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1,
                'return' => $this->orderSnapshot($metadata, $this->response('approved'), 'pending', false)]);
        } else {
            $pending = null;
            if ($case === 'reload') {
                $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
                $pending->shouldReceive('payment_complete')->once()->andReturn(true);
            }
            $calls = 0;
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => static function () use (&$calls, $pending, $case) {
                if ($case === 'reload' && ++$calls === 1) {
                    return $pending;
                }
                throw new \RuntimeException('SYNTHETIC_READ_FAILURE');
            }]);
            $this->logger->shouldReceive('warning')->once();
        }

        $result = (new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership(), $telemetry))
            ->apply(321, 'attempt_fixture_query_success_001', $response, 'pending_query');

        $this->assertSame($reason, $result['reason']);
        $errors = array_values(array_filter($events, static fn(array $event): bool => $event[0] === SmartPixMetricSchema::ERROR));
        $this->assertCount(1, $errors);
        $this->assertSame($category, $errors[0][2]['category']);
        $this->assertSame('pending_query', $errors[0][2]['source']);
    }

    public static function errorCategoryProvider(): array
    {
        return [
            'invalid normalized context' => ['invalid', 'invalid_terminal_context', 'invalid_contract'],
            'state decision conflict' => ['conflict', 'amount_mismatch', 'conflict'],
            'order read exception' => ['read', 'order_read_failed', 'internal_error'],
            'native verification exception' => ['reload', 'completion_reload_failed', 'persistence'],
        ];
    }

    public static function throwableReadProvider(): array
    {
        return [['approved', false], ['approved', true], ['rejected', true], ['cancelled', true]];
    }

    /** @dataProvider ownershipDenialProvider */
    public function testOwnershipDenialCannotReachNativeMethods(string $state, string $claim, string $outcome, string $reason): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $effect = $state === 'approved' ? 'complete_payment' : ($state === 'rejected' ? 'mark_failed' : 'mark_cancelled');
        $ownership->shouldReceive('acquire')->with(321, 'attempt_fixture_query_success_001', $effect)->once()
            ->andReturn(['outcome' => $claim, 'owner' => $claim === 'acquired' ? 'INVALID_OWNER' : null]);
        foreach (['start', 'complete', 'markUncertain', 'releaseReserved'] as $method) {
            $ownership->shouldNotReceive($method);
        }
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $applicator = new SmartPixStateApplicator(
            Mockery::mock(OrderMetadata::class),
            new SmartPixStateReducer(),
            Mockery::mock(Seller::class),
            $ownership
        );
        $this->assertSame(
            ['outcome' => $outcome, 'reason' => $reason, 'status' => null],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $this->response($state))
        );
    }

    public static function ownershipDenialProvider(): array
    {
        $cases = [];
        foreach (['approved', 'rejected', 'cancelled'] as $state) {
            foreach (
                [['busy', 'busy', 'native_effect_in_progress'], ['uncertain', 'uncertain', 'native_effect_uncertain'],
                ['conflict', 'conflict', 'ownership_context_conflict'], ['unavailable', 'uncertain', 'ownership_unavailable'],
                ['acquired', 'uncertain', 'ownership_unavailable']] as $case
            ) {
                $cases[] = array_merge([$state], $case);
            }
        }
        return $cases;
    }

    public function testCompletedTombstoneWithUnpaidLocalStateCannotRepeatCompletion(): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->andReturn(['outcome' => 'completed', 'owner' => null]);
        foreach (['start', 'complete', 'markUncertain', 'releaseReserved'] as $method) {
            $ownership->shouldNotReceive($method);
        }
        [$applicator, $order, $response] = $this->context('approved', 'pending', false, $ownership);
        $this->forbidEffects($order);
        $this->assertSame(
            ['outcome' => 'uncertain', 'reason' => 'completed_effect_not_verified', 'status' => 'pending'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public function testUnverifiedStartCannotExecuteOrReleaseAnExecutingRecord(): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->twice()->andReturn(
            ['outcome' => 'acquired', 'owner' => str_repeat('a', 64)],
            ['outcome' => 'busy', 'owner' => null]
        );
        $ownership->shouldReceive('start')->once()->andReturn(false);
        $ownership->shouldReceive('releaseReserved')->once()->andReturn(false);
        $ownership->shouldNotReceive('complete');
        $ownership->shouldNotReceive('markUncertain');
        [$applicator, $order, $response] = $this->context('approved', 'pending', false, $ownership);
        $this->forbidEffects($order);
        $this->assertSame(
            'ownership_release_not_verified',
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)['reason']
        );
        $this->assertSame('busy', $applicator->apply(321, 'attempt_fixture_query_success_001', $response)['outcome']);
    }

    public function testReentrantWorkerIsDeniedBeforeItCanCompletePayment(): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->twice()->andReturn(
            ['outcome' => 'acquired', 'owner' => str_repeat('a', 64)],
            ['outcome' => 'busy', 'owner' => null]
        );
        $ownership->shouldReceive('start')->once()->andReturn(true);
        $ownership->shouldReceive('complete')->once()->andReturn(true);
        $ownership->shouldNotReceive('releaseReserved');
        $ownership->shouldNotReceive('markUncertain');
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'processing', true);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership);
        $pending->shouldReceive('payment_complete')->once()->andReturnUsing(function () use ($applicator, $response): bool {
            $this->assertSame('busy', $applicator->apply(321, 'attempt_fixture_query_success_001', $response)['outcome']);
            return true;
        });
        $pending->shouldNotReceive('update_status');
        $this->assertSame('applied', $applicator->apply(321, 'attempt_fixture_query_success_001', $response)['outcome']);
    }

    public function testFailedCompletionJournalBlocksReplayEvenAfterNativeStateWasSaved(): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->twice()->andReturn(
            ['outcome' => 'acquired', 'owner' => str_repeat('a', 64)],
            ['outcome' => 'uncertain', 'owner' => null]
        );
        $ownership->shouldReceive('start')->once()->andReturn(true);
        $ownership->shouldReceive('complete')->once()->andReturn(false);
        $ownership->shouldReceive('markUncertain')->once()->andReturn(true);
        $ownership->shouldNotReceive('releaseReserved');
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'processing', true);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership);
        $this->assertSame(
            'ownership_completion_not_verified',
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)['reason']
        );
        $this->assertSame(
            'native_effect_uncertain',
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)['reason']
        );
    }

    /** @dataProvider ownershipAcquireThrowableProvider */
    public function testOwnershipAcquireThrowableDeniesReadsAndSanitizesDiagnostics(\Throwable $failure): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->andThrow($failure);
        foreach (['start', 'complete', 'markUncertain', 'releaseReserved'] as $method) {
            $ownership->shouldNotReceive($method);
        }
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
            'source' => 'mercadopago-smart-pix', 'reason' => 'ownership_unavailable',
        ]);
        $applicator = new SmartPixStateApplicator(
            Mockery::mock(OrderMetadata::class),
            new SmartPixStateReducer(),
            Mockery::mock(Seller::class),
            $ownership
        );
        $this->assertSame(
            ['outcome' => 'uncertain', 'reason' => 'ownership_unavailable', 'status' => null],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $this->response('approved'))
        );
    }

    public static function ownershipAcquireThrowableProvider(): array
    {
        return [[new \RuntimeException('SYNTHETIC_PRIVATE_DB')], [new \Error('SYNTHETIC_PRIVATE_DB')]];
    }

    /** @dataProvider ownershipStartFailureProvider */
    public function testFailedExecutionAdmissionCannotReachNativeMethods($failure, string $reason, ?string $status): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $owner = str_repeat('a', 64);
        $ownership->shouldReceive('acquire')->once()->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $start = $ownership->shouldReceive('start')->with(321, 'attempt_fixture_query_success_001', 'complete_payment', $owner)->once();
        if ($failure instanceof \Throwable) {
            $start->andThrow($failure);
            $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
                'source' => 'mercadopago-smart-pix', 'reason' => 'ownership_execution_failed',
            ]);
        } else {
            $start->andReturn(false);
        }
        $ownership->shouldReceive('releaseReserved')->with(321, 'attempt_fixture_query_success_001', 'complete_payment', $owner)->once()->andReturn(true);
        $ownership->shouldNotReceive('complete');
        $ownership->shouldNotReceive('markUncertain');
        [$applicator, $order, $response] = $this->context('approved', 'pending', false, $ownership);
        $this->forbidEffects($order);
        $this->assertSame(
            ['outcome' => 'uncertain', 'reason' => $reason, 'status' => $status],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public static function ownershipStartFailureProvider(): array
    {
        return [
            [false, 'ownership_start_not_verified', 'pending'],
            [new \RuntimeException('SYNTHETIC_PRIVATE_START'), 'ownership_execution_failed', null],
            [new \Error('SYNTHETIC_PRIVATE_START'), 'ownership_execution_failed', null],
        ];
    }

    /** @dataProvider reservationReleaseFailureProvider */
    public function testUnverifiedReservationReleaseCannotReportTheOriginalGuardAsSuccess(bool $throws): void
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $owner = str_repeat('a', 64);
        $ownership->shouldReceive('acquire')->once()->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $release = $ownership->shouldReceive('releaseReserved')->with(321, 'attempt_fixture_query_success_001', 'complete_payment', $owner)->once();
        if ($throws) {
            $release->andThrow(new \Error('SYNTHETIC_PRIVATE_RELEASE'));
            $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
                'source' => 'mercadopago-smart-pix', 'reason' => 'ownership_finalization_failed',
            ]);
        } else {
            $release->andReturn(false);
        }
        foreach (['start', 'complete', 'markUncertain'] as $method) {
            $ownership->shouldNotReceive($method);
        }
        [$applicator, $order, $response] = $this->context('approved', 'pending', false, $ownership);
        $order->shouldReceive('get_refunds')->andReturn([new \stdClass()]);
        $this->forbidEffects($order);
        $this->assertSame(
            ['outcome' => 'uncertain', 'reason' => $throws ? 'ownership_finalization_failed' : 'ownership_release_not_verified', 'status' => 'pending'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public static function reservationReleaseFailureProvider(): array
    {
        return [[false], [true]];
    }

    /** @dataProvider interruptedOwnershipFinalizationProvider */
    public function testUncertainFinalizationAfterNativePersistenceDoesNotRepeatCompletion(
        string $completion,
        string $uncertain,
        bool $nativeResult,
        string $reason,
        ?string $status
    ): void {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $owner = str_repeat('a', 64);
        $arguments = [321, 'attempt_fixture_query_success_001', 'complete_payment', $owner];
        $ownership->shouldReceive('acquire')->twice()->andReturn(
            ['outcome' => 'acquired', 'owner' => $owner],
            ['outcome' => 'busy', 'owner' => null]
        );
        $ownership->shouldReceive('start')->withArgs($arguments)->once()->andReturn(true);
        if ($completion === 'unused') {
            $ownership->shouldNotReceive('complete');
        } else {
            $complete = $ownership->shouldReceive('complete')->withArgs($arguments)->once();
            if ($completion === 'throw') {
                $complete->andThrow(new \Error('SYNTHETIC_PRIVATE_COMPLETE'));
                $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
                    'source' => 'mercadopago-smart-pix', 'reason' => 'ownership_execution_failed',
                ]);
            } else {
                $complete->andReturn(false);
            }
        }
        $mark = $ownership->shouldReceive('markUncertain')->withArgs($arguments)->once();
        if ($uncertain === 'throw') {
            $mark->andThrow(new \Error('SYNTHETIC_PRIVATE_UNCERTAIN'));
            $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
                'source' => 'mercadopago-smart-pix', 'reason' => 'ownership_finalization_failed',
            ]);
        } else {
            $mark->andReturn($uncertain === 'true');
        }
        $ownership->shouldNotReceive('releaseReserved');
        $response = $this->response('approved');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'processing', true);
        $pending->shouldReceive('payment_complete')->withNoArgs()->once()->andReturn($nativeResult);
        $pending->shouldNotReceive('update_status');
        $pending->shouldNotReceive('save');
        $pending->shouldNotReceive('add_order_note');
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership);
        $result = $applicator->apply(321, 'attempt_fixture_query_success_001', $response);
        $this->assertSame(['outcome' => 'uncertain', 'reason' => $reason, 'status' => $status], $result);
        $this->assertStringNotContainsString('PRIVATE', json_encode($result));
        $this->assertSame(
            ['outcome' => 'busy', 'reason' => 'native_effect_in_progress', 'status' => null],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public static function interruptedOwnershipFinalizationProvider(): array
    {
        return [
            ['false', 'false', true, 'ownership_completion_not_verified', 'processing'],
            ['false', 'throw', true, 'ownership_finalization_failed', 'processing'],
            ['throw', 'true', true, 'ownership_execution_failed', null],
            ['unused', 'false', false, 'native_completion_reported_failure', 'processing'],
            ['unused', 'throw', false, 'ownership_finalization_failed', 'processing'],
        ];
    }

    /** @dataProvider ownershipAuthoritativeGuardProvider */
    public function testAdmissionAndCompletedTombstoneRevalidateCurrentNativeFacts(
        string $claim,
        string $changedFact,
        string $reason,
        ?string $status
    ): void {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $owner = str_repeat('a', 64);
        [$applicator, $order, $response] = $this->context('approved', 'pending', false, $ownership);
        $ownership->shouldReceive('acquire')->once()->andReturnUsing(static function () use ($claim, $owner, $changedFact, $order): array {
            // The authoritative values change at admission, before the applicator reads them.
            if ($changedFact === 'amount') {
                $order->shouldReceive('get_total')->with('edit')->andReturn('125.51');
            } elseif ($changedFact === 'refund') {
                $order->shouldReceive('get_refunds')->andReturn([new \stdClass()]);
            } else {
                $order->shouldReceive('get_currency')->with('edit')->andReturn('USD');
            }
            return ['outcome' => $claim, 'owner' => $claim === 'acquired' ? $owner : null];
        });
        foreach (['start', 'complete', 'markUncertain'] as $method) {
            $ownership->shouldNotReceive($method);
        }
        if ($claim === 'acquired') {
            $ownership->shouldReceive('releaseReserved')->with(321, 'attempt_fixture_query_success_001', 'complete_payment', $owner)->once()->andReturn(true);
        } else {
            $ownership->shouldNotReceive('releaseReserved');
        }
        $this->forbidEffects($order);
        $this->assertSame(
            ['outcome' => 'conflict', 'reason' => $reason, 'status' => $status],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response)
        );
    }

    public static function ownershipAuthoritativeGuardProvider(): array
    {
        $cases = [];
        foreach (['acquired', 'completed'] as $claim) {
            foreach ([['amount', 'amount_mismatch', 'pending'], ['refund', 'refund_context_conflict', 'pending'], ['currency', 'order_context_mismatch', null]] as $case) {
                $cases[] = array_merge([$claim], $case);
            }
        }
        return $cases;
    }

    /** @dataProvider rejectionMetricProvider */
    public function testAppliedRejectionCountsItsBuyerClassWithABoundedMessage(string $code, string $source, string $value, string $message): void
    {
        $response = $this->response('rejected');
        $response['error']['code'] = $code;
        $events = [];
        $applicator = $this->failingApplicator($response, new SmartPixTelemetry($this->recordingSink($events)));

        $this->assertSame(
            ['outcome' => 'applied', 'reason' => 'native_unpaid_status_verified', 'status' => 'failed'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, $source, 'server')
        );
        $context = ['operation' => 'apply', 'source' => $source, 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $this->assertSame([
            [SmartPixMetricSchema::EFFECT, 'applied', $context + ['reason_class' => 'native_verification', 'native_status' => 'failed']],
        ], $events);
    }

    public static function rejectionMetricProvider(): array
    {
        return [
            'insufficient balance' => ['SMART_PIX_REJECTED_INSUFFICIENT_AMOUNT', 'pending_query', 'insufficient_balance', 'insufficient_amount'],
            'limit exceeded' => ['SMART_PIX_REJECTED_REJECTED_CAP_EXCEEDED', 'notification', 'limit_exceeded', 'rejected_cap_exceeded'],
            'high risk' => ['SMART_PIX_REJECTED_HIGH_RISK', 'internal', 'rejected', 'high_risk'],
            'reason outside the allowlist' => ['SMART_PIX_REJECTED_REJECTED_BY_REGULATIONS', 'pending_query', 'rejected', 'other'],
            'longest bounded reason' => ['SMART_PIX_REJECTED_' . str_repeat('A', 64), 'notification', 'rejected', 'other'],
        ];
    }

    public function testRejectionAppliedFromTheSyncResponseIsNotCountedAgain(): void
    {
        $response = $this->response('rejected');
        $events = [];
        $applicator = $this->failingApplicator($response, new SmartPixTelemetry($this->recordingSink($events)));

        $this->assertSame(
            ['outcome' => 'applied', 'reason' => 'native_unpaid_status_verified', 'status' => 'failed'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'sync_response', 'classic')
        );
        $this->assertSame([SmartPixMetricSchema::EFFECT], array_column($events, 0));
    }

    public function testRejectionMetricSinkFailurePreservesTheAppliedResult(): void
    {
        $response = $this->response('rejected');
        $names = [];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->andReturnUsing(static function (string $name) use (&$names): void {
            $names[] = $name;
            throw new \RuntimeException('SYNTHETIC_PRIVATE_TRANSPORT');
        });
        $sink->shouldNotReceive('distribution');
        $applicator = $this->failingApplicator($response, new SmartPixTelemetry($sink));

        $result = $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'pending_query', 'server');

        $this->assertSame(['outcome' => 'applied', 'reason' => 'native_unpaid_status_verified', 'status' => 'failed'], $result);
        $this->assertSame([SmartPixMetricSchema::EFFECT], $names);
    }

    public function testServerTaskReportsTheTerminalRejectionAndTelemetryFailureCannotChangeIt(): void
    {
        $response = $this->response('rejected');
        $observed = [];
        $telemetry = new SmartPixTelemetry(null, null, null, static function ($id, $source) use (&$observed): void {
            $observed[] = [$id, $source];
            throw new \RuntimeException('Diagnostic transport unavailable.');
        });
        $applicator = $this->failingApplicator($response, $telemetry);

        $result = $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'internal', 'server');

        $this->assertSame('applied', $result['outcome']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame([[321, 'internal']], $observed);
    }

    public function testRejectionReplayOnAnAlreadyFailedOrderIsNotCountedAgain(): void
    {
        $response = $this->response('rejected');
        $events = [];
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $failed = $this->orderSnapshot($metadata, $response, 'failed', false);
        $this->forbidEffects($failed);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $failed]);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $owner = str_repeat('a', 64);
        $ownership->shouldReceive('acquire')->with(321, 'attempt_fixture_query_success_001', 'mark_failed')->once()
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $ownership->shouldReceive('releaseReserved')->with(321, 'attempt_fixture_query_success_001', 'mark_failed', $owner)->once()->andReturn(true);
        $ownership->shouldNotReceive('start', 'complete', 'markUncertain');
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, new SmartPixTelemetry($this->recordingSink($events)));

        $this->assertSame(
            ['outcome' => 'no_change', 'reason' => 'terminal_already_applied', 'status' => 'failed'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'notification', 'server')
        );
        $this->assertSame([SmartPixMetricSchema::EFFECT], array_column($events, 0));
        $this->assertSame('no_change', $events[0][1]);
    }

    /** @dataProvider deniedRejectionClaimProvider */
    public function testDeniedRejectionOwnershipHasNoEffectAndIsNotCounted(string $claim, string $outcome, string $reason): void
    {
        $events = [];
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->with(321, 'attempt_fixture_query_success_001', 'mark_failed')->once()
            ->andReturn(['outcome' => $claim, 'owner' => null]);
        $ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $applicator = new SmartPixStateApplicator(
            Mockery::mock(OrderMetadata::class),
            new SmartPixStateReducer(),
            Mockery::mock(Seller::class),
            $ownership,
            new SmartPixTelemetry($this->recordingSink($events))
        );

        $this->assertSame(
            ['outcome' => $outcome, 'reason' => $reason, 'status' => null],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $this->response('rejected'), 'notification', 'server')
        );
        if ($claim !== 'busy') { $this->assertApplicationDiagnostic($events, false, $claim === 'conflict' ? 'conflict' : 'persistence'); }
        $this->assertSame([SmartPixMetricSchema::EFFECT], array_column($events, 0));
    }

    public static function deniedRejectionClaimProvider(): array
    {
        return [
            'busy' => ['busy', 'busy', 'native_effect_in_progress'],
            'uncertain' => ['uncertain', 'uncertain', 'native_effect_uncertain'],
            'conflict' => ['conflict', 'conflict', 'ownership_context_conflict'],
            'unavailable' => ['unavailable', 'uncertain', 'ownership_unavailable'],
        ];
    }

    public function testCompletedRejectionTombstoneOnAPendingOrderIsUncertainAndNotCounted(): void
    {
        $events = [];
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->with(321, 'attempt_fixture_query_success_001', 'mark_failed')->once()
            ->andReturn(['outcome' => 'completed', 'owner' => null]);
        $ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');
        $response = $this->response('rejected');
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $this->forbidEffects($pending);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $pending]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, new SmartPixTelemetry($this->recordingSink($events)));

        $this->assertSame(
            ['outcome' => 'uncertain', 'reason' => 'completed_effect_not_verified', 'status' => 'pending'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'notification', 'server')
        );
        // The completed tombstone is reported only through the uncertain effect result.
        $this->assertApplicationDiagnostic($events, false);
        $this->assertSame([SmartPixMetricSchema::EFFECT], array_column($events, 0));
        $this->assertSame('uncertain', $events[0][1]);
    }

    /** @dataProvider unverifiedRejectionProvider */
    public function testUnverifiedNativeFailedStatusIsUncertainAndNotCounted(bool $reported, string $persistedStatus, string $reason, ?string $status): void
    {
        $response = $this->response('rejected');
        $events = [];
        $applicator = $this->failingApplicator($response, new SmartPixTelemetry($this->recordingSink($events)), $persistedStatus, $reported);

        $this->assertSame(
            ['outcome' => 'uncertain', 'reason' => $reason, 'status' => $status],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'pending_query', 'server')
        );
        $this->assertApplicationDiagnostic($events, false);
        $this->assertSame([SmartPixMetricSchema::EFFECT], array_column($events, 0));
        $this->assertSame('uncertain', $events[0][1]);
    }

    public static function unverifiedRejectionProvider(): array
    {
        return [
            'native method reported failure' => [false, 'failed', 'native_status_reported_failure', 'failed'],
            'persisted status still pending' => [true, 'pending', 'native_status_not_verified', null],
            'persisted status cancelled' => [true, 'cancelled', 'native_status_not_verified', null],
        ];
    }

    public function testAppliedCancellationIsNotCountedAsARejection(): void
    {
        $response = $this->response('cancelled');
        $events = [];
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'cancelled', false);
        $pending->shouldReceive('update_status')->with('cancelled')->once()->andReturn(true);
        $this->forbidManualEffects($pending);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership(), new SmartPixTelemetry($this->recordingSink($events)));

        $this->assertSame(
            ['outcome' => 'applied', 'reason' => 'native_unpaid_status_verified', 'status' => 'cancelled'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'notification', 'server')
        );
        $this->assertSame([SmartPixMetricSchema::EFFECT], array_column($events, 0));
    }

    public function testAppliedApprovalFromAQueryIsNotCountedAsARejection(): void
    {
        $response = $this->response('approved');
        $events = [];
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, 'processing', true);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership(), new SmartPixTelemetry($this->recordingSink($events)));

        $this->assertSame(
            ['outcome' => 'applied', 'reason' => 'native_paid_state_verified', 'status' => 'processing'],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'pending_query', 'server')
        );
        $this->assertSame([SmartPixMetricSchema::EFFECT], array_column($events, 0));
    }

    /** @dataProvider invalidObservationProvider */
    public function testRejectionWithANonArrayObservationNeverLoadsTheOrder($observed): void
    {
        $response = $this->response('rejected');
        $response['observed'] = $observed;
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire');
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::EFFECT, 'unsupported', Mockery::type('array'));
        $applicator = new SmartPixStateApplicator(
            Mockery::mock(OrderMetadata::class),
            new SmartPixStateReducer(),
            Mockery::mock(Seller::class),
            $ownership,
            new SmartPixTelemetry($sink)
        );

        $this->assertSame(
            ['outcome' => 'unsupported', 'reason' => 'invalid_terminal_context', 'status' => null],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'pending_query', 'server')
        );
    }

    public static function invalidObservationProvider(): array
    {
        return ['null' => [null], 'string' => ['failed'], 'boolean' => [false], 'integer' => [0]];
    }

    /** @dataProvider nonRejectionErrorProvider */
    public function testRejectedPaymentWithoutAClosedRejectionErrorNeverLoadsTheOrder(array $error): void
    {
        $response = $this->response('rejected');
        $response['error'] = $error;
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire');
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $applicator = new SmartPixStateApplicator(Mockery::mock(OrderMetadata::class), new SmartPixStateReducer(), Mockery::mock(Seller::class), $ownership);

        $this->assertSame(
            ['outcome' => 'unsupported', 'reason' => 'invalid_terminal_context', 'status' => null],
            $applicator->apply(321, 'attempt_fixture_query_success_001', $response, 'pending_query')
        );
    }

    public static function nonRejectionErrorProvider(): array
    {
        return [
            'legacy unknown category' => [['category' => 'unknown', 'code' => 'SMART_PIX_REJECTED_HIGH_RISK', 'retryable' => false]],
            'legacy terminal code' => [['category' => 'unknown', 'code' => 'SMART_PIX_TERMINAL_FAILURE', 'retryable' => false]],
            'non-rejection code' => [['category' => 'rejected', 'code' => 'SMART_PIX_UNKNOWN', 'retryable' => false]],
            'lowercase reason' => [['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_high_risk', 'retryable' => false]],
            'reason too long' => [['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_' . str_repeat('A', 65), 'retryable' => false]],
        ];
    }

    /**
     * A pending order whose native failed-status change is persisted as $persistedStatus.
     * @param array<string, mixed> $response
     */
    private function failingApplicator(
        array $response,
        SmartPixTelemetry $telemetry,
        string $persistedStatus = 'failed',
        bool $reported = true
    ): SmartPixStateApplicator {
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $pending = $this->orderSnapshot($metadata, $response, 'pending', false);
        $persisted = $this->orderSnapshot($metadata, $response, $persistedStatus, false);
        $pending->shouldReceive('update_status')->with('failed')->once()->andReturn($reported);
        $this->forbidManualEffects($pending);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$pending, $persisted]]);
        return new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $this->ownership(), $telemetry);
    }

    /** @param list<array<int, mixed>> $events */
    private function recordingSink(array &$events): SmartPixMetricSink
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->andReturnUsing(static function (...$arguments) use (&$events): void {
            $events[] = $arguments;
        });
        $sink->shouldNotReceive('distribution');
        return $sink;
    }

    /** @return array{SmartPixStateApplicator, mixed, array<string, mixed>} */
    private function context(string $state, string $status = 'pending', bool $paid = false, ?SmartPixEffectOwnershipStore $ownership = null): array
    {
        $response = $this->response($state);
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $order = $this->orderSnapshot($metadata, $response, $status, $paid);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);
        return [new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership ?? $this->ownership()), $order, $response];
    }

    private function ownership(): SmartPixEffectOwnershipStore
    {
        $store = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $store->shouldReceive('acquire')->andReturn(['outcome' => 'acquired', 'owner' => str_repeat('a', 64)]);
        $store->shouldReceive('start')->andReturn(true);
        $store->shouldReceive('complete')->andReturn(true);
        $store->shouldReceive('markUncertain')->andReturn(true);
        $store->shouldReceive('releaseReserved')->andReturn(true);
        return $store;
    }

    private function forbidManualEffects($order): void
    {
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('save');
        $order->shouldNotReceive('add_order_note');
    }

    private function forbidEffects($order): void
    {
        $this->forbidManualEffects($order);
        $order->shouldNotReceive('update_status');
    }

    /** @return array<string, mixed> */
    private function response(string $state): array
    {
        return [
            'outcome' => $state === 'rejected' ? 'failure' : 'success',
            'state' => $state === 'approved' ? 'terminal_success' : 'terminal_failure',
            'order_reference' => 'synthetic_terminal_order',
            'eligibility' => null,
            'authorization' => null,
            'payments' => [[
                'reference' => 'synthetic_terminal_payment',
                'state' => $state,
                'amount' => ['value' => '125.50', 'currency' => 'BRL'],
            ]],
            'error' => $state === 'rejected'
                ? ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_HIGH_RISK', 'retryable' => false]
                : null,
        ];
    }

    /** @param array<string, mixed> $response */
    private function orderSnapshot($metadata, array $response, string $status, bool $paid)
    {
        $order = Mockery::mock(WC_Order::class);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_id')->andReturn(321)->byDefault();
        $order->shouldReceive('get_data_store')->once()->andReturn($store);
        $order->shouldReceive('read_meta_data')->with(true)->once();
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID)->byDefault();
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL')->byDefault();
        $order->shouldReceive('get_total')->with('edit')->andReturn('125.50')->byDefault();
        $order->shouldReceive('get_status')->with('edit')->andReturn($status)->byDefault();
        $order->shouldReceive('is_paid')->andReturn($paid)->byDefault();
        $order->shouldReceive('get_date_paid')->with('edit')->andReturn($paid ? new \stdClass() : null)->byDefault();
        $order->shouldReceive('get_refunds')->andReturn([])->byDefault();
        $order->shouldReceive('get_transaction_id')->with('edit')->andReturn('')->byDefault();
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1)->byDefault();
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('attempt_fixture_query_success_001')->byDefault();
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn($response['order_reference'])->byDefault();
        return $order;
    }

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->logger = Mockery::mock();
        $this->logger->shouldReceive('warning')->byDefault();
        WP_Mock::userFunction('wc_get_logger', ['return' => $this->logger]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }
}
