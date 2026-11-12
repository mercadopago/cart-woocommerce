<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use InvalidArgumentException;
use JsonException;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use RuntimeException;
use UnexpectedValueException;

class MockSmartPixOrderClient implements SmartPixOrderClient
{
    private const CONTRACT_VERSION = 'smart-pix-internal/v1';

    private string $fixturesDirectory;

    private string $scenarioId;

    /**
     * @var array<int, array{operation: string, request: array<string, mixed>}>
     */
    private array $calls = [];

    public function __construct(string $fixturesDirectory, string $scenarioId)
    {
        $this->fixturesDirectory = rtrim($fixturesDirectory, DIRECTORY_SEPARATOR);
        $this->scenarioId        = $scenarioId;
    }

    public function create(array $request, ?SmartPixAuthorizedPayment $payment = null): array
    {
        return $this->respond('create', $request);
    }

    public function query(array $request): array
    {
        return $this->respond('query', $request);
    }

    public function cancel(array $request): array
    {
        return $this->respond('cancel', $request);
    }

    public function refund(array $request): array
    {
        return $this->respond('refund', $request);
    }

    /**
     * @return array<int, array{operation: string, request: array<string, mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function respond(string $operation, array $request): array
    {
        $fixture = $this->loadFixture();

        if (($fixture['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new UnexpectedValueException('The Smart Pix fixture uses an unsupported contract version.');
        }

        if (($fixture['scenario_id'] ?? null) !== $this->scenarioId) {
            throw new UnexpectedValueException('The Smart Pix fixture scenario_id does not match the requested scenario.');
        }

        if (($fixture['port'] ?? null) !== 'orders' || ($fixture['operation'] ?? null) !== $operation) {
            throw new UnexpectedValueException('The Smart Pix fixture does not match the requested Orders operation.');
        }

        if (
            !isset($fixture['request'])
            || !is_array($fixture['request'])
            || !$this->hasSameNormalizedValue($fixture['request'], $request)
        ) {
            throw new InvalidArgumentException('The normalized request does not match the selected Smart Pix fixture.');
        }

        if (!isset($fixture['response']) || !is_array($fixture['response'])) {
            throw new UnexpectedValueException('The Smart Pix fixture does not contain a normalized response.');
        }

        $this->calls[] = [
            'operation' => $operation,
            'request'   => $request,
        ];

        return $fixture['response'];
    }

    /**
     * Compare normalized values without coercing scalar types.
     *
     * Associative array key order is irrelevant, while indexed values remain bound to their keys.
     *
     * @param mixed $expected
     * @param mixed $actual
     */
    private function hasSameNormalizedValue($expected, $actual): bool
    {
        if (is_array($expected) !== is_array($actual)) {
            return false;
        }

        if (!is_array($expected) || !is_array($actual)) {
            return gettype($expected) === gettype($actual) && $expected === $actual;
        }

        if (count($expected) !== count($actual)) {
            return false;
        }

        foreach ($expected as $key => $expectedValue) {
            if (
                !array_key_exists($key, $actual)
                || !$this->hasSameNormalizedValue($expectedValue, $actual[$key])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(): array
    {
        if (!preg_match('/^[a-z][a-z0-9-]{2,79}$/', $this->scenarioId)) {
            throw new InvalidArgumentException('The Smart Pix scenario identifier is invalid.');
        }

        $path = $this->fixturesDirectory
            . DIRECTORY_SEPARATOR
            . 'orders'
            . DIRECTORY_SEPARATOR
            . $this->scenarioId
            . '.json';

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The Smart Pix fixture could not be read.');
        }

        try {
            $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('The Smart Pix fixture contains invalid JSON.', 0, $exception);
        }

        if (!is_array($fixture)) {
            throw new UnexpectedValueException('The Smart Pix fixture root must be an object.');
        }

        return $fixture;
    }
}
