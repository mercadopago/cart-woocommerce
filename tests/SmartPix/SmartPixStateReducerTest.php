<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use PHPUnit\Framework\TestCase;

class SmartPixStateReducerTest extends TestCase
{
    public function testNormalizedPpApprovalChecksLocalAmountAndPreservesPaidReplay(): void
    {
        $response = (new SmartPixOrderNormalizer())->normalize([
            'id' => 'ORDER_SYNTHETIC_001', 'external_reference' => 'WC-321',
            'type' => 'online', 'site_id' => 'MLB', 'currency' => 'BRL',
            'status' => 'processed', 'status_detail' => 'accredited',
            'total_amount' => '10', 'total_paid_amount' => '10',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC_001', 'status' => 'processed', 'status_detail' => 'accredited',
                'amount' => '10', 'paid_amount' => '10',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]],
        ], 'ORDER_SYNTHETIC_001', null, 'WC-321');
        $this->assertSame('terminal_success', $response['state']);
        $reducer = new SmartPixStateReducer();
        $amount = ['value' => '10.00', 'currency' => 'BRL'];
        $this->assertSame(['complete_payment'], $reducer->reduce('pending', false, '0.00', $response, $amount)['effects']);
        foreach (['processing', 'completed'] as $status) {
            $decision = $reducer->reduce($status, true, '0.00', $response, $amount);
            $this->assertSame('approval_already_applied', $decision['reason']);
            $this->assertSame([], $decision['effects']);
        }
        $decision = $reducer->reduce('pending', false, '0.00', $response, ['value' => '11.00', 'currency' => 'BRL']);
        $this->assertSame('amount_mismatch', $decision['reason']);
        $this->assertSame([], $decision['effects']);
        foreach (['failed', 'cancelled', 'refunded'] as $status) {
            $this->assertSame([], $reducer->reduce($status, false, '0.00', $response, $amount)['effects']);
        }
    }

    public function testSyntheticApprovalProposesNativeCompletionWithoutForcingStatus(): void
    {
        $response = $this->response('orders-query-terminal-success');
        $original = $response;

        $this->assertSame([
            'decision' => 'propose_effects',
            'reason' => 'payment_approved',
            'previous_status' => 'pending',
            'next_status' => null,
            'effects' => ['complete_payment'],
        ], (new SmartPixStateReducer())->reduce('pending', false, '0.00', $response, ['value' => '125.50', 'currency' => 'BRL']));
        $this->assertSame($original, $response);
    }

    public function testRecognizedApprovalWithDifferentAmountReportsConflictWithoutEffects(): void
    {
        $response = $this->response('orders-query-terminal-success');
        $response['payments'][0]['amount']['value'] = '125.51';
        $result = (new SmartPixStateReducer())->reduce('pending', false, '0.00', $response, $this->amount());

        $this->assertSame('conflict', $result['decision']);
        $this->assertSame('amount_mismatch', $result['reason']);
        $this->assertSame('pending', $result['next_status']);
        $this->assertSame([], $result['effects']);
    }

    public function testPendingOrderReceivingNormalizedPendingResponseProposesNoEffects(): void
    {
        $data = $this->rawPendingResponse();
        $response = (new SmartPixOrderNormalizer())->normalize($data, 'ORDER_SYNTHETIC_001', ['value' => '4.00', 'currency' => 'BRL']);
        $original = $response;

        $this->assertSame('success', $response['outcome']);
        $this->assertSame([
            'decision' => 'no_change',
            'reason' => 'pending_preserved',
            'previous_status' => 'pending',
            'next_status' => 'pending',
            'effects' => [],
        ], (new SmartPixStateReducer())->reduce('pending', false, '0.00', $response));
        $this->assertSame($original, $response);
    }

    /**
     * @dataProvider unsupportedContextProvider
     * @param array<string, mixed> $response
     */
    public function testUnsupportedInputsPreserveStatusAndProposeNoEffects(
        string $status,
        bool $isPaid,
        string $refundedAmount,
        array $response
    ): void {
        $this->assertSame([
            'decision' => 'unsupported',
            'reason' => 'unsupported_transition',
            'previous_status' => $status,
            'next_status' => $status,
            'effects' => [],
        ], (new SmartPixStateReducer())->reduce($status, $isPaid, $refundedAmount, $response));
    }

    public static function unsupportedContextProvider(): array
    {
        $pending = ['outcome' => 'success', 'state' => 'processing', 'error' => null];

        return [
            'paid facts despite pending status' => ['pending', true, '0.00', $pending],
            'existing refund despite pending status' => ['pending', false, '1.00', $pending],
            'custom status' => ['custom-review', false, '0.00', $pending],
            'paid facts despite on hold status' => ['on-hold', true, '0.00', $pending],
            'query failure is not financial rejection' => [
                'pending', false, '0.00', ['outcome' => 'failure', 'state' => 'error', 'error' => ['category' => 'timeout']],
            ],
            'terminal mapping remains unsupported' => [
                'pending', false, '0.00', ['outcome' => 'success', 'state' => 'terminal_success', 'error' => null],
            ],
            'missing error field' => ['pending', false, '0.00', ['outcome' => 'success', 'state' => 'processing']],
        ];
    }

    /** @dataProvider syntheticTransitionProvider */
    public function testSyntheticTerminalDecisions(
        string $fixture,
        string $status,
        string $reason,
        ?string $nextStatus,
        string $effect
    ): void {
        $response = $this->response($fixture);
        $this->assertSame([
            'decision' => 'propose_effects',
            'reason' => $reason,
            'previous_status' => $status,
            'next_status' => $nextStatus,
            'effects' => [$effect],
        ], (new SmartPixStateReducer())->reduce($status, false, '0.00', $response, $this->amount()));
    }

    public static function syntheticTransitionProvider(): array
    {
        return [
            'approval from on hold' => ['orders-query-terminal-success', 'on-hold', 'payment_approved', null, 'complete_payment'],
            'rejection from pending' => ['orders-query-terminal-failure', 'pending', 'payment_rejected', 'failed', 'mark_failed'],
            'rejection from on hold' => ['orders-query-terminal-failure', 'on-hold', 'payment_rejected', 'failed', 'mark_failed'],
            'cancellation from pending' => ['orders-cancel-terminal-success', 'pending', 'payment_cancelled', 'cancelled', 'mark_cancelled'],
            'cancellation from on hold' => ['orders-cancel-terminal-success', 'on-hold', 'payment_cancelled', 'cancelled', 'mark_cancelled'],
        ];
    }

    /** @dataProvider replayAndConflictProvider */
    public function testReplayAndConflictingTerminalEvidenceNeverProposeEffects(
        string $fixture,
        string $status,
        bool $isPaid,
        string $refundedAmount,
        string $decision,
        string $reason
    ): void {
        $this->assertSame([
            'decision' => $decision,
            'reason' => $reason,
            'previous_status' => $status,
            'next_status' => $status,
            'effects' => [],
        ], (new SmartPixStateReducer())->reduce($status, $isPaid, $refundedAmount, $this->response($fixture), $this->amount()));
    }

    public static function replayAndConflictProvider(): array
    {
        return [
            'approval replay on processing' => ['orders-query-terminal-success', 'processing', true, '0.00', 'no_change', 'approval_already_applied'],
            'approval replay on completed' => ['orders-query-terminal-success', 'completed', true, '0.00', 'no_change', 'approval_already_applied'],
            'rejection replay' => ['orders-query-terminal-failure', 'failed', false, '0.00', 'no_change', 'terminal_already_applied'],
            'cancellation replay' => ['orders-cancel-terminal-success', 'cancelled', false, '0.00', 'no_change', 'terminal_already_applied'],
            'approval after cancellation' => ['orders-query-terminal-success', 'cancelled', false, '0.00', 'conflict', 'terminal_state_conflict'],
            'approval after rejection' => ['orders-query-terminal-success', 'failed', false, '0.00', 'conflict', 'terminal_state_conflict'],
            'rejection after payment' => ['orders-query-terminal-failure', 'processing', true, '0.00', 'conflict', 'paid_state_conflict'],
            'cancellation after payment' => ['orders-cancel-terminal-success', 'completed', true, '0.00', 'conflict', 'paid_state_conflict'],
            'approval after partial refund' => ['orders-query-terminal-success', 'processing', true, '25.50', 'conflict', 'refund_context_conflict'],
            'approval after full refund' => ['orders-query-terminal-success', 'refunded', false, '125.50', 'conflict', 'refund_context_conflict'],
            'paid fact contradicts pending status' => ['orders-query-terminal-success', 'pending', true, '0.00', 'conflict', 'local_state_conflict'],
            'unpaid fact contradicts fulfillment' => ['orders-query-terminal-success', 'completed', false, '0.00', 'conflict', 'local_state_conflict'],
            'approval replay on store paid status' => ['orders-query-terminal-success', 'paid-by-store', true, '0.00', 'no_change', 'approval_already_applied'],
            'rejection after store paid status' => ['orders-query-terminal-failure', 'paid-by-store', true, '0.00', 'conflict', 'paid_state_conflict'],
            'cancellation after store paid status' => ['orders-cancel-terminal-success', 'paid-by-store', true, '0.00', 'conflict', 'paid_state_conflict'],
            'approval after partial refund on store paid status' => ['orders-query-terminal-success', 'paid-by-store', true, '25.50', 'conflict', 'refund_context_conflict'],
            'paid fact contradicts on hold status' => ['orders-query-terminal-success', 'on-hold', true, '0.00', 'conflict', 'local_state_conflict'],
        ];
    }

    /** @dataProvider pendingPreservationProvider */
    public function testPendingResponsePreservesProgression(string $status, bool $isPaid, string $reason): void
    {
        $raw = $this->rawPendingResponse();
        $response = (new SmartPixOrderNormalizer())->normalize($raw);
        $this->assertSame([
            'decision' => 'no_change',
            'reason' => $reason,
            'previous_status' => $status,
            'next_status' => $status,
            'effects' => [],
        ], (new SmartPixStateReducer())->reduce($status, $isPaid, '0.00', $response));
    }

    public static function pendingPreservationProvider(): array
    {
        return [
            'pending on hold' => ['on-hold', false, 'pending_preserved'],
            'pending after paid processing' => ['processing', true, 'paid_state_preserved'],
            'pending after completion' => ['completed', true, 'paid_state_preserved'],
            'pending after store paid status' => ['paid-by-store', true, 'paid_state_preserved'],
            'pending after rejection' => ['failed', false, 'terminal_state_preserved'],
            'pending after cancellation' => ['cancelled', false, 'terminal_state_preserved'],
        ];
    }

    /**
     * @dataProvider monetaryGuardProvider
     * @param array<string, mixed>|null $expectedAmount
     * @param array<string, mixed> $changes
     */
    public function testSyntheticApprovalRequiresExactSinglePaymentTotal(?array $expectedAmount, array $changes): void
    {
        $response = array_replace_recursive($this->response('orders-query-terminal-success'), $changes);
        $result = (new SmartPixStateReducer())->reduce('pending', false, '0.00', $response, $expectedAmount);
        $this->assertSame('unsupported', $result['decision']);
        $this->assertSame('pending', $result['next_status']);
        $this->assertSame([], $result['effects']);
    }

    public static function monetaryGuardProvider(): array
    {
        $total = ['value' => '125.50', 'currency' => 'BRL'];
        return [
            'missing server amount' => [null, []],
            'wrong server currency' => [['value' => '125.50', 'currency' => 'USD'], []],
            'numeric server amount' => [['value' => 125.50, 'currency' => 'BRL'], []],
            'malformed payment amount' => [$total, ['payments' => [['amount' => ['value' => '125.500']]]]],
            'numeric payment amount' => [$total, ['payments' => [['amount' => ['value' => 125.50]]]]],
            'malformed payment currency' => [$total, ['payments' => [['amount' => ['currency' => ['BRL']]]]]],
            'subcent server amount' => [['value' => '125.501', 'currency' => 'BRL'], []],
            'terminal order with pending payment' => [$total, ['payments' => [['state' => 'processing']]]],
            'approval with failure outcome' => [$total, ['outcome' => 'failure']],
        ];
    }

    /** @dataProvider monetaryConflictProvider */
    public function testRecognizedTerminalMoneyConflictsPreserveLocalState(string $fixture, string $value, string $currency, string $reason): void
    {
        $response = $this->response($fixture);
        $response['payments'][0]['amount'] = ['value' => $value, 'currency' => $currency];
        $result = (new SmartPixStateReducer())->reduce('pending', false, '0.00', $response, $this->amount());
        $this->assertSame('conflict', $result['decision']);
        $this->assertSame($reason, $result['reason']);
        $this->assertSame('pending', $result['next_status']);
        $this->assertSame([], $result['effects']);
    }

    public static function monetaryConflictProvider(): array
    {
        return [
            'underpayment' => ['orders-query-terminal-success', '0.01', 'BRL', 'amount_mismatch'],
            'overpayment' => ['orders-query-terminal-success', '125.51', 'BRL', 'amount_mismatch'],
            'currency conflict' => ['orders-query-terminal-success', '125.50', 'USD', 'currency_mismatch'],
            'rejected money conflict' => ['orders-query-terminal-failure', '125.51', 'BRL', 'amount_mismatch'],
            'cancelled money conflict' => ['orders-cancel-terminal-success', '125.51', 'BRL', 'amount_mismatch'],
        ];
    }

    public function testCurrentAuthoritativeAmountMismatchPreservesTheOrder(): void
    {
        $result = (new SmartPixStateReducer())->reduce('pending', false, '0.00', $this->response('orders-query-terminal-success'), ['value' => '130.00', 'currency' => 'BRL']);
        $this->assertSame('amount_mismatch', $result['reason']);
        $this->assertSame([], $result['effects']);
    }

    public function testNoncanonicalRefundZeroDoesNotAuthorizeCompletion(): void
    {
        $result = (new SmartPixStateReducer())->reduce('pending', false, '0', $this->response('orders-query-terminal-success'), $this->amount());
        $this->assertSame('refund_context_conflict', $result['reason']);
        $this->assertSame([], $result['effects']);
    }

    public function testMultipleMovementsAndRefundResponseRemainUnsupported(): void
    {
        $response = $this->response('orders-query-terminal-success');
        $second = $response['payments'][0];
        $second['reference'] = 'SECOND_SYNTHETIC_PAYMENT';
        $response['payments'][0]['amount']['value'] = '100.00';
        $second['amount']['value'] = '25.50';
        $response['payments'][] = $second;

        foreach ([$response, array_replace($response, ['payments' => array_reverse($response['payments'])]), $this->response('orders-refund-terminal-success')] as $input) {
            $result = (new SmartPixStateReducer())->reduce('pending', false, '0.00', $input, $this->amount());
            $this->assertSame('unsupported', $result['decision']);
            $this->assertSame([], $result['effects']);
        }
    }

    public function testProductionNormalizerStillRejectsUnevidencedTerminalResponse(): void
    {
        $raw = $this->rawPendingResponse();
        $raw['status'] = 'processed';
        $raw['transactions']['payments'][0]['status'] = 'approved';
        $response = (new SmartPixOrderNormalizer())->normalize($raw);

        $this->assertSame('failure', $response['outcome']);
        $this->assertSame('unknown', $response['error']['category']);
        $decision = (new SmartPixStateReducer())->reduce('pending', false, '0.00', $response, ['value' => '4.00', 'currency' => 'BRL']);
        $this->assertSame('unsupported', $decision['decision']);
        $this->assertSame('pending', $decision['next_status']);
        $this->assertSame([], $decision['effects']);
    }

    /**
     * Minimal pending input with synthetic references and exact decimal amounts.
     * @return array<string, mixed>
     */
    private function rawPendingResponse(): array
    {
        return [
            'id' => 'ORDER_SYNTHETIC_001',
            'external_reference' => 'ATTEMPT_SYNTHETIC_001',
            'type' => 'online',
            'country_code' => 'BRA',
            'currency' => 'BRL',
            'total_amount' => '4.00',
            'total_paid_amount' => '0.00',
            'status' => 'processing',
            'status_detail' => 'in_process',
            'transactions' => [
                'payments' => [[
                    'id' => 'PAYMENT_SYNTHETIC_001',
                    'amount' => '4.00',
                    'status' => 'processing',
                    'status_detail' => 'in_process',
                    'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
                ]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function response(string $scenario): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/orders/' . $scenario . '.json'), true, 512, JSON_THROW_ON_ERROR);
        return $fixture['response'];
    }

    /** @return array<string, string> */
    private function amount(): array
    {
        return ['value' => '125.50', 'currency' => 'BRL'];
    }
}
