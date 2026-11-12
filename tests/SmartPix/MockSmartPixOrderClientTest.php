<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class MockSmartPixOrderClientTest extends TestCase
{
    public function testCreateReturnsTheNormalizedProcessingResponse(): void
    {
        $fixture = $this->loadFixture('orders-create-processing-without-payment');
        $client  = mpCreateSmartPixOrderClientMockForTest($fixture['scenario_id']);

        $response = $client->create($fixture['request']);

        $this->assertSame($fixture['response'], $response);
        $this->assertSame('success', $response['outcome']);
        $this->assertSame('processing', $response['state']);
        $this->assertSame([], $response['payments']);
        $this->assertSame(
            [
                [
                    'operation' => 'create',
                    'request'   => $fixture['request'],
                ],
            ],
            $client->getCalls()
        );
    }

    public function testQueryReturnsATerminalSuccessResponse(): void
    {
        $fixture = $this->loadFixture('orders-query-terminal-success');
        $client  = mpCreateSmartPixOrderClientMockForTest($fixture['scenario_id']);

        $response = $client->query($fixture['request']);

        $this->assertSame($fixture['response'], $response);
        $this->assertSame('terminal_success', $response['state']);
        $this->assertSame('approved', $response['payments'][0]['state']);
        $this->assertSingleCall($client, 'query', $fixture['request']);
    }

    public function testQueryReturnsATerminalFailureResponse(): void
    {
        $fixture = $this->loadFixture('orders-query-terminal-failure');
        $client  = mpCreateSmartPixOrderClientMockForTest($fixture['scenario_id']);

        $response = $client->query($fixture['request']);

        $this->assertSame($fixture['response'], $response);
        $this->assertSame('terminal_failure', $response['state']);
        $this->assertSame('rejected', $response['error']['category']);
        $this->assertFalse($response['error']['retryable']);
        $this->assertSingleCall($client, 'query', $fixture['request']);
    }

    /**
     * @dataProvider recoverableFailureProvider
     */
    public function testQueryReturnsNormalizedRecoverableFailures(string $scenarioId, string $category): void
    {
        $fixture = $this->loadFixture($scenarioId);
        $client  = mpCreateSmartPixOrderClientMockForTest($fixture['scenario_id']);

        $response = $client->query($fixture['request']);

        $this->assertSame($fixture['response'], $response);
        $this->assertSame('failure', $response['outcome']);
        $this->assertSame('processing', $response['state']);
        $this->assertSame($category, $response['error']['category']);
        $this->assertTrue($response['error']['retryable']);
        $this->assertSingleCall($client, 'query', $fixture['request']);
    }

    /**
     * @return array<string, array{scenarioId: string, category: string}>
     */
    public function recoverableFailureProvider(): array
    {
        return [
            'timeout' => [
                'scenarioId' => 'orders-query-timeout',
                'category'   => 'timeout',
            ],
            'rate limit' => [
                'scenarioId' => 'orders-query-rate-limit',
                'category'   => 'rate_limit',
            ],
        ];
    }

    public function testQueryReturnsAClosedInvalidResponseFailure(): void
    {
        $fixture = $this->loadFixture('orders-query-invalid-response');
        $client  = mpCreateSmartPixOrderClientMockForTest($fixture['scenario_id']);

        $response = $client->query($fixture['request']);

        $this->assertSame($fixture['response'], $response);
        $this->assertSame('failure', $response['outcome']);
        $this->assertSame('error', $response['state']);
        $this->assertSame('invalid_response', $response['error']['category']);
        $this->assertFalse($response['error']['retryable']);
        $this->assertSingleCall($client, 'query', $fixture['request']);
    }

    /**
     * @dataProvider terminalOperationProvider
     */
    public function testTerminalOperationsReturnTheirNormalizedResponses(
        string $scenarioId,
        string $operation,
        string $state
    ): void {
        $fixture = $this->loadFixture($scenarioId);
        $client  = mpCreateSmartPixOrderClientMockForTest($fixture['scenario_id']);

        $response = $client->{$operation}($fixture['request']);

        $this->assertSame($fixture['response'], $response);
        $this->assertSame($state, $response['state']);
        $this->assertSingleCall($client, $operation, $fixture['request']);
    }

    /**
     * @return array<string, array{scenarioId: string, operation: string, state: string}>
     */
    public function terminalOperationProvider(): array
    {
        return [
            'cancel' => [
                'scenarioId' => 'orders-cancel-terminal-success',
                'operation'  => 'cancel',
                'state'      => 'terminal_failure',
            ],
            'refund' => [
                'scenarioId' => 'orders-refund-terminal-success',
                'operation'  => 'refund',
                'state'      => 'terminal_success',
            ],
        ];
    }

    public function testRejectsASelectedFixtureForAnotherOperation(): void
    {
        $fixture = $this->loadFixture('orders-create-processing-without-payment');
        $client  = mpCreateSmartPixOrderClientMockForTest($fixture['scenario_id']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('does not match the requested Orders operation');

        $client->query($fixture['request']);
    }

    public function testRejectsARequestThatDoesNotMatchTheSelectedFixture(): void
    {
        $fixture                               = $this->loadFixture('orders-query-terminal-success');
        $fixture['request']['order_reference'] = 'order_fixture_different_001';
        $client                                = mpCreateSmartPixOrderClientMockForTest(
            'orders-query-terminal-success'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('normalized request does not match');

        $client->query($fixture['request']);
    }

    public function testRejectsARequestWithASchemaInvalidScalarType(): void
    {
        $fixture                               = $this->loadFixture('orders-create-processing-without-payment');
        $fixture['request']['amount']['value'] = 125.50;
        $client                                = mpCreateSmartPixOrderClientMockForTest(
            'orders-create-processing-without-payment'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('normalized request does not match');

        $client->create($fixture['request']);
    }

    public function testRejectsAnInvalidScenarioIdentifier(): void
    {
        $client = mpCreateSmartPixOrderClientMockForTest('../orders-query-terminal-success');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('scenario identifier is invalid');

        $client->query([]);
    }

    public function testRejectsAFixtureWhoseScenarioIdDoesNotMatchTheRequestedScenario(): void
    {
        $fixture                 = $this->loadFixture('orders-query-terminal-success');
        $fixture['scenario_id']  = 'orders-query-success';
        $mismatchedScenarioId    = 'orders-query-mismatched-scenario';
        $temporaryDirectory      = sys_get_temp_dir() . '/smart-pix-mock-' . uniqid('', true);

        mkdir($temporaryDirectory . '/orders', 0700, true);
        file_put_contents(
            $temporaryDirectory . '/orders/' . $mismatchedScenarioId . '.json',
            json_encode($fixture, JSON_THROW_ON_ERROR)
        );

        try {
            $client = new MockSmartPixOrderClient($temporaryDirectory, $mismatchedScenarioId);

            $this->expectException(UnexpectedValueException::class);
            $this->expectExceptionMessage('scenario_id does not match');

            $client->query($fixture['request']);
        } finally {
            unlink($temporaryDirectory . '/orders/' . $mismatchedScenarioId . '.json');
            rmdir($temporaryDirectory . '/orders');
            rmdir($temporaryDirectory);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $scenarioId): array
    {
        $path = __DIR__ . "/fixtures/orders/{$scenarioId}.json";

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $request
     */
    private function assertSingleCall(
        MockSmartPixOrderClient $client,
        string $operation,
        array $request
    ): void {
        $this->assertSame(
            [
                [
                    'operation' => $operation,
                    'request'   => $request,
                ],
            ],
            $client->getCalls()
        );
    }
}
