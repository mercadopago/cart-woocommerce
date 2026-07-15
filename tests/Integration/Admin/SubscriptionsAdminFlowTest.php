<?php

namespace MercadoPago\Woocommerce\Tests\Integration\Admin;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\SubscriptionsCredentialsValidator;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Subscriptions Admin UI credential validation flow.
 *
 * Covers the 5 scenarios when admin saves Pre-approval credentials:
 * 1. Token valid with preapproval scope -> green badge
 * 2. Token valid but missing scope -> inline error
 * 3. Token invalid (401) -> error message
 * 4. Token/app mismatch (403) -> error message
 * 5. Service unavailable (5xx) -> error message
 *
 * @spec feat-001 US-2, US-6 | DD-8, DD-9, DD-12
 * @covers \MercadoPago\Woocommerce\Helpers\SubscriptionsCredentialsValidator
 */
class SubscriptionsAdminFlowTest extends TestCase
{
    private SubscriptionsCredentialsValidator $validator;

    private Mockery\MockInterface $requester;

    private Mockery\MockInterface $logs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requester = Mockery::mock(Requester::class);

        $logFile = Mockery::mock(File::class);
        $logFile->shouldReceive('info')->byDefault();
        $logFile->shouldReceive('error')->byDefault();
        $logFile->shouldReceive('warning')->byDefault();

        $this->logs = Mockery::mock(Logs::class);
        $this->logs->file = $logFile;

        $this->validator = new SubscriptionsCredentialsValidator($this->requester, $this->logs);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeResponse(int $status, array $data): Mockery\MockInterface
    {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn($status);
        $response->shouldReceive('getData')->andReturn((object) $data);
        return $response;
    }

    /**
     * AC-1: Token valid with preapproval scope -> valid=true, includes app_name.
     */
    public function testValidTokenWithPreapprovalScopeReturnsSuccess(): void
    {
        $token = 'TEST-12345-a-b-c';

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($this->makeResponse(200, [
                'name'     => 'My Subscriptions App',
                'site_id'  => 'MLB',
                'scopes'   => ['read', 'write', 'preapproval', 'offline_access'],
                'active'   => true,
                'blocked'  => false,
                'disabled' => false,
            ]));

        $result = $this->validator->validate($token);

        $this->assertTrue($result['valid']);
        $this->assertSame('ok', $result['reason']);
        $this->assertSame('12345', $result['app_id']);
        $this->assertSame('My Subscriptions App', $result['app_name']);
        $this->assertSame('MLB', $result['site_id']);
    }

    /**
     * AC-2: Token valid but missing preapproval scope -> valid=false, reason.
     */
    public function testValidTokenMissingScopeReturnsError(): void
    {
        $token = 'TEST-98765-a-b-c';

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($this->makeResponse(200, [
                'name'     => 'Regular App',
                'site_id'  => 'MLA',
                'scopes'   => ['read', 'write', 'offline_access'],
                'active'   => true,
                'blocked'  => false,
                'disabled' => false,
            ]));

        $result = $this->validator->validate($token);

        $this->assertFalse($result['valid']);
        $this->assertSame('missing_scope', $result['reason']);
    }

    /**
     * AC-3: Token invalid (401) -> valid=false, reason.
     */
    public function testInvalidTokenReturns401Error(): void
    {
        $token = 'TEST-11111-a-b-c';

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($this->makeResponse(401, []));

        $result = $this->validator->validate($token);

        $this->assertFalse($result['valid']);
        $this->assertSame('invalid_token', $result['reason']);
    }

    /**
     * AC-4: Token/app mismatch (403) -> valid=false, reason.
     */
    public function testTokenAppMismatchReturns403Error(): void
    {
        $token = 'TEST-22222-a-b-c';

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($this->makeResponse(403, []));

        $result = $this->validator->validate($token);

        $this->assertFalse($result['valid']);
        $this->assertSame('token_app_mismatch', $result['reason']);
    }

    /**
     * AC-5: Service unavailable (5xx) -> valid=false, reason.
     */
    public function testServiceUnavailableReturns5xxError(): void
    {
        $token = 'TEST-33333-a-b-c';

        $this->requester
            ->shouldReceive('get')
            ->once()
            ->andReturn($this->makeResponse(503, []));

        $result = $this->validator->validate($token);

        $this->assertFalse($result['valid']);
        $this->assertSame('service_unavailable', $result['reason']);
    }
}
