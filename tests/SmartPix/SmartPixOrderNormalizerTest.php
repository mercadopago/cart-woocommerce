<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixBuyerError;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer;
use PHPUnit\Framework\TestCase;

class SmartPixOrderNormalizerTest extends TestCase
{
    public function testAccreditedPpOrderQueryNormalizesExactPaidAmounts(): void
    {
        $data = [
            'id' => 'ORDER_SYNTHETIC_001', 'type' => 'online',
            'external_reference' => 'WC-321', 'site_id' => 'MLB', 'currency' => 'BRL',
            'total_amount' => '10', 'total_paid_amount' => '10',
            'status' => 'processed', 'status_detail' => 'accredited',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC_001', 'status' => 'processed', 'status_detail' => 'accredited',
                'amount' => '10', 'paid_amount' => '10',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
                'references' => ['source' => 'mp_order', 'payment_id' => 'SYNTHETIC_PAYMENT_ID'],
                'processed_with_super_token' => true,
            ]],
        ];
        $result = (new SmartPixOrderNormalizer())->normalize(
            $data,
            'ORDER_SYNTHETIC_001',
            ['value' => '10.00', 'currency' => 'BRL'],
            'WC-321'
        );
        $this->assertSame([
            'outcome' => 'success', 'state' => 'terminal_success',
            'order_reference' => 'ORDER_SYNTHETIC_001', 'eligibility' => null, 'authorization' => null,
            'payments' => [[
                'reference' => 'PAYMENT_SYNTHETIC_001', 'state' => 'approved',
                'amount' => ['value' => '10.00', 'currency' => 'BRL'],
            ]], 'error' => null,
            'observed' => [
                'order_status' => 'processed', 'order_status_detail' => 'accredited',
                'payment_status' => 'processed', 'payment_status_detail' => 'accredited', 'total_failed' => null,
                'payment_id' => 'PAYMENT_SYNTHETIC_001', 'payment_reference' => null,
            ],
        ], $result);
    }

    public function testRawPendingResponsePreservesPaymentAndNeverApproves(): void
    {
        $data = $this->fixture();
        $data['transactions']['payments'][0]['payment_method']['token'] = 'SYNTHETIC_AUTHORIZATION_NOT_VALID';
        $result = (new SmartPixOrderNormalizer())->normalize($data, null, ['value' => '4.00', 'currency' => 'BRL']);

        $this->assertSame([
            'outcome' => 'success',
            'state' => 'processing',
            'order_reference' => 'ORDER_SYNTHETIC_001',
            'eligibility' => null,
            'authorization' => null,
            'payments' => [[
                'reference' => 'PAYMENT_SYNTHETIC_001',
                'state' => 'processing',
                'amount' => ['value' => '4.00', 'currency' => 'BRL'],
            ]],
            'error' => null,
        ], $result);
        $this->assertStringNotContainsString('SYNTHETIC_AUTHORIZATION_NOT_VALID', json_encode($result));
    }

    public function testPpPendingAmountDoesNotImplyAnAccreditedPayment(): void
    {
        $data = $this->ppFixture();
        $data['status'] = $data['payments'][0]['status'] = 'action_required';
        $data['status_detail'] = $data['payments'][0]['status_detail'] = 'waiting_transfer';
        $data['payments'][0]['paid_amount'] = '0';
        foreach (['0', '10'] as $reportedPaid) {
            $data['total_paid_amount'] = $reportedPaid;
            $result = (new SmartPixOrderNormalizer())->normalize($data);
            $this->assertSame('success', $result['outcome']);
            $this->assertSame('processing', $result['state']);
            $this->assertSame('processing', $result['payments'][0]['state']);
        }
    }

    public function testFailedPpOrderNormalizesToTerminalRejectionWithItsReason(): void
    {
        $data = $this->ppFixture();
        $data['total_paid_amount'] = '0';
        $data['total_failed_amount'] = '10';
        $data['status'] = $data['payments'][0]['status'] = 'failed';
        $data['status_detail'] = $data['payments'][0]['status_detail'] = 'rejected_cap_exceeded';
        unset($data['payments'][0]['paid_amount']);

        $result = (new SmartPixOrderNormalizer())->normalize(
            $data,
            'ORDER_SYNTHETIC_001',
            ['value' => '10.00', 'currency' => 'BRL'],
            'WC-321'
        );

        $this->assertSame([
            'outcome' => 'failure', 'state' => 'terminal_failure',
            'order_reference' => 'ORDER_SYNTHETIC_001', 'eligibility' => null, 'authorization' => null,
            'payments' => [[
                'reference' => 'PAYMENT_SYNTHETIC_001', 'state' => 'rejected',
                'amount' => ['value' => '10.00', 'currency' => 'BRL'],
            ]],
            'error' => ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_REJECTED_CAP_EXCEEDED', 'retryable' => false],
            'observed' => [
                'order_status' => 'failed', 'order_status_detail' => 'rejected_cap_exceeded',
                'payment_status' => 'failed', 'payment_status_detail' => 'rejected_cap_exceeded', 'total_failed' => '10.00',
                'payment_id' => 'PAYMENT_SYNTHETIC_001', 'payment_reference' => null,
            ],
        ], $result);
        $detail = SmartPixOrderNormalizer::rejectionStatusDetail($result['error']);
        $this->assertSame('rejected_cap_exceeded', $detail);
        $this->assertSame(SmartPixBuyerError::LIMIT_EXCEEDED, SmartPixBuyerError::fromRejectionStatusDetail($detail));
    }

    public function testProcessingPpOrderNormalizesAsNonTerminalWithoutError(): void
    {
        $data = $this->ppFixture();
        $data['total_paid_amount'] = $data['payments'][0]['paid_amount'] = '0';
        $data['status'] = $data['payments'][0]['status'] = 'processing';
        $data['status_detail'] = $data['payments'][0]['status_detail'] = 'in_process';

        $result = (new SmartPixOrderNormalizer())->normalize(
            $data,
            'ORDER_SYNTHETIC_001',
            ['value' => '10.00', 'currency' => 'BRL'],
            'WC-321'
        );

        $this->assertSame([
            'outcome' => 'success', 'state' => 'processing',
            'order_reference' => 'ORDER_SYNTHETIC_001', 'eligibility' => null, 'authorization' => null,
            'payments' => [[
                'reference' => 'PAYMENT_SYNTHETIC_001', 'state' => 'processing',
                'amount' => ['value' => '10.00', 'currency' => 'BRL'],
            ]],
            'error' => null,
            'observed' => [
                'order_status' => 'processing', 'order_status_detail' => 'in_process',
                'payment_status' => 'processing', 'payment_status_detail' => 'in_process', 'total_failed' => null,
                'payment_id' => 'PAYMENT_SYNTHETIC_001', 'payment_reference' => null,
            ],
        ], $result);
    }

    /** Shape observed in a Core P&P notification envelope; still to be confirmed against GET /v1/orders. */
    public function testProcessingWaitingTransferPpOrderNormalizesAsNonTerminalWithThePaymentStatus(): void
    {
        $data = $this->ppFixture();
        $data['total_paid_amount'] = $data['payments'][0]['paid_amount'] = '0';
        $data['status'] = 'processing';
        $data['payments'][0]['status'] = 'action_required';
        $data['status_detail'] = $data['payments'][0]['status_detail'] = 'waiting_transfer';

        $result = (new SmartPixOrderNormalizer())->normalize(
            $data,
            'ORDER_SYNTHETIC_001',
            ['value' => '10.00', 'currency' => 'BRL'],
            'WC-321'
        );

        $this->assertSame([
            'outcome' => 'success', 'state' => 'processing',
            'order_reference' => 'ORDER_SYNTHETIC_001', 'eligibility' => null, 'authorization' => null,
            'payments' => [[
                'reference' => 'PAYMENT_SYNTHETIC_001', 'state' => 'processing',
                'amount' => ['value' => '10.00', 'currency' => 'BRL'],
            ]],
            'error' => null,
            'observed' => [
                'order_status' => 'processing', 'order_status_detail' => 'waiting_transfer',
                'payment_status' => 'action_required', 'payment_status_detail' => 'waiting_transfer', 'total_failed' => null,
                'payment_id' => 'PAYMENT_SYNTHETIC_001', 'payment_reference' => null,
            ],
        ], $result);
    }

    /** @dataProvider unrecognizedRejectionDetailProvider */
    public function testFailedPpOrderWithAnUnboundedReasonIsAnUnknownState($detail): void
    {
        $data = $this->failedPpFixture();
        $data['status_detail'] = $data['payments'][0]['status_detail'] = $detail;

        $this->assertFailure('unknown', $this->normalizeFailed($data));
    }

    public static function unrecognizedRejectionDetailProvider(): array
    {
        return [
            'empty' => [''],
            'missing' => [null],
            'too long' => [str_repeat('a', 65)],
            'uppercase' => ['HIGH_RISK'],
            'mixed case' => ['High_risk'],
            'hyphen' => ['high-risk'],
            'space' => ['high risk'],
            'trailing newline' => ["high_risk\n"],
            'numeric' => [42],
        ];
    }

    public function testFailedPpOrderAcceptsTheLongestBoundedReason(): void
    {
        $detail = str_repeat('a', 64);
        $data = $this->failedPpFixture();
        $data['status_detail'] = $data['payments'][0]['status_detail'] = $detail;

        $result = $this->normalizeFailed($data);

        $this->assertSame('terminal_failure', $result['state']);
        $this->assertSame('SMART_PIX_REJECTED_' . strtoupper($detail), $result['error']['code']);
        $this->assertSame($detail, SmartPixOrderNormalizer::rejectionStatusDetail($result['error']));
    }

    /**
     * @dataProvider invalidFailedPpResponseProvider
     * @param callable(array<string, mixed>): array<string, mixed> $change
     */
    public function testFailedPpOrderRejectsContradictoryFinancialOrStructuralFacts(callable $change, string $category): void
    {
        $this->assertFailure($category, $this->normalizeFailed($change($this->failedPpFixture())));
    }

    public static function invalidFailedPpResponseProvider(): array
    {
        $set = static function (array $path, $value): callable {
            return static function (array $data) use ($path, $value): array {
                $target = &$data;
                foreach ($path as $key) {
                    $target = &$target[$key];
                }
                $target = $value;
                return $data;
            };
        };
        return [
            'paid order total' => [$set(['total_paid_amount'], '10'), 'invalid_response'],
            'partially paid order total' => [$set(['total_paid_amount'], '0.01'), 'invalid_response'],
            'no payments' => [$set(['payments'], []), 'invalid_response'],
            'two payments' => [static function (array $data): array {
                $second = $data['payments'][0];
                $second['id'] = 'PAYMENT_SYNTHETIC_002';
                $data['payments'][0]['amount'] = '6';
                $second['amount'] = '4';
                $data['payments'][] = $second;
                return $data;
            }, 'invalid_response'],
            'payment status differs' => [$set(['payments', 0, 'status'], 'processing'), 'unknown'],
            'payment detail differs' => [$set(['payments', 0, 'status_detail'], 'insufficient_amount'), 'unknown'],
            'payment below total' => [$set(['payments', 0, 'amount'], '9.99'), 'invalid_response'],
            'payment above total' => [$set(['payments', 0, 'amount'], '10.01'), 'invalid_response'],
            'paid payment' => [$set(['payments', 0, 'paid_amount'], '10'), 'invalid_response'],
            'partially paid payment' => [$set(['payments', 0, 'paid_amount'], '0.01'), 'invalid_response'],
            'null paid amount' => [$set(['payments', 0, 'paid_amount'], null), 'invalid_response'],
            'float payment amount' => [$set(['payments', 0, 'amount'], 10.0), 'invalid_response'],
            'subcent payment amount' => [$set(['payments', 0, 'amount'], '10.001'), 'invalid_response'],
            'float total' => [$set(['total_amount'], 10.0), 'invalid_response'],
            'subcent total' => [$set(['total_amount'], '10.001'), 'invalid_response'],
            'wrong currency' => [$set(['currency'], 'USD'), 'invalid_response'],
            'wrong site' => [$set(['site_id'], 'MLA'), 'invalid_response'],
            'wrong country' => [$set(['country_code'], 'ARG'), 'invalid_response'],
            'wrong type' => [$set(['type'], 'offline'), 'invalid_response'],
            'wrong method id' => [$set(['payments', 0, 'payment_method', 'id'], 'pix'), 'invalid_response'],
            'wrong method type' => [$set(['payments', 0, 'payment_method', 'type'], 'bank_transfer'), 'invalid_response'],
            'mixed envelopes' => [$set(['transactions'], ['payments' => []]), 'invalid_response'],
            'refunds present' => [$set(['payments', 0, 'refunds'], [['amount' => '10', 'status' => 'processed']]), 'unknown'],
            'null refunds' => [$set(['payments', 0, 'refunds'], null), 'invalid_response'],
            'associative refunds' => [$set(['payments', 0, 'refunds'], ['first' => ['amount' => '10']]), 'invalid_response'],
            'malformed failed total' => [$set(['total_failed_amount'], 'ten'), 'invalid_response'],
            'null failed total' => [$set(['total_failed_amount'], null), 'invalid_response'],
            'float failed total' => [$set(['total_failed_amount'], 10.0), 'invalid_response'],
            'subcent failed total' => [$set(['total_failed_amount'], '10.001'), 'invalid_response'],
            'failed total below order total' => [$set(['total_failed_amount'], '9.99'), 'invalid_response'],
            'failed total above order total' => [$set(['total_failed_amount'], '10.01'), 'invalid_response'],
            'zero failed total' => [$set(['total_failed_amount'], '0'), 'invalid_response'],
        ];
    }

    public function testFailedPpOrderRequiresTheServerOwnedAmount(): void
    {
        $normalizer = new SmartPixOrderNormalizer();
        $data = $this->failedPpFixture();
        foreach ([['value' => '9.99', 'currency' => 'BRL'], ['value' => '10.00', 'currency' => 'USD'], ['value' => 10.0, 'currency' => 'BRL']] as $amount) {
            $this->assertFailure('invalid_response', $normalizer->normalize($data, 'ORDER_SYNTHETIC_001', $amount, 'WC-321'));
        }
    }

    public function testFailedPpOrderForAnotherOrderOrAttemptIsALinkageMismatch(): void
    {
        $normalizer = new SmartPixOrderNormalizer();
        $data = $this->failedPpFixture();
        $amount = ['value' => '10.00', 'currency' => 'BRL'];

        $this->assertFailure('linkage_mismatch', $normalizer->normalize($data, 'OTHER_ORDER', $amount, 'WC-321'));
        $this->assertFailure('linkage_mismatch', $normalizer->normalize($data, 'ORDER_SYNTHETIC_001', $amount, 'WC-322'));
        $data['external_reference'] = null;
        $this->assertFailure('linkage_mismatch', $normalizer->normalize($data, 'ORDER_SYNTHETIC_001', $amount));
    }

    public function testFailedPpOrderWithoutAFailedTotalNormalizesWithANullObservation(): void
    {
        $data = $this->failedPpFixture();
        unset($data['total_failed_amount']);

        $result = $this->normalizeFailed($data);

        $this->assertSame('terminal_failure', $result['state']);
        $this->assertSame('rejected', $result['payments'][0]['state']);
        $this->assertNull($result['observed']['total_failed']);
    }

    public function testFailedPpOrderAcceptsAnExplicitZeroPaidPaymentAndEquivalentFailedTotal(): void
    {
        $data = $this->failedPpFixture();
        $data['payments'][0]['paid_amount'] = '0.00';
        $data['total_failed_amount'] = '10.000';

        $result = $this->normalizeFailed($data);

        $this->assertSame('terminal_failure', $result['state']);
        $this->assertSame('10.00', $result['observed']['total_failed']);
    }

    /** @dataProvider rejectionReasonProvider */
    public function testFailedPpOrderReasonMapsToTheBuyerRejectionCode(string $detail, string $code, string $buyerCode): void
    {
        $data = $this->failedPpFixture();
        $data['status_detail'] = $data['payments'][0]['status_detail'] = $detail;

        $result = $this->normalizeFailed($data);

        $this->assertSame(['category' => 'rejected', 'code' => $code, 'retryable' => false], $result['error']);
        $this->assertSame($detail, $result['observed']['order_status_detail']);
        $this->assertSame($detail, $result['observed']['payment_status_detail']);
        $this->assertSame($buyerCode, SmartPixBuyerError::fromRejectionStatusDetail(SmartPixOrderNormalizer::rejectionStatusDetail($result['error'])));
    }

    public static function rejectionReasonProvider(): array
    {
        return [
            'insufficient balance' => ['insufficient_amount', 'SMART_PIX_REJECTED_INSUFFICIENT_AMOUNT', SmartPixBuyerError::INSUFFICIENT_BALANCE],
            'limit exceeded' => ['rejected_cap_exceeded', 'SMART_PIX_REJECTED_REJECTED_CAP_EXCEEDED', SmartPixBuyerError::LIMIT_EXCEEDED],
            'high risk' => ['high_risk', 'SMART_PIX_REJECTED_HIGH_RISK', SmartPixBuyerError::REJECTED],
            'unmapped reason' => ['rejected_by_regulations_2', 'SMART_PIX_REJECTED_REJECTED_BY_REGULATIONS_2', SmartPixBuyerError::REJECTED],
        ];
    }

    /**
     * @dataProvider invalidProcessingPpResponseProvider
     * @param array<string, mixed> $changes
     */
    public function testProcessingPpOrderRejectsContradictoryFacts(array $changes, string $category): void
    {
        $data = $this->ppFixture();
        $data['total_paid_amount'] = $data['payments'][0]['paid_amount'] = '0';
        $data['status'] = $data['payments'][0]['status'] = 'processing';
        $data['status_detail'] = $data['payments'][0]['status_detail'] = 'in_process';
        $data = array_replace_recursive($data, $changes);

        $result = (new SmartPixOrderNormalizer())->normalize($data, 'ORDER_SYNTHETIC_001', ['value' => '10.00', 'currency' => 'BRL'], 'WC-321');

        $this->assertFailure($category, $result);
    }

    public static function invalidProcessingPpResponseProvider(): array
    {
        return [
            'accredited detail' => [['status_detail' => 'accredited', 'payments' => [['status_detail' => 'accredited']]], 'unknown'],
            'payment still action required' => [['payments' => [['status' => 'action_required', 'status_detail' => 'waiting_transfer']]], 'unknown'],
            'payment already processed' => [['payments' => [['status' => 'processed', 'status_detail' => 'accredited']]], 'unknown'],
            'paid payment' => [['payments' => [['paid_amount' => '10']]], 'invalid_response'],
            'partially paid payment' => [['payments' => [['paid_amount' => '5']]], 'invalid_response'],
            'partially paid order total' => [['total_paid_amount' => '5'], 'invalid_response'],
            'overpaid order total' => [['total_paid_amount' => '10.01'], 'invalid_response'],
            'malformed failed total' => [['total_failed_amount' => 'ten'], 'invalid_response'],
        ];
    }

    /** @dataProvider nonRejectionErrorProvider */
    public function testRejectionStatusDetailRefusesAnythingButAClosedRejectionError($error): void
    {
        $this->assertNull(SmartPixOrderNormalizer::rejectionStatusDetail($error));
    }

    public static function nonRejectionErrorProvider(): array
    {
        $valid = ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_HIGH_RISK', 'retryable' => false];
        return [
            'null' => [null],
            'string' => ['SMART_PIX_REJECTED_HIGH_RISK'],
            'extra key' => [$valid + ['message' => 'SYNTHETIC_PRIVATE']],
            'missing retryable' => [['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_HIGH_RISK']],
            'retryable' => [array_replace($valid, ['retryable' => true])],
            'non-boolean retryable' => [array_replace($valid, ['retryable' => 0])],
            'other category' => [array_replace($valid, ['category' => 'invalid_response'])],
            'legacy unknown category' => [array_replace($valid, ['category' => 'unknown'])],
            'legacy terminal code' => [array_replace($valid, ['code' => 'SMART_PIX_TERMINAL_FAILURE'])],
            'wrong prefix' => [array_replace($valid, ['code' => 'SMART_PIX_HIGH_RISK'])],
            'empty reason' => [array_replace($valid, ['code' => 'SMART_PIX_REJECTED_'])],
            'lowercase code' => [array_replace($valid, ['code' => 'SMART_PIX_REJECTED_high_risk'])],
            'lowercase prefix' => [array_replace($valid, ['code' => 'smart_pix_rejected_HIGH_RISK'])],
            'symbol in reason' => [array_replace($valid, ['code' => 'SMART_PIX_REJECTED_HIGH-RISK'])],
            'trailing newline' => [array_replace($valid, ['code' => "SMART_PIX_REJECTED_HIGH_RISK\n"])],
            'reason too long' => [array_replace($valid, ['code' => 'SMART_PIX_REJECTED_' . str_repeat('A', 65)])],
            'non-string code' => [array_replace($valid, ['code' => 42])],
        ];
    }

    /** @dataProvider invalidPpResponseProvider */
    public function testPpApprovalRejectsUnverifiedFinancialOrStructuralFacts(array $changes): void
    {
        $data = array_replace_recursive($this->ppFixture(), $changes);
        $result = (new SmartPixOrderNormalizer())->normalize($data, 'ORDER_SYNTHETIC_001', ['value' => '10.00', 'currency' => 'BRL'], 'WC-321');
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame([], $result['payments']);
    }

    public static function invalidPpResponseProvider(): array
    {
        return [
            'unpaid payment' => [['payments' => [['paid_amount' => '0']]]],
            'partial payment' => [['payments' => [['paid_amount' => '9.99']]]],
            'underpaid order' => [['total_paid_amount' => '9.99']],
            'overpaid order' => [['total_paid_amount' => '10.01']],
            'wrong total' => [['total_amount' => '11', 'total_paid_amount' => '11', 'payments' => [['amount' => '11', 'paid_amount' => '11']]]],
            'wrong currency' => [['currency' => 'USD']],
            'wrong seller country' => [['site_id' => 'MLA']],
            'ambiguous country' => [['country_code' => 'ARG']],
            'wrong order id' => [['id' => 'OTHER_ORDER']],
            'wrong attempt' => [['external_reference' => 'WC-322']],
            'float payment' => [['payments' => [['paid_amount' => 10.0]]]],
            'subcent' => [['payments' => [['paid_amount' => '10.001']]]],
            'unknown state' => [['status' => 'approved']],
            'unknown detail' => [['status_detail' => 'unknown']],
            'pending payment' => [['payments' => [['status' => 'action_required', 'status_detail' => 'waiting_transfer']]]],
            'wrong method' => [['payments' => [['payment_method' => ['id' => 'pix']]]]],
            'missing payments' => [['payments' => null]],
            'malformed payment' => [['payments' => ['invalid']]],
            'mixed envelopes' => [['transactions' => ['payments' => []]]],
        ];
    }

    public function testPpAccreditedPaymentWithRefundsFailsClosedUntilLifecycleIsConfirmed(): void
    {
        $data = $this->ppFixture();
        $data['payments'][0]['refunds'] = [[
            'transaction_id' => 'PAYMENT_SYNTHETIC_001', 'amount' => '4', 'status' => 'processed',
            'source' => 'mp-op-pp-order-api', 'references' => ['refund_payment_id' => 'SYNTHETIC_PRIVATE_REFUND'],
        ]];
        $result = (new SmartPixOrderNormalizer())->normalize(
            $data,
            'ORDER_SYNTHETIC_001',
            ['value' => '10.00', 'currency' => 'BRL'],
            'WC-321'
        );
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('error', $result['state']);
        $this->assertSame('unknown', $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        $this->assertSame([], $result['payments']);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE_REFUND', json_encode($result));
    }

    public function testPpAccreditedPaymentWithEmptyRefundListStillApproves(): void
    {
        $data = $this->ppFixture();
        $data['payments'][0]['refunds'] = [];
        $result = (new SmartPixOrderNormalizer())->normalize($data, 'ORDER_SYNTHETIC_001', ['value' => '10.00', 'currency' => 'BRL'], 'WC-321');
        $this->assertSame('terminal_success', $result['state']);
        $this->assertSame('approved', $result['payments'][0]['state']);
    }

    /** @dataProvider malformedRefundsProvider */
    public function testPpMalformedRefundsAreInvalidResponses($refunds): void
    {
        $data = $this->ppFixture();
        $data['payments'][0]['refunds'] = $refunds;
        $result = (new SmartPixOrderNormalizer())->normalize($data, 'ORDER_SYNTHETIC_001', ['value' => '10.00', 'currency' => 'BRL'], 'WC-321');
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        $this->assertSame([], $result['payments']);
    }

    public static function malformedRefundsProvider(): array
    {
        $refund = ['transaction_id' => 'PAYMENT_SYNTHETIC_001', 'amount' => '4', 'status' => 'processed'];
        return [
            'null' => [null],
            'string' => ['SYNTHETIC_REFUND'],
            'associative' => [['first' => $refund]],
            'sparse' => [[1 => $refund]],
        ];
    }

    public function testPpPendingPaymentWithRefundsIsUnexpectedAndNeverProjected(): void
    {
        $data = $this->ppFixture();
        $data['status'] = $data['payments'][0]['status'] = 'action_required';
        $data['status_detail'] = $data['payments'][0]['status_detail'] = 'waiting_transfer';
        $data['total_paid_amount'] = $data['payments'][0]['paid_amount'] = '0';
        $data['payments'][0]['refunds'] = [['transaction_id' => 'PAYMENT_SYNTHETIC_001', 'amount' => '10', 'status' => 'processed']];
        $result = (new SmartPixOrderNormalizer())->normalize($data, 'ORDER_SYNTHETIC_001', ['value' => '10.00', 'currency' => 'BRL'], 'WC-321');
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('unknown', $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        $this->assertSame([], $result['payments']);
    }

    public function testPpCollectionRequiresUniquePaymentsAndExactTotalWithoutFloatArithmetic(): void
    {
        $data = $this->ppFixture();
        $payment = $data['payments'][0];
        $data['payments'][0]['amount'] = $data['payments'][0]['paid_amount'] = '6.75';
        $payment['id'] = 'PAYMENT_SYNTHETIC_002';
        $payment['amount'] = $payment['paid_amount'] = '3.25';
        $data['payments'][] = $payment;
        $normalizer = new SmartPixOrderNormalizer();
        $this->assertCount(2, $normalizer->normalize($data)['payments']);
        $data['payments'][1]['amount'] = $data['payments'][1]['paid_amount'] = '3.24';
        $this->assertSame('failure', $normalizer->normalize($data)['outcome']);
        $data['payments'][1] = $data['payments'][0];
        $this->assertSame('failure', $normalizer->normalize($data)['outcome']);
    }

    /** Masked real GET: the layer id follows references.source, as in the other Orders integrations. */
    public function testPpObservationKeepsTheFirstPaymentIdAndItsLayerReferenceBySource(): void
    {
        $data = $this->ppFixture();
        $data['payments'][0]['references'] = [
            'source' => 'mp_order', 'order_id' => 'ORDBTASYNTHETIC001',
            'order_transaction_id' => 'PAYSYNTHETIC001', 'transaction_intent_id' => '', 'payment_id' => '',
        ];

        $result = (new SmartPixOrderNormalizer())->normalize(
            $data,
            'ORDER_SYNTHETIC_001',
            ['value' => '10.00', 'currency' => 'BRL'],
            'WC-321'
        );

        $this->assertSame('terminal_success', $result['state']);
        $this->assertSame('PAYMENT_SYNTHETIC_001', $result['observed']['payment_id']);
        $this->assertSame('ORDBTASYNTHETIC001', $result['observed']['payment_reference']);
        unset($result['observed']);
        $this->assertStringNotContainsString('ORDBTASYNTHETIC001', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function testPpReferencesProofAndProviderDetailsAreNotCopiedToTheResult(): void
    {
        $data = $this->ppFixture();
        $data['payer'] = ['email' => 'synthetic@example.test'];
        $data['payments'][0]['payment_method']['token'] = 'SYNTHETIC_PRIVATE_PROOF';
        $data['payments'][0]['references'] = ['payment_id' => 'SYNTHETIC_PROVIDER_ID', 'transaction_intent_id' => 'SYNTHETIC_INTENT'];
        $json = json_encode((new SmartPixOrderNormalizer())->normalize($data), JSON_THROW_ON_ERROR);
        foreach (['payer', 'token', 'references', 'SYNTHETIC_PRIVATE_PROOF', 'SYNTHETIC_PROVIDER_ID', 'SYNTHETIC_INTENT'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $json);
        }
    }

    /** @return array<string, mixed> */
    private function failedPpFixture(): array
    {
        $data = $this->ppFixture();
        $data['total_paid_amount'] = '0';
        $data['total_failed_amount'] = '10';
        $data['status'] = $data['payments'][0]['status'] = 'failed';
        $data['status_detail'] = $data['payments'][0]['status_detail'] = 'high_risk';
        unset($data['payments'][0]['paid_amount']);
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeFailed(array $data): array
    {
        return (new SmartPixOrderNormalizer())->normalize($data, 'ORDER_SYNTHETIC_001', ['value' => '10.00', 'currency' => 'BRL'], 'WC-321');
    }

    /** @param array<string, mixed> $result */
    private function assertFailure(string $category, array $result): void
    {
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('error', $result['state']);
        $this->assertSame($category, $result['error']['category']);
        $this->assertSame('SMART_PIX_' . strtoupper($category), $result['error']['code']);
        $this->assertFalse($result['error']['retryable']);
        $this->assertSame([], $result['payments']);
        $this->assertArrayNotHasKey('observed', $result);
        $this->assertNull(SmartPixOrderNormalizer::rejectionStatusDetail($result['error']));
    }

    /** Sanitized P&P shape reported by the operator, with synthetic identifiers. */
    private function ppFixture(): array
    {
        return [
            'id' => 'ORDER_SYNTHETIC_001', 'type' => 'online', 'external_reference' => 'WC-321',
            'site_id' => 'MLB', 'currency' => 'BRL', 'total_amount' => '10', 'total_paid_amount' => '10',
            'status' => 'processed', 'status_detail' => 'accredited',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC_001', 'status' => 'processed', 'status_detail' => 'accredited',
                'amount' => '10', 'paid_amount' => '10',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]],
        ];
    }

    public function testCollectionOrderNeverSelectsOnePaymentAndReplayHasNoStatefulEffects(): void
    {
        $data = $this->fixture();
        $second = $data['transactions']['payments'][0];
        $second['id'] = 'PAYMENT_SYNTHETIC_002';
        $second['amount'] = '1.25';
        $data['transactions']['payments'][0]['amount'] = '2.75';
        $data['transactions']['payments'][] = $second;
        $normalizer = new SmartPixOrderNormalizer();

        $first = $normalizer->normalize($data);
        $this->assertSame(['PAYMENT_SYNTHETIC_001', 'PAYMENT_SYNTHETIC_002'], array_column($first['payments'], 'reference'));
        $this->assertSame(['2.75', '1.25'], array_column(array_column($first['payments'], 'amount'), 'value'));
        $this->assertSame($first, $normalizer->normalize($data));
        $data['transactions']['payments'] = array_reverse($data['transactions']['payments']);
        $reversed = $normalizer->normalize($data);
        $this->assertSame(array_reverse($first['payments']), $reversed['payments']);
        $this->assertSame('processing', $reversed['state']);
    }

    public function testCollectionWhosePaymentsDoNotSumToTheOrderTotalFailsClosed(): void
    {
        $data = $this->fixture();
        $second = $data['transactions']['payments'][0];
        $second['id'] = 'PAYMENT_SYNTHETIC_002';
        $second['amount'] = '1.24';
        $data['transactions']['payments'][0]['amount'] = '2.75';
        $data['transactions']['payments'][] = $second;

        $result = (new SmartPixOrderNormalizer())->normalize($data);

        $this->assertFailure('invalid_response', $result);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
    }

    /**
     * @dataProvider invalidResponseProvider
     * @param array<string, mixed> $changes
     */
    public function testUnsupportedOrMalformedResponsesNeverApprove(array $changes, string $category): void
    {
        $data = array_replace_recursive($this->fixture(), $changes);
        $result = (new SmartPixOrderNormalizer())->normalize($data);

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('error', $result['state']);
        $this->assertSame($category, $result['error']['category']);
        $this->assertSame([], $result['payments']);
        $this->assertFalse($result['error']['retryable']);
    }

    public static function invalidResponseProvider(): array
    {
        return [
            'unknown order state' => [['status' => 'unrecognized'], 'unknown'],
            'unknown order detail' => [['status_detail' => 'unrecognized'], 'unknown'],
            'unevidenced terminal success' => [['status' => 'processed'], 'unknown'],
            'unknown payment state' => [['transactions' => ['payments' => [['status' => 'approved']]]], 'unknown'],
            'unknown payment detail' => [['transactions' => ['payments' => [['status_detail' => 'unrecognized']]]], 'unknown'],
            'wrong currency' => [['currency' => 'USD'], 'invalid_response'],
            'wrong country' => [['country_code' => 'ARG'], 'invalid_response'],
            'wrong type' => [['type' => 'offline'], 'invalid_response'],
            'invalid order reference' => [['id' => '../order'], 'invalid_response'],
            'numeric total' => [['total_amount' => 4.0], 'invalid_response'],
            'zero total' => [['total_amount' => '0.00'], 'invalid_response'],
            'negative total' => [['total_amount' => '-4.00'], 'invalid_response'],
            'subcent total' => [['total_amount' => '4.005'], 'invalid_response'],
            'newline total' => [['total_amount' => "4.00\n"], 'invalid_response'],
            'pending with paid amount' => [['total_paid_amount' => '4.00'], 'invalid_response'],
            'numeric paid amount' => [['total_paid_amount' => 0], 'invalid_response'],
            'invalid collection' => [['transactions' => ['payments' => null]], 'invalid_response'],
            'missing transactions' => [['transactions' => null], 'invalid_response'],
            'malformed payment' => [['transactions' => ['payments' => ['invalid']]], 'invalid_response'],
            'missing payment id' => [['transactions' => ['payments' => [['id' => null]]]], 'invalid_response'],
            'numeric payment amount' => [['transactions' => ['payments' => [['amount' => 4.0]]]], 'invalid_response'],
            'single payment below total' => [['transactions' => ['payments' => [['amount' => '0.01']]]], 'invalid_response'],
            'single payment above total' => [['transactions' => ['payments' => [['amount' => '4.01']]]], 'invalid_response'],
            'wrong method' => [['transactions' => ['payments' => [['payment_method' => ['id' => 'pix']]]]], 'invalid_response'],
            'ambiguous root payments' => [['payments' => [['id' => 'ROOT_PAYMENT']]], 'invalid_response'],
        ];
    }

    public function testDuplicateSparseOrEmptyCollectionsFailClosed(): void
    {
        $data = $this->fixture();
        $payment = $data['transactions']['payments'][0];
        foreach ([[], [$payment, $payment], [2 => $payment]] as $collection) {
            $data['transactions']['payments'] = $collection;
            $result = (new SmartPixOrderNormalizer())->normalize($data);
            $this->assertSame('invalid_response', $result['error']['category']);
            $this->assertSame([], $result['payments']);
        }
    }

    public function testOrderAttemptAndFinancialLinkageAreChecked(): void
    {
        $data = $this->fixture();
        $normalizer = new SmartPixOrderNormalizer();
        $mismatch = $normalizer->normalize($data, 'OTHER_ORDER');
        $this->assertSame('linkage_mismatch', $mismatch['error']['category']);
        $this->assertSame('OTHER_ORDER', $mismatch['order_reference']);
        $this->assertSame('linkage_mismatch', $normalizer->normalize($data, null, null, 'OTHER_ATTEMPT')['error']['category']);
        $this->assertSame('invalid_response', $normalizer->normalize($data, null, ['value' => '5.00', 'currency' => 'BRL'])['error']['category']);
    }

    public function testUnknownRawFieldsAndAuthorizationDoNotLeaveTheNormalizer(): void
    {
        $data = $this->fixture();
        $data['payer'] = ['email' => 'synthetic@example.test'];
        $data['original_message'] = 'UNTRUSTED_PROVIDER_MESSAGE';
        $data['user_id'] = 'USER_SYNTHETIC_001';
        $data['integration_data'] = ['application_id' => 'APPLICATION_SYNTHETIC_001'];
        $data['transactions']['payments'][0]['reference_id'] = 'REFERENCE_SYNTHETIC_001';
        $data['transactions']['payments'][0]['payment_method']['token'] = 'SYNTHETIC_AUTHORIZATION_NOT_VALID';
        $result = (new SmartPixOrderNormalizer())->normalize($data);
        $json = json_encode($result, JSON_THROW_ON_ERROR);
        foreach (['token', 'payment_method', 'payer', 'user_id', 'original_message', 'integration_data', 'reference_id'] as $field) {
            $this->assertStringNotContainsString('"' . $field . '"', $json);
        }
    }

    /**
     * Minimal pending input with synthetic references and exact decimal amounts.
     * @return array<string, mixed>
     */
    private function fixture(): array
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
}
