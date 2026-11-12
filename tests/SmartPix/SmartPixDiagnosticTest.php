<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use PHPUnit\Framework\TestCase;

class SmartPixDiagnosticTest extends TestCase
{
    public function testCoreOriginalMessageIsKeptWhenThePublicApiMessageIsGeneric(): void
    {
        $original = 'Consent expired; email=buyer@example.test; token=synthetic-proof';
        $response = new HttpResponse(422, [], '', ['message' => 'server_error', 'original_message' => $original]);
        $diagnostic = SmartPixDiagnostic::response($response);
        $this->assertStringContainsString('Consent expired', $diagnostic);
        $this->assertStringNotContainsString('buyer@example.test', $diagnostic);
        $this->assertStringNotContainsString('synthetic-proof', $diagnostic);
        $error = new \MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException('server_error', 'provider_error', 422, $original, 422);
        $diagnostic = SmartPixDiagnostic::exception($error);
        $this->assertStringContainsString('Consent expired', $diagnostic);
        $this->assertStringNotContainsString('synthetic-proof', $diagnostic);
        $this->assertSame('server_error', $error->getMessage());
    }

    public function testContractDiagnosticExplainsTheFailureWithoutChangingTheClosedExceptionReason(): void
    {
        $error = new \MercadoPago\Woocommerce\SmartPix\SmartPixDiagnosticException('invalid_response', 'payment.status does not match order.status');
        $this->assertSame('invalid_response', $error->getMessage());
        $this->assertSame('invalid_response: payment.status does not match order.status', SmartPixDiagnostic::exception($error));
        $this->assertStringContainsString('an earlier operation is not confirmed', SmartPixDiagnostic::exception(new \RuntimeException('operation_unresolved')));
    }

    public function testNormalizerDiagnosticsAreClearedBeforeTheNextResponseAndNeverAddedToTheResult(): void
    {
        $normalizer = new \MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer();
        $normalizer->normalize(['id' => 'PRIVATE_SENTINEL', 'external_reference' => 'WC-321']);
        $this->assertSame('legacy order.status must be processing', $normalizer->diagnosticMessage());
        $result = $normalizer->normalize([]);
        $this->assertSame('order.id is missing or invalid', $normalizer->diagnosticMessage());
        $this->assertStringNotContainsString('diagnostic', json_encode($result));
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', $normalizer->diagnosticMessage());
    }

    public function testOriginalProviderErrorAndNestedCausesArePreservedWithoutTheOrderPayload(): void
    {
        $diagnostic = SmartPixDiagnostic::response(new HttpResponse(422, [], '', [
            'error' => 'invalid_order', 'message' => 'The Open Finance consent has expired.',
            'cause' => [['code' => 'CONSENT_EXPIRED', 'description' => 'A new authorization is required.']],
            'payer' => ['email' => 'synthetic@example.test'],
            'payments' => [['token' => 'synthetic-proof']], 'external_reference' => 'private-order-reference',
        ]));

        $this->assertSame([
            'http_status' => 422, 'error' => 'invalid_order', 'message' => 'The Open Finance consent has expired.',
            'cause' => [['code' => 'CONSENT_EXPIRED', 'description' => 'A new authorization is required.']],
        ], json_decode($diagnostic, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testPlainTextProviderErrorAndOriginalTransportFailureRemainUseful(): void
    {
        $this->assertSame(['http_status' => 502, 'message' => 'Upstream connection reset.'],
            json_decode(SmartPixDiagnostic::response(new HttpResponse(502, [], 'Upstream connection reset.')), true));
        $error = new TransportException('http_request_failed', 'cURL error 28: Operation timed out after 10000 ms');
        $this->assertStringContainsString('cURL error 28: Operation timed out after 10000 ms', SmartPixDiagnostic::exception($error));
        $this->assertSame('Unable to connect to Mercado Pago API.', $error->getMessage());
    }

    public function testCredentialAndContactValuesAreRedactedWithoutReplacingTheDiagnostic(): void
    {
        // Deliberately invalid repeated digits exercise document redaction without a valid CPF.
        $message = SmartPixDiagnostic::message("CONSENT_EXPIRED; token=synthetic-proof; email=buyer@example.test; cpf=00000000000\nCookie: session=private; other=private\nAuthorization: Bearer private\nTry authorization again.");
        $this->assertStringContainsString('CONSENT_EXPIRED', $message);
        $this->assertStringContainsString('Try authorization again.', $message);
        foreach (['synthetic-proof', 'buyer@example.test', '00000000000', 'private', "\n"] as $secret) {
            $this->assertStringNotContainsString($secret, $message);
        }
        $this->assertSame($message, SmartPixDiagnostic::message($message));
    }

    public function testKnownAuthorizationMaterialEchoedWithoutALabelIsStillRemoved(): void
    {
        $this->assertSame('Authorization refused for [REDACTED]',
            SmartPixDiagnostic::message('Authorization refused for opaque-proof-value', ['opaque-proof-value']));
    }

    public function testLongUnicodeErrorsStayBoundedAndSerializable(): void
    {
        $message = SmartPixDiagnostic::message(str_repeat('ç', 5000));
        $this->assertLessThanOrEqual(4096, strlen($message));
        $this->assertSame(1, preg_match('//u', $message));
        $this->assertIsString(json_encode(['message' => $message], JSON_THROW_ON_ERROR));
    }

    public function testMalformedSuccessfulResponseNeverSerializesAnEntireBody(): void
    {
        $this->assertSame('{"http_status":200}', SmartPixDiagnostic::response(new HttpResponse(200, [], 'private response body')));
    }
}
