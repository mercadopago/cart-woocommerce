<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use Exception;
use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\SubscriptionsCredentialsValidator;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class SubscriptionsCredentialsValidatorTest extends TestCase
{
    private const VALID_TOKEN   = 'APP_USR-1234567890123456-X-X-X';
    private const INVALID_TOKEN = 'BADTOKEN';

    /** @var Mockery\MockInterface|Requester */
    private $requesterMock;

    /** @var Mockery\MockInterface|Logs */
    private $logsMock;

    /** @var Mockery\MockInterface|File */
    private $fileTransportMock;

    /** @var SubscriptionsCredentialsValidator */
    private $validator;

    public function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);

        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }
        if (!defined('MP_VERSION')) {
            define('MP_VERSION', '8.0.0');
        }

        $this->requesterMock     = Mockery::mock(Requester::class);
        $this->fileTransportMock = Mockery::mock(File::class);

        $this->logsMock         = Mockery::mock(Logs::class);
        $this->logsMock->file   = $this->fileTransportMock;
        $this->logsMock->remote = Mockery::mock(Remote::class);

        $this->validator = new SubscriptionsCredentialsValidator(
            $this->requesterMock,
            $this->logsMock
        );
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /* ─────────────────── extractApplicationId ─────────────────── */

    public function testExtractApplicationIdFromValidProdToken(): void
    {
        $result = $this->validator->extractApplicationId(self::VALID_TOKEN);
        $this->assertSame('1234567890123456', $result);
    }

    public function testExtractApplicationIdFromTestToken(): void
    {
        $testToken = 'TEST-1234567890123456-X-X-X';
        $result    = $this->validator->extractApplicationId($testToken);
        $this->assertSame('1234567890123456', $result);
    }

    public function testExtractApplicationIdReturnsNullForMalformedToken(): void
    {
        $this->assertNull($this->validator->extractApplicationId('BADTOKEN'));
        $this->assertNull($this->validator->extractApplicationId('APP_USR-abc-only-four'));
        $this->assertNull($this->validator->extractApplicationId('APP_USR-notanumber-X-X-X'));
        $this->assertNull($this->validator->extractApplicationId('FOO-1234567890123456-X-X-X'));
    }

    /* ─────────────────── validate — pré-validação ─────────────────── */

    public function testValidateReturnsMalformedTokenWithoutHttpCall(): void
    {
        $this->requesterMock->shouldNotReceive('get');

        $result = $this->validator->validate(self::INVALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('malformed_token', $result['reason']);
    }

    /* ─────────────────── validate — cenário 1: scope OK ─────────────────── */

    public function testValidateReturnsTrueWhenScopeOkAndHealthy(): void
    {
        $response = $this->makeResponse(200, [
            'id'       => 1234567890123456,
            'name'     => 'My App',
            'site_id'  => 'MLB',
            'active'   => true,
            'blocked'  => false,
            'disabled' => false,
            'scopes'   => ['read', 'write', 'payments', 'preapproval'],
            'access_token'      => 'FAKE-TOKEN-SHOULD-NOT-LEAK',
            'test_access_token' => 'FAKE-TEST-TOKEN-SHOULD-NOT-LEAK',
        ]);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('info')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertTrue($result['valid']);
        $this->assertSame('ok', $result['reason']);
        $this->assertSame('My App', $result['app_name']);
        $this->assertSame('MLB', $result['site_id']);
        $this->assertSame('1234567890123456', $result['app_id']);
    }

    /* ─────────────────── validate — cenário 2: sem scope ─────────────────── */

    public function testValidateReturnsMissingScopeWhenPreapprovalAbsent(): void
    {
        $response = $this->makeResponse(200, [
            'id'       => 1234567890123456,
            'name'     => 'My App',
            'site_id'  => 'MLB',
            'active'   => true,
            'blocked'  => false,
            'disabled' => false,
            'scopes'   => ['read', 'write', 'payments'],
            'access_token'      => 'FAKE-TOKEN-SHOULD-NOT-LEAK',
            'test_access_token' => 'FAKE-TEST-TOKEN-SHOULD-NOT-LEAK',
        ]);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('warning')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('missing_scope', $result['reason']);
    }

    /* ─────────────────── validate — cenário 3: 401 ─────────────────── */

    public function testValidateReturnsInvalidTokenOn401(): void
    {
        $response = $this->makeResponse(401, []);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('invalid_token', $result['reason']);
    }

    /* ─────────────────── validate — cenário 4: 403 ─────────────────── */

    public function testValidateReturnsTokenAppMismatchOn403(): void
    {
        $response = $this->makeResponse(403, []);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('token_app_mismatch', $result['reason']);
    }

    /* ─────────────────── validate — cenário 5: 5xx ─────────────────── */

    public function testValidateReturnsServiceUnavailableOn5xx(): void
    {
        $response = $this->makeResponse(500, []);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('service_unavailable', $result['reason']);
    }

    public function testValidateReturnsServiceUnavailableOnException(): void
    {
        $this->requesterMock->shouldReceive('get')->once()
            ->andThrow(new Exception('Connection timed out', 0));

        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('service_unavailable', $result['reason']);
    }

    /* ─────────────── AC-4: credenciais da response são descartadas ─────────────── */

    public function testValidateDoesNotLeakAccessTokenFromResponse(): void
    {
        $response = $this->makeResponse(200, [
            'id'       => 1234567890123456,
            'name'     => 'My App',
            'site_id'  => 'MLB',
            'active'   => true,
            'blocked'  => false,
            'disabled' => false,
            'scopes'   => ['preapproval'],
            'access_token'      => 'FAKE-TOKEN-SHOULD-NOT-LEAK',
            'test_access_token' => 'FAKE-TEST-TOKEN-SHOULD-NOT-LEAK',
        ]);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('info')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $serialized = serialize($result);
        $this->assertStringNotContainsString('FAKE-TOKEN-SHOULD-NOT-LEAK', $serialized);
        $this->assertStringNotContainsString('FAKE-TEST-TOKEN-SHOULD-NOT-LEAK', $serialized);
        $this->assertArrayNotHasKey('access_token', $result);
        $this->assertArrayNotHasKey('test_access_token', $result);
    }

    /* ─────────────── saúde da aplicação ─────────────── */

    public function testValidateReturnsApplicationInactiveWhenActiveFalse(): void
    {
        $response = $this->makeResponse(200, [
            'id'       => 1234567890123456,
            'name'     => 'My App',
            'site_id'  => 'MLB',
            'active'   => false,
            'blocked'  => false,
            'disabled' => false,
            'scopes'   => ['preapproval'],
        ]);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('application_inactive', $result['reason']);
    }

    public function testValidateReturnsApplicationBlockedWhenBlockedTrue(): void
    {
        $response = $this->makeResponse(200, [
            'id'       => 1234567890123456,
            'name'     => 'My App',
            'site_id'  => 'MLB',
            'active'   => true,
            'blocked'  => true,
            'disabled' => false,
            'scopes'   => ['preapproval'],
        ]);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('application_blocked', $result['reason']);
    }

    public function testValidateReturnsApplicationNotFoundOn404(): void
    {
        $response = $this->makeResponse(404, []);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('application_not_found', $result['reason']);
    }

    public function testValidateReturnsApplicationDisabledWhenDisabledTrue(): void
    {
        $response = $this->makeResponse(200, [
            'id'       => 1234567890123456,
            'name'     => 'My App',
            'site_id'  => 'MLB',
            'active'   => true,
            'blocked'  => false,
            'disabled' => true,
            'scopes'   => ['preapproval'],
        ]);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('application_disabled', $result['reason']);
    }

    public function testValidateReturnsScopeFieldMissingWhenScopesNull(): void
    {
        $response = $this->makeResponse(200, [
            'id'       => 1234567890123456,
            'name'     => 'My App',
            'site_id'  => 'MLB',
            'active'   => true,
            'blocked'  => false,
            'disabled' => false,
        ]);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('error')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('scope_field_missing', $result['reason']);
    }

    public function testValidateReturnsUnexpectedResponseOnUnknownStatus(): void
    {
        $response = $this->makeResponse(202, []);

        $this->requesterMock->shouldReceive('get')->once()->andReturn($response);
        $this->fileTransportMock->shouldReceive('warning')->once();

        $result = $this->validator->validate(self::VALID_TOKEN);

        $this->assertFalse($result['valid']);
        $this->assertSame('unexpected_response', $result['reason']);
    }

    /* ─────────────── helpers ─────────────── */

    private function makeResponse(int $status, array $data): Response
    {
        $response = new Response();
        $response->setStatus($status);
        $response->setData((object) $data);
        return $response;
    }
}
