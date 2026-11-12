<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SmartPixTelemetryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @dataProvider mutationMetricProvider */
    public function testMutationResultsAndDurationsUseTheClosedSemanticSchema(string $operation, string $result): void
    {
        $details = ['operation' => $operation, 'source' => 'internal', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $failure = $result === 'failed';
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::MUTATION, $result,
            $failure ? $details + ['category' => 'conflict'] : $details);
        $sink->shouldReceive('distribution')->once()->with(SmartPixMetricSchema::TIME, 0.125,
            $details + ['outcome' => $failure ? 'failure' : 'success']);
        $telemetry = new SmartPixTelemetry($sink);
        $telemetry->mutationResult($operation, $result, 'amount_conflict');
        $telemetry->processingTime($operation, 0.125, $failure ? 'failure' : 'success', 'server', 'internal');
        $this->assertTrue(true);
    }

    public static function mutationMetricProvider(): array
    {
        return [['refund', 'succeeded'], ['refund', 'already_applied'], ['refund', 'failed'], ['cancel', 'succeeded'], ['cancel', 'failed']];
    }

    /** @dataProvider applicationErrorCategoryProvider */
    public function testApplicationErrorCategoryFollowsTheReasonClass(string $reason, bool $exception, string $category): void
    {
        $this->assertSame($category, (new SmartPixTelemetry())->applicationErrorCategory($reason, $exception));
    }

    public static function applicationErrorCategoryProvider(): array
    {
        return [
            'invalid context' => ['invalid_terminal_context', false, 'invalid_contract'],
            'order context mismatch' => ['order_context_mismatch', false, 'conflict'],
            'paid facts disagree' => ['paid_facts_disagree', false, 'conflict'],
            'state decision' => ['amount_mismatch', false, 'conflict'],
            'ownership conflict' => ['ownership_context_conflict', false, 'conflict'],
            'ownership failure' => ['ownership_unavailable', true, 'persistence'],
            'native effect' => ['native_status_reported_failure', true, 'persistence'],
            'native verification' => ['native_completion_not_verified', false, 'persistence'],
            'order read exception' => ['order_read_failed', true, 'internal_error'],
        ];
    }

    public function testUnpaidOrderExpiryRetainsItsSemanticCounterWithoutClaimingPaymentExpired(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::EXPIRATION, 'cancel_requested', [
            'operation' => 'cancel', 'source' => 'internal', 'surface' => 'server', 'payment_method' => 'smart_pix', 'site_id' => 'MLB',
        ]);
        (new SmartPixTelemetry($sink))->pendingExpiration();
        $payload = \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricPayload::semantic(SmartPixMetricSchema::EXPIRATION, 'cancel_requested', [
            'operation' => 'cancel', 'surface' => 'server', 'site_id' => 'MLB',
        ]);
        $this->assertSame('1', $payload['value']);
        $this->assertSame('unpaid_order_timeout', $payload['details']['reason']);
        $this->assertArrayNotHasKey('payment_status', $payload['details']);
    }

    public function testSuccessfulCreationRecordsOnlyTheNumericDuration(): void
    {
        $details = ['operation' => 'create', 'source' => 'sync_response', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldReceive('distribution')->once()->withArgs(function ($name, $value, $context) use ($details): bool {
            $this->assertSame(SmartPixMetricSchema::TIME, $name);
            $this->assertEqualsWithDelta(0.125, $value, 0.000001);
            $this->assertSame($details + ['outcome' => 'success'], $context);
            return true;
        });
        $ticks = [1.0, 1.125];
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$ticks): float {
            return array_shift($ticks);
        });
        $start = $telemetry->startTimer();
        $telemetry->processingTime('create', $telemetry->elapsed($start), 'success', 'classic', 'sync_response');
        $this->assertSame([], $ticks);
    }

    /** @dataProvider surfaceProvider */
    public function testOrderCreationTimeIsASuccessfulSyncCreationDistribution(string $surface): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldReceive('distribution')->once()->with(SmartPixMetricSchema::ORDER_CREATION_TIME, 0.432, [
            'operation' => 'create', 'source' => 'sync_response', 'surface' => $surface,
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'outcome' => 'success',
        ]);
        (new SmartPixTelemetry($sink))->orderCreationTime(0.432, $surface);
    }

    public static function surfaceProvider(): array
    {
        return ['classic' => ['classic'], 'blocks' => ['blocks'], 'order pay' => ['order_pay']];
    }

    /** @dataProvider approvalSourceProvider */
    public function testPaymentApprovalTimeIsASuccessfulDistributionPerObservationSource(string $operation, string $source): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldReceive('distribution')->once()->with(SmartPixMetricSchema::PAYMENT_APPROVAL_TIME, 42.0, [
            'operation' => $operation, 'source' => $source, 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'outcome' => 'success',
        ]);
        (new SmartPixTelemetry($sink))->paymentApprovalTime(42.0, $operation, 'server', $source);
    }

    public static function approvalSourceProvider(): array
    {
        return [
            'poll' => ['get', 'pending_query'],
            'thank-you page' => ['get', 'internal'],
            'notification' => ['notification', 'notification'],
        ];
    }

    public function testDedicatedDistributionsRejectUnsupportedContextsAndDurations(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink);
        foreach ([null, -1.0, NAN, INF] as $duration) {
            $telemetry->orderCreationTime($duration, 'classic');
            $telemetry->paymentApprovalTime($duration, 'get', 'server', 'pending_query');
        }
        $telemetry->orderCreationTime(0.432, 'SYNTHETIC_PRIVATE_SURFACE');
        foreach ([['create', 'pending_query'], ['apply', 'notification'], ['get', 'sync_response'], ['get', 'SYNTHETIC_PRIVATE']] as [$operation, $source]) {
            $telemetry->paymentApprovalTime(42.0, $operation, 'server', $source);
        }
        $this->assertTrue(true);
    }

    public function testDedicatedDistributionSinkFailureNeverEscapes(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('distribution')->twice()->andThrow(new \Error('SYNTHETIC_PRIVATE_METRIC'));
        $telemetry = new SmartPixTelemetry($sink);
        $telemetry->orderCreationTime(0.432, 'blocks');
        $telemetry->paymentApprovalTime(42.0, 'notification', 'server', 'notification');
        $this->assertTrue(true);
    }
    public function testMetricSinkThrowablesNeverEscapeSemanticEmission(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->twice()->andThrow(new \Error('SYNTHETIC_PRIVATE_METRIC'));
        $sink->shouldReceive('distribution')->once()->andThrow(new \TypeError('SYNTHETIC_PRIVATE_METRIC'));
        $telemetry = new SmartPixTelemetry($sink);
        $telemetry->error('get', 'timeout', 'server', 'pending_query');
        $telemetry->processingTime('create', 0.125, 'success', 'classic', 'sync_response');
        $telemetry->notificationRejected('invalid_auth');
        $this->assertTrue(true);
    }

    public function testNotificationCounterAndDurationHaveOnlyBoundedContext(): void
    {
        $context = ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with('smart_pix_server_notification_rejected', 'body_too_large', $context);
        $sink->shouldReceive('distribution')->once()->with('smart_pix_server_processing_time', 0.125, $context + ['outcome' => 'failure']);
        $telemetry = new SmartPixTelemetry($sink);
        $telemetry->notificationRejected('body_too_large');
        $telemetry->processingTime('notification', 0.125, 'failure', 'server', 'notification');
        $this->assertTrue(true);
    }

    public function testInvalidPayloadNotificationRejectionUsesTheSameBoundedContext(): void
    {
        $context = ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with('smart_pix_server_notification_rejected', 'invalid_payload', $context);
        $telemetry = new SmartPixTelemetry($sink);
        $telemetry->notificationRejected('invalid_payload');
        $this->assertTrue(true);
    }

    public function testPrivateReasonAndCustomOrderStatusNeverBecomeDimensions(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with('smart_pix_server_effect_result', 'uncertain', [
            'operation' => 'apply', 'source' => 'internal', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'reason_class' => 'state_decision', 'native_status' => 'unknown',
        ]);
        (new SmartPixTelemetry($sink))->effectResult('uncertain', 'SYNTHETIC_PRIVATE_REASON', 'SYNTHETIC_PRIVATE_STATUS', 'server', 'internal');
        $this->assertTrue(true);
    }

    /** @dataProvider paidStatusProvider */
    public function testStorePaidStatusBecomesAFixedValueWithoutItsName(?string $status, bool $paid, string $expected): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with('smart_pix_server_effect_result', 'applied', [
            'operation' => 'apply', 'source' => 'internal', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'reason_class' => 'native_verification', 'native_status' => $expected,
        ]);
        (new SmartPixTelemetry($sink))->effectResult('applied', 'native_paid_state_verified', $status, 'server', 'internal', $paid);
        $this->assertTrue(true);
    }

    public static function paidStatusProvider(): array
    {
        return [
            'store paid status' => ['SYNTHETIC_PRIVATE_STATUS', true, 'paid_custom'],
            'native paid status keeps its name' => ['processing', true, 'processing'],
            'unpaid custom status' => ['SYNTHETIC_PRIVATE_STATUS', false, 'unknown'],
            'missing status' => [null, true, 'unknown'],
        ];
    }

    public function testInvalidFieldsAndNonPositiveDurationsNeverReachTheSink(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink);
        $telemetry->error('get', 'SYNTHETIC_PRIVATE_TOKEN', 'server', 'pending_query');
        $telemetry->effectResult('busy', 'native_effect_in_progress', null, 'SYNTHETIC_PRIVATE_SURFACE', 'internal');
        $telemetry->notificationRejected('SYNTHETIC_PRIVATE_TOKEN');
        foreach ([null, 0.0, -1.0, NAN, INF] as $duration) {
            $telemetry->processingTime('create', $duration, 'success', 'classic', 'sync_response');
        }
        $this->assertTrue(true);
    }

    public function testInvalidOrThrowingClockDoesNotFabricateDuration(): void
    {
        $clock = static function (): float {
            throw new \Error('SYNTHETIC_PRIVATE_CLOCK');
        };
        $telemetry = new SmartPixTelemetry(null, $clock);
        $this->assertNull($telemetry->startTimer());
        $this->assertNull($telemetry->elapsed(1.0));
        $this->assertNull($telemetry->elapsed(null));
        $this->assertNull((new SmartPixTelemetry(null, static fn(): float => 1.0))->elapsed(1.0));
        $this->assertNull((new SmartPixTelemetry(null, static fn(): float => NAN))->startTimer());
    }

    public function testMissingSinkIsSafeForAllSemanticMethods(): void
    {
        $telemetry = new SmartPixTelemetry();
        $telemetry->error('validation', 'validation', 'classic', 'sync_response');
        $telemetry->orderCreationTime(0.432, 'classic');
        $telemetry->paymentApprovalTime(42.0, 'get', 'server', 'pending_query');
        $telemetry->effectResult('busy', 'native_effect_in_progress', null, 'server', 'pending_query');
        $telemetry->notificationRejected('invalid_auth');
        $telemetry->processingTime('notification', 0.125, 'success', 'server', 'notification');
        $this->assertTrue(true);
    }

    public function testSchemaRejectsUnknownFieldsAndMismatchedMetricSemantics(): void
    {
        $context = ['operation' => 'create', 'source' => 'sync_response', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        foreach (['owner', 'attempt_hash', 'order_id', 'cust_id', 'sdk_instance_id', 'account', 'token', 'headers', 'payload'] as $field) {
            $this->assertFalse(SmartPixMetricSchema::validDistribution(
                SmartPixMetricSchema::ORDER_CREATION_TIME,
                0.125,
                $context + ['outcome' => 'success', $field => 'SYNTHETIC_PRIVATE']
            ));
        }
        // The removed creation and admission counters are no longer part of the schema.
        $this->assertFalse(SmartPixMetricSchema::validCount('smart_pix_server_create_result', 'pending', $context));
        $this->assertFalse(SmartPixMetricSchema::validCount('smart_pix_server_effect_admission', 'acquired', array_replace($context, ['operation' => 'apply'])));
        $this->assertFalse(SmartPixMetricSchema::validCount('smart_pix_server_error', 'true', $context));
        $this->assertFalse(SmartPixMetricSchema::validCount('smart_pix_server_effect_result', 'applied', $context));
        $this->assertFalse(SmartPixMetricSchema::validDistribution('smart_pix_server_processing_time', 0.125, $context));
        $this->assertFalse(SmartPixMetricSchema::validDistribution(SmartPixMetricSchema::CREATION_RECOVERY, 0.125, $context + ['outcome' => 'success']));
        $this->assertFalse(SmartPixMetricSchema::validCount('../SYNTHETIC_PRIVATE', 'pending', $context));
    }

    /** @dataProvider paymentRejectedProvider */
    public function testPaymentRejectedCountsTheBuyerClassWithAnAllowlistedMessage(?string $detail, string $value, string $message): void
    {
        $context = ['operation' => 'apply', 'source' => 'pending_query', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::PAYMENT_REJECTED, $value, $context, $message);
        $sink->shouldNotReceive('distribution');

        (new SmartPixTelemetry($sink))->paymentRejected($detail, 'server', 'pending_query');
    }

    public static function paymentRejectedProvider(): array
    {
        return [
            'insufficient balance' => ['insufficient_amount', 'insufficient_balance', 'insufficient_amount'],
            'limit exceeded' => ['rejected_cap_exceeded', 'limit_exceeded', 'rejected_cap_exceeded'],
            'high risk' => ['high_risk', 'rejected', 'high_risk'],
            'reason outside the allowlist' => ['rejected_by_regulations', 'rejected', 'other'],
            'private-looking reason' => ['SYNTHETIC_PRIVATE_REASON', 'rejected', 'other'],
            'missing reason' => [null, 'rejected', 'other'],
            'empty reason' => ['', 'rejected', 'other'],
        ];
    }

    public function testPaymentRejectedWithAnUnboundedContextNeverReachesTheSink(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink);
        $telemetry->paymentRejected('high_risk', 'SYNTHETIC_PRIVATE_SURFACE', 'pending_query');
        $telemetry->paymentRejected('high_risk', 'server', 'SYNTHETIC_PRIVATE_SOURCE');
        $this->assertTrue(true);
    }

    public function testPaymentRejectedSinkFailureNeverEscapes(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->andThrow(new \Error('SYNTHETIC_PRIVATE_METRIC'));
        (new SmartPixTelemetry($sink))->paymentRejected('insufficient_amount', 'server', 'notification');
        $this->assertTrue(true);
    }

    public function testSchemaBindsPaymentRejectedToApplyAndDeclaredMessages(): void
    {
        $context = ['operation' => 'apply', 'source' => 'notification', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $this->assertTrue(SmartPixMetricSchema::validCount(SmartPixMetricSchema::PAYMENT_REJECTED, 'rejected', $context));
        foreach (['create', 'get', 'validation', 'notification'] as $operation) {
            $this->assertFalse(SmartPixMetricSchema::validCount(
                SmartPixMetricSchema::PAYMENT_REJECTED,
                'rejected',
                array_replace($context, ['operation' => $operation])
            ));
        }
        $this->assertFalse(SmartPixMetricSchema::validCount(SmartPixMetricSchema::PAYMENT_REJECTED, 'high_risk', $context));
        $this->assertFalse(SmartPixMetricSchema::validCount(SmartPixMetricSchema::PAYMENT_REJECTED, 'smart_pix_rejected', $context));
        $this->assertFalse(SmartPixMetricSchema::validCount(SmartPixMetricSchema::PAYMENT_REJECTED, 'rejected', $context + ['category' => 'SYNTHETIC_PRIVATE']));

        $this->assertTrue(SmartPixMetricSchema::validMessage(SmartPixMetricSchema::PAYMENT_REJECTED, 'rejected', 'other'));
        $this->assertTrue(SmartPixMetricSchema::validMessage(SmartPixMetricSchema::PAYMENT_REJECTED, 'limit_exceeded', ''));
        $this->assertFalse(SmartPixMetricSchema::validMessage(SmartPixMetricSchema::PAYMENT_REJECTED, 'rejected', 'rejected_by_regulations'));
        $this->assertFalse(SmartPixMetricSchema::validMessage(SmartPixMetricSchema::PAYMENT_REJECTED, 'rejected', 'insufficient_amount'));
        $this->assertFalse(SmartPixMetricSchema::validMessage(SmartPixMetricSchema::PAYMENT_REJECTED, 'insufficient_balance', 'other'));
        $this->assertFalse(SmartPixMetricSchema::validMessage(SmartPixMetricSchema::PAYMENT_REJECTED, 'limit_exceeded', 'high_risk'));
    }

    public function testNormalizedFailuresNeverExposeProviderCodesOrMessages(): void
    {
        $telemetry = new SmartPixTelemetry();
        $this->assertSame('invalid_contract', $telemetry->normalizedErrorCategory([
            'outcome' => 'failure', 'state' => 'error', 'error' => ['category' => 'invalid_response', 'code' => 'SYNTHETIC_PRIVATE', 'message' => 'SYNTHETIC_PRIVATE'],
        ]));
        $this->assertSame('internal_error', $telemetry->normalizedErrorCategory(['outcome' => 'failure', 'error' => ['category' => 'SYNTHETIC_PRIVATE']]));
        $this->assertNull($telemetry->normalizedErrorCategory(['outcome' => 'failure', 'state' => 'terminal_failure']));
    }

    /** @dataProvider distributionSchemaProvider */
    public function testSchemaBindsEachDistributionToItsOperationsSourcesAndOutcomes(string $name, array $context, bool $valid): void
    {
        $base = ['surface' => 'server', 'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $this->assertSame($valid, SmartPixMetricSchema::validDistribution($name, 0.125, $context + $base));
    }

    public static function distributionSchemaProvider(): array
    {
        $time = SmartPixMetricSchema::TIME;
        $creation = SmartPixMetricSchema::ORDER_CREATION_TIME;
        $approval = SmartPixMetricSchema::PAYMENT_APPROVAL_TIME;
        $cases = [
            'processing failure' => [$time, ['operation' => 'notification', 'source' => 'notification', 'outcome' => 'failure'], true],
            'processing of a get' => [$time, ['operation' => 'get', 'source' => 'pending_query', 'outcome' => 'success'], false],
            'processing without outcome' => [$time, ['operation' => 'create', 'source' => 'sync_response'], false],
            'creation success' => [$creation, ['operation' => 'create', 'source' => 'sync_response', 'outcome' => 'success'], true],
            'creation failure' => [$creation, ['operation' => 'create', 'source' => 'sync_response', 'outcome' => 'failure'], false],
            'creation from another source' => [$creation, ['operation' => 'create', 'source' => 'internal', 'outcome' => 'success'], false],
            'creation from another operation' => [$creation, ['operation' => 'get', 'source' => 'sync_response', 'outcome' => 'success'], false],
            'approval failure' => [$approval, ['operation' => 'get', 'source' => 'pending_query', 'outcome' => 'failure'], false],
            'approval from the sync answer' => [$approval, ['operation' => 'get', 'source' => 'sync_response', 'outcome' => 'success'], false],
            'approval of a create' => [$approval, ['operation' => 'create', 'source' => 'internal', 'outcome' => 'success'], false],
            'approval without outcome' => [$approval, ['operation' => 'get', 'source' => 'internal'], false],
            'unknown distribution' => ['smart_pix_server_create_result', ['operation' => 'create', 'source' => 'sync_response', 'outcome' => 'success'], false],
        ];
        foreach (['get', 'notification'] as $operation) {
            foreach (['pending_query', 'notification', 'internal'] as $source) {
                $cases["approval $operation/$source"] = [$approval, ['operation' => $operation, 'source' => $source, 'outcome' => 'success'], true];
            }
        }
        return $cases;
    }
}
