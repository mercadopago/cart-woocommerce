<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\DenyAllSmartPixNotificationAuthenticator;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationAuthenticator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DenyAllSmartPixNotificationAuthenticatorTest extends TestCase
{
    public function testAuthenticateRejectsAWellFormedRequest(): void
    {
        $authenticator = new DenyAllSmartPixNotificationAuthenticator();
        $headers = [
            'content-type' => 'application/json',
            'x-signature' => 'ts=1700000000,v1=SYNTHETIC_SIGNATURE',
            'x-request-id' => 'SYNTHETIC_REQUEST_001',
        ];
        $rawBody = '{"id":"ORDER_SYNTHETIC_001","type":"order"}';

        $this->assertInstanceOf(SmartPixNotificationAuthenticator::class, $authenticator);
        $this->assertFalse($authenticator->authenticate($headers, $rawBody));
    }

    /** @return array<string, array{0: array<string, string>, 1: string}> */
    public function requestProvider(): array
    {
        return [
            'empty headers and body' => [[], ''],
            'large body' => [['content-type' => 'application/json'], str_repeat('a', 1048576)],
            'binary body' => [['content-type' => 'application/octet-stream'], "\x00\x01\x02\xfe\xff"],
            'invalid utf-8 body' => [['content-type' => 'application/json'], "{\"a\":\"\xff\xfe\"}"],
            'mixed case unnormalized header keys' => [
                [
                    'Content-Type' => 'application/json',
                    'X-Signature' => 'ts=1700000000,v1=SYNTHETIC_SIGNATURE',
                    'X-REQUEST-ID' => 'SYNTHETIC_REQUEST_001',
                ],
                '{"id":"ORDER_SYNTHETIC_001"}',
            ],
        ];
    }

    /**
     * @dataProvider requestProvider
     * @param array<string, string> $headers
     */
    public function testAuthenticateRejectsAnyRequest(array $headers, string $rawBody): void
    {
        $this->assertFalse((new DenyAllSmartPixNotificationAuthenticator())->authenticate($headers, $rawBody));
    }

    public function testAuthenticateRejectsRepeatedCallsOnTheSameInstance(): void
    {
        $authenticator = new DenyAllSmartPixNotificationAuthenticator();
        $headers = ['x-signature' => 'ts=1700000000,v1=SYNTHETIC_SIGNATURE'];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->assertFalse($authenticator->authenticate($headers, '{"id":"ORDER_SYNTHETIC_001"}'));
            $this->assertFalse($authenticator->authenticate([], ''));
        }
    }

    public function testAuthenticatorHasNoConstructorParameters(): void
    {
        $constructor = (new ReflectionClass(DenyAllSmartPixNotificationAuthenticator::class))->getConstructor();

        $this->assertTrue($constructor === null || $constructor->getNumberOfParameters() === 0);
    }
}
