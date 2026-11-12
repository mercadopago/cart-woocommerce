<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricPayload;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use PHPUnit\Framework\TestCase;
use Mockery;
use WC_Order;

class SmartPixMetricPayloadTest extends TestCase
{
    public function testUnknownCreationAndRecoveryNeverClaimAFinancialPaymentStatus(): void
    {
        // The plugin has no uncertain creation state: an unanswered create is a failed creation.
        $creation = \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricPayload::gateway('smart_pix_order_creation', 'timeout', '0.2s', ['surface' => 'classic']);
        $this->assertSame('order_creation_failed', $creation['details']['event']);
        $this->assertSame('timeout', $creation['details']['reason']);
        $this->assertArrayNotHasKey('payment_status', $creation['details']);
        $recovery = \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricPayload::semantic(
            \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown',
            ['site_id' => 'MLB', 'surface' => 'classic']
        );
        $this->assertSame('1', $recovery['value']);
        $this->assertSame('result_unknown', $recovery['details']['reason']);
        $this->assertArrayNotHasKey('payment_status', $recovery['details']);
    }

    public function testMutationMetricUsesSmartTransferAndCorrelatesOnlyDiagnosticIdentifiers(): void
    {
        $payload = SmartPixMetricPayload::semantic(SmartPixMetricSchema::MUTATION, 'failed', [
            'operation' => 'refund', 'surface' => 'server', 'site_id' => 'MLB', 'category' => 'conflict',
        ]);
        $this->assertSame('1', $payload['value']);
        $this->assertSame(['payment_method' => 'smart_transfer', 'site_id' => 'MLB', 'event' => 'refund_failed', 'reason' => 'conflict'], $payload['details']);
        $this->assertSame('server', $payload['message']);
    }

    /** @dataProvider observations */
    public function testFieldsHaveOneMeaning(string $name, string $value, string $message, array $expected): void
    {
        $payload = SmartPixMetricPayload::gateway($name, $value, $message, [
            'sdk_instance_id' => 'sdk-original-123', 'request_id' => 'request-current-456',
            'surface' => 'classic', 'elapsed' => '300s',
        ]);
        $this->assertSame('1', $payload['value']);
        $this->assertSame('classic', $payload['message']);
        $this->assertSame($expected + ['request_id' => 'request-current-456', 'sdk_instance_id' => 'sdk-original-123'], $payload['details']);
        foreach ($payload['details'] as $detail) {
            $this->assertIsString($detail);
        }
    }

    public static function observations(): array
    {
        $method = ['payment_method' => 'smart_transfer'];
        return [
            'creation accepted' => ['smart_pix_order_creation', 'pending', '0.432s', $method + [
                'event' => 'order_creation_succeeded', 'payment_status' => 'pending', 'request_time' => '432']],
            'creation declined' => ['smart_pix_order_creation', 'insufficient_balance', '0.125s', $method + [
                'event' => 'order_creation_rejected', 'payment_status' => 'rejected', 'reason' => 'insufficient_balance', 'request_time' => '125']],
            'technical failure has no financial state' => ['smart_pix_order_creation', 'timeout', '0.002s', $method + [
                'event' => 'order_creation_failed', 'reason' => 'timeout', 'request_time' => '2']],
            'terminal observed' => ['smart_pix_payment_status_observed', 'approved', '42s', $method + [
                'event' => 'payment_status_observed', 'payment_status' => 'approved', 'pooling_time' => '42000']],
            'missing duration is not zero' => ['smart_pix_payment_status_observed', 'cancelled', 'unknown', $method + [
                'event' => 'payment_status_observed', 'payment_status' => 'cancelled']],
            'page expiry is not cancellation' => ['smart_pix_polling_expired', 'expired', 'max_duration', $method + [
                'event' => 'polling_expired', 'reason' => 'max_duration', 'pooling_time' => '300000']],
            'rate limited query' => ['smart_pix_status_query_failed', 'get_rate_limit', 'orders_get', $method + [
                'event' => 'status_query_failed', 'reason' => 'rate_limit']],
            'query timeout' => ['smart_pix_status_query_failed', 'get_timeout', 'orders_get', $method + [
                'event' => 'status_query_failed', 'reason' => 'timeout']],
            'internal polling failure' => ['smart_pix_server_error', 'status_poll', 'exception', $method + [
                'event' => 'polling_failed', 'reason' => 'exception']],
            'access denied' => ['smart_pix_status_access', 'invalid_nonce', '', $method + [
                'event' => 'status_access_denied', 'reason' => 'invalid_nonce']],
        ];
    }

    public function testPollingFailureDiagnosticReplacesTheSurfaceMessageAfterRedaction(): void
    {
        $payload = SmartPixMetricPayload::gateway('smart_pix_server_error', 'status_poll_client', 'exception', [
            'surface' => 'classic',
            'diagnostic_message' => 'Orders read failed for buyer@example.test with Bearer APP_USR-SYNTHETIC',
        ]);

        $this->assertSame('polling_client_failed', $payload['details']['event']);
        $this->assertSame('exception', $payload['details']['reason']);
        $this->assertSame('Orders read failed for [REDACTED_EMAIL] with Bearer [REDACTED]', $payload['message']);
        $this->assertArrayNotHasKey('diagnostic_message', $payload['details']);
    }

    public function testDiagnosticMessageIsIgnoredOutsideTheServerErrorEvent(): void
    {
        $payload = SmartPixMetricPayload::gateway('smart_pix_status_access', 'invalid_nonce', '', [
            'surface' => 'blocks', 'diagnostic_message' => 'SYNTHETIC_DIAGNOSTIC',
        ]);

        $this->assertSame('blocks', $payload['message']);
    }

    /** @dataProvider observationSourceProvider */
    public function testTerminalObservationForwardsOnlyAKnownSource(string $state, string $source): void
    {
        $payload = SmartPixMetricPayload::gateway(SmartPixMetricPayload::STATUS_OBSERVED, $state, '42s', ['source' => $source]);

        $this->assertSame([
            'payment_method' => 'smart_transfer', 'event' => 'payment_status_observed', 'payment_status' => $state,
            'source' => $source, 'pooling_time' => '42000', 'missing_fields' => 'sdk_instance_id',
        ], $payload['details']);
    }

    public static function observationSourceProvider(): array
    {
        $cases = [];
        foreach (['approved', 'rejected', 'cancelled'] as $state) {
            foreach (SmartPixMetricPayload::OBSERVATION_SOURCES as $source) {
                $cases["$state from $source"] = [$state, $source];
            }
        }
        return $cases;
    }

    /** @dataProvider unknownObservationSourceProvider */
    public function testTerminalObservationDropsAnUnknownOrNonStringSource($source): void
    {
        $payload = SmartPixMetricPayload::gateway(SmartPixMetricPayload::STATUS_OBSERVED, 'approved', '42s', ['source' => $source]);

        $this->assertArrayNotHasKey('source', $payload['details']);
        $this->assertStringNotContainsString('SYNTHETIC', (string) json_encode($payload));
    }

    public static function unknownObservationSourceProvider(): array
    {
        return [
            'internal schema source' => ['pending_query'],
            'empty' => [''],
            'different case' => ['Poll'],
            'unbounded' => ['SYNTHETIC_PRIVATE_SOURCE'],
            'non-string' => [['poll']],
            'null' => [null],
        ];
    }

    /** @dataProvider sourcelessEventProvider */
    public function testOnlyTheTerminalObservationCarriesASource(string $name, string $value, string $message): void
    {
        $payload = SmartPixMetricPayload::gateway($name, $value, $message, ['source' => 'poll', 'elapsed' => '300s']);

        $this->assertArrayNotHasKey('source', $payload['details']);
        // A non-terminal status is not an observation at all, whatever its source.
        $this->assertNull(SmartPixMetricPayload::gateway(SmartPixMetricPayload::STATUS_OBSERVED, 'pending', '42s', ['source' => 'poll']));
    }

    public static function sourcelessEventProvider(): array
    {
        return [
            'creation' => ['smart_pix_order_creation', 'pending', '0.432s'],
            'polling expired' => [SmartPixMetricPayload::POLLING_EXPIRED, 'expired', 'max_duration'],
            'query failed' => [SmartPixMetricPayload::QUERY_FAILED, 'get_timeout', 'orders_get'],
            'access denied' => ['smart_pix_status_access', 'invalid_nonce', ''],
        ];
    }

    public function testDedicatedDistributionsUseTheirOwnSemanticEvents(): void
    {
        $context = ['site_id' => 'MLB', 'surface' => 'blocks', 'environment' => 'prod'];
        $creation = SmartPixMetricPayload::semantic(SmartPixMetricSchema::ORDER_CREATION_TIME, '', $context);
        $this->assertSame('blocks', $creation['message']);
        $this->assertSame(['payment_method' => 'smart_transfer', 'site_id' => 'MLB',
            'event' => 'order_creation_succeeded', 'environment' => 'prod'], $creation['details']);

        $approval = SmartPixMetricPayload::semantic(SmartPixMetricSchema::PAYMENT_APPROVAL_TIME, '', ['surface' => 'server'] + $context);
        $this->assertSame(['payment_method' => 'smart_transfer', 'site_id' => 'MLB', 'event' => 'payment_status_observed',
            'payment_status' => 'approved', 'environment' => 'prod'], $approval['details']);
    }

    public function testActualImmediateApprovalOverridesTheLegacyPendingCreationLabel(): void
    {
        $payload = SmartPixMetricPayload::gateway('smart_pix_order_creation', 'pending', '0.1s', ['payment_status' => 'approved']);
        $this->assertSame('approved', $payload['details']['payment_status']);
        $this->assertSame('sdk_instance_id', $payload['details']['missing_fields']);
        $this->assertNull(SmartPixMetricPayload::gateway('smart_pix_pending_status', 'pending', 'unknown'));
    }

    public function testRejectionHasFinancialDetailsInsteadOfGenericValueAndMessage(): void
    {
        $payload = SmartPixMetricPayload::semantic(SmartPixMetricSchema::PAYMENT_REJECTED, 'limit_exceeded', [
            'operation' => 'apply', 'surface' => 'server', 'source' => 'notification', 'site_id' => 'MLB',
        ], 'rejected_cap_exceeded');
        $this->assertSame('1', $payload['value']);
        $this->assertSame('server', $payload['message']);
        $this->assertSame('rejected', $payload['details']['payment_status']);
        $this->assertSame('rejected_cap_exceeded', $payload['details']['payment_status_detail']);
        $this->assertSame('limit_exceeded', $payload['details']['reason']);
    }

    /** @dataProvider creationDurations */
    public function testCreationDurationIsPreservedWithoutBuckets(string $duration): void
    {
        $payload = SmartPixMetricPayload::gateway('smart_pix_order_creation', 'pending', $duration, ['surface' => 'classic']);
        $this->assertArrayNotHasKey('duration_bucket', $payload['details']);
        $this->assertSame((string) (int) round((float) $duration * 1000), $payload['details']['request_time']);
    }

    public static function creationDurations(): array
    {
        return [
            ['0.999s'], ['1.000s'], ['2.500s'], ['3.000s'],
            ['9.999s'], ['10.000s'], ['45.000s'],
        ];
    }

    /** @dataProvider terminalDurations */
    public function testApprovalWaitIsPreservedWithoutBuckets(string $duration): void
    {
        $payload = SmartPixMetricPayload::gateway('smart_pix_payment_status_observed', 'approved', $duration, ['source' => 'poll']);
        $this->assertArrayNotHasKey('duration_bucket', $payload['details']);
        $this->assertSame((string) (int) round((float) $duration * 1000), $payload['details']['pooling_time']);
    }

    public static function terminalDurations(): array
    {
        return [
            ['0s'], ['29s'], ['30s'], ['60s'], ['120s'],
            ['299s'], ['300s'], ['900s'], ['7200s'],
        ];
    }

    public function testUnknownDurationHasNoBucketAndOtherEventsAreNotBucketed(): void
    {
        $observed = SmartPixMetricPayload::gateway('smart_pix_payment_status_observed', 'approved', 'unknown', []);
        $this->assertArrayNotHasKey('duration_bucket', $observed['details']);
        $expired = SmartPixMetricPayload::gateway('smart_pix_polling_expired', 'expired', '', ['elapsed' => '300s']);
        $this->assertArrayNotHasKey('duration_bucket', $expired['details']);
    }
}
