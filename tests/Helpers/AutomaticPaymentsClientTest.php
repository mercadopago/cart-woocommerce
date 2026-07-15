<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class AutomaticPaymentsClientTest extends TestCase
{
    /**
     * @var AutomaticPaymentsClient
     */
    private $client;

    /**
     * @var Mockery\MockInterface|Requester
     */
    private $requesterMock;

    /**
     * @var Mockery\MockInterface|SubscriptionsHelper
     */
    private $subscriptionsHelperMock;

    /**
     * @var Mockery\MockInterface|Logs
     */
    private $logsMock;

    /**
     * @var Mockery\MockInterface|File
     */
    private $fileTransportMock;

    public function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);

        $this->requesterMock           = Mockery::mock(Requester::class);
        $this->subscriptionsHelperMock = Mockery::mock(SubscriptionsHelper::class);
        $this->fileTransportMock       = Mockery::mock(File::class);

        $this->logsMock         = Mockery::mock(Logs::class);
        $this->logsMock->file   = $this->fileTransportMock;
        $this->logsMock->remote = Mockery::mock(Remote::class);

        $this->client = new AutomaticPaymentsClient(
            $this->requesterMock,
            $this->subscriptionsHelperMock,
            $this->logsMock
        );
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /* ───────────────────────── buildHeaders ───────────────────────── */

    public function testBuildHeadersLogsWarningWhenAccessTokenIsEmpty(): void
    {
        $this->fileTransportMock
            ->shouldReceive('warning')
            ->once()
            ->with(
                Mockery::pattern('/empty access_token/'),
                AutomaticPaymentsClient::LOG_SOURCE,
                []
            );

        $headers = $this->client->buildHeaders('');

        $this->assertSame('Bearer ', $headers['Authorization']);
        $this->addToAssertionCount(1);
    }

    public function testBuildHeadersContainsAllRequiredHeaders(): void
    {
        $headers = $this->client->buildHeaders('TEST-ACCESS-TOKEN');

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayHasKey('X-Platform-Id', $headers);
        $this->assertArrayHasKey('X-Product-Id', $headers);

        $this->assertSame('Bearer TEST-ACCESS-TOKEN', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame(MP_PLATFORM_ID, $headers['X-Platform-Id']);
        $this->assertSame(MP_PRODUCT_ID_DESKTOP, $headers['X-Product-Id']);
    }

    public function testBuildHeadersOmitsIdempotencyKeyWhenNotProvided(): void
    {
        $headers = $this->client->buildHeaders('TEST-ACCESS-TOKEN');

        $this->assertArrayNotHasKey('X-Idempotency-Key', $headers);
    }

    public function testBuildHeadersOmitsIdempotencyKeyForEmptyString(): void
    {
        $headers = $this->client->buildHeaders('TEST-ACCESS-TOKEN', '');

        $this->assertArrayNotHasKey('X-Idempotency-Key', $headers);
    }

    public function testBuildHeadersIncludesIdempotencyKeyWhenProvided(): void
    {
        $idemKey = '8a1f8e1f-91dc-4d9d-9c9f-1e2d3c4b5a6f';
        $headers = $this->client->buildHeaders('TEST-ACCESS-TOKEN', $idemKey);

        $this->assertSame($idemKey, $headers['X-Idempotency-Key']);
    }

    public function testBuildHeadersLogsWarningWhenPlatformIdIsEmpty(): void
    {
        $client = Mockery::mock(AutomaticPaymentsClient::class, [
            $this->requesterMock,
            $this->subscriptionsHelperMock,
            $this->logsMock,
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $client->shouldReceive('getPlatformId')->once()->andReturn('');

        $this->fileTransportMock
            ->shouldReceive('warning')
            ->once()
            ->with(
                Mockery::pattern('/MP_PLATFORM_ID is not defined/'),
                AutomaticPaymentsClient::LOG_SOURCE,
                []
            );

        $headers = $client->buildHeaders('TEST-ACCESS-TOKEN');

        $this->assertSame('', $headers['X-Platform-Id']);
    }

    /* ───────────────────────── log scrubs PII ───────────────────────── */

    public function testLogInfoStripsPiiKeysBeforeWritingThroughFileTransport(): void
    {
        $this->fileTransportMock
            ->shouldReceive('info')
            ->once()
            ->with(
                'op=cit status=approved',
                AutomaticPaymentsClient::LOG_SOURCE,
                Mockery::on(function ($context) {
                    return !isset($context['token'])
                        && !isset($context['email'])
                        && !isset($context['document'])
                        && !isset($context['last_four'])
                        && !isset($context['device_fingerprint'])
                        && ($context['op'] ?? null) === 'cit'
                        && ($context['http_status'] ?? null) === 201;
                })
            );

        $this->client->log('info', 'op=cit status=approved', [
            'op'                 => 'cit',
            'http_status'        => 201,
            'token'              => 'this-should-be-stripped',
            'email'              => 'buyer@example.com',
            'document'           => '12345678900',
            'last_four'          => '1234',
            'device_fingerprint' => 'abc-123',
        ]);

        // Mockery `shouldReceive` does not count as a PHPUnit assertion.
        $this->addToAssertionCount(1);
    }

    public function testLogScrubsPiiKeysCaseInsensitively(): void
    {
        // Keys like 'Authorization' (capital A) or 'TOKEN' must also be stripped —
        // buildHeaders() produces 'Authorization' with a bearer token, and that casing
        // must not bypass the PII filter if it ever ends up in a log context.
        $this->fileTransportMock
            ->shouldReceive('info')
            ->once()
            ->with(
                'op=cit',
                AutomaticPaymentsClient::LOG_SOURCE,
                Mockery::on(function ($context) {
                    return !isset($context['Authorization'])
                        && !isset($context['TOKEN'])
                        && !isset($context['Email'])
                        && ($context['op'] ?? null) === 'cit';
                })
            );

        $this->client->log('info', 'op=cit', [
            'op'            => 'cit',
            'Authorization' => 'Bearer should-be-stripped',
            'TOKEN'         => 'also-stripped',
            'Email'         => 'buyer@example.com',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testLogWarningRoutesThroughWarningTransportWithPiiScrubbed(): void
    {
        $this->fileTransportMock
            ->shouldReceive('warning')
            ->once()
            ->with(
                'op=mit credential_revoked=true',
                AutomaticPaymentsClient::LOG_SOURCE,
                Mockery::on(function ($context) {
                    return !isset($context['token'])
                        && ($context['op'] ?? null) === 'mit';
                })
            );

        $this->client->log('warning', 'op=mit credential_revoked=true', [
            'op'    => 'mit',
            'token' => 'should-be-stripped',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testLogDebugRoutesThroughDebugTransportWithPiiScrubbed(): void
    {
        $this->fileTransportMock
            ->shouldReceive('debug')
            ->once()
            ->with(
                'op=cit idem_key_prefix=abc123',
                AutomaticPaymentsClient::LOG_SOURCE,
                Mockery::on(function ($context) {
                    return !isset($context['email'])
                        && ($context['op'] ?? null) === 'cit';
                })
            );

        $this->client->log('debug', 'op=cit idem_key_prefix=abc123', [
            'op'    => 'cit',
            'email' => 'buyer@example.com',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testLogUnknownLevelEmitsAsWarning(): void
    {
        $this->fileTransportMock
            ->shouldReceive('warning')
            ->once()
            ->with(
                'op=cit unknown_level=critical',
                AutomaticPaymentsClient::LOG_SOURCE,
                Mockery::any()
            );

        $this->client->log('critical', 'op=cit unknown_level=critical', ['op' => 'cit']);

        $this->addToAssertionCount(1);
    }

    /* ───────────────────────── addPaymentMethod (3a) ───────────────────────── */

    public function testAddPaymentMethodPostsTokenAndSetAsDefaultThenExtractsNewDefaultCardId(): void
    {
        $subscriptionId = 'SUBSC-abc';
        $token          = 'TOKEN-xyz';
        $accessToken    = 'AT-1';
        $idemKey        = '8a1f8e1f-91dc-4d9d-9c9f-1e2d3c4b5a6f';
        $oldCardId      = '9876543210';

        $responsePayload = [
            'profile' => [
                'payment_methods' => [
                    ['card_id' => $oldCardId,    'default' => false],
                    ['card_id' => '9876543299',  'default' => true],
                ],
            ],
        ];

        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn($responsePayload);

        $expectedUriSuffix = '/subscriptions/' . rawurlencode($subscriptionId) . '/payment-methods';

        $this->requesterMock
            ->shouldReceive('post')
            ->once()
            ->with(
                Mockery::on(fn($path) => str_ends_with($path, $expectedUriSuffix)),
                Mockery::on(function ($headers) use ($idemKey) {
                    return ($headers['X-Idempotency-Key'] ?? null) === $idemKey;
                }),
                Mockery::on(function ($body) use ($token) {
                    return ($body['token'] ?? null) === $token
                        && ($body['set_as_default'] ?? null) === true;
                })
            )
            ->andReturn($response);

        $result = $this->client->addPaymentMethod($subscriptionId, $token, $accessToken, $idemKey, $oldCardId);

        $this->assertSame(200, $result['status']);
        $this->assertSame('9876543299', $result['new_card_id']);
    }

    public function testAddPaymentMethodReturnsNullNewCardIdWhenDefaultMatchesCurrentCardId(): void
    {
        // Edge case from §4.4: o token novo gerou o mesmo cartão já cadastrado.
        $subscriptionId = 'SUBSC-abc';
        $oldCardId      = '9876543210';

        $responsePayload = [
            'profile' => [
                'payment_methods' => [
                    ['card_id' => $oldCardId, 'default' => true],
                ],
            ],
        ];

        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn($responsePayload);

        $this->requesterMock->shouldReceive('post')->andReturn($response);

        $result = $this->client->addPaymentMethod($subscriptionId, 'TOKEN', 'AT', 'idem-key', $oldCardId);

        $this->assertNull($result['new_card_id']);
    }

    /* ───────────────────────── removePaymentMethod (3b) ───────────────────────── */

    public function testRemovePaymentMethodsSendsCardIdInUrlWithoutBody(): void
    {
        $subscriptionId = 'SUBSC-abc';
        $cardId         = '9876543210';

        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn(['profile' => ['payment_methods' => []]]);

        $expectedUriSuffix = '/subscriptions/' . rawurlencode($subscriptionId) . '/payment-methods/' . rawurlencode($cardId);

        $this->requesterMock
            ->shouldReceive('delete')
            ->once()
            ->with(
                Mockery::on(fn($path) => str_ends_with($path, $expectedUriSuffix)),
                Mockery::on(function ($headers) {
                    // AC-3: DELETE não envia X-Idempotency-Key
                    return !array_key_exists('X-Idempotency-Key', $headers);
                })
                // AC: sem body — o card_id vai na URL
            )
            ->andReturn($response);

        $result = $this->client->removePaymentMethod($subscriptionId, $cardId, 'AT-1');

        $this->assertSame(200, $result['status']);
        $this->assertNull($result['error']);
    }

    public function testRemovePaymentMethodsHandlesNullDataFromGetData(): void
    {
        // responseToArray: getData() returns null → return []
        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn(null);

        $this->requesterMock->shouldReceive('delete')->andReturn($response);

        $result = $this->client->removePaymentMethod('SUBSC', 'CARD-1', 'AT');

        $this->assertSame(200, $result['status']);
        $this->assertSame([], $result['data']);
    }

    public function testRemovePaymentMethodsReturnsEarlyWhenCardIdIsEmpty(): void
    {
        $this->fileTransportMock->shouldReceive('warning')->once();
        $this->requesterMock->shouldNotReceive('delete');

        $result = $this->client->removePaymentMethod('SUBSC', '', 'AT');

        $this->assertSame(0, $result['status']);
        $this->assertSame([], $result['data']);
        $this->assertNull($result['error']);
    }

    public function testRemovePaymentMethodsEncodesCardIdInUri(): void
    {
        // card_id with special characters must be rawurlencode'd in the URI.
        $cardId = 'CARD/with spaces&special=chars';

        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn([]);

        $this->requesterMock
            ->shouldReceive('delete')
            ->once()
            ->with(
                Mockery::on(fn($path) => str_ends_with($path, '/subscriptions/SUBSC/payment-methods/' . rawurlencode($cardId))),
                Mockery::type('array')
            )
            ->andReturn($response);

        $result = $this->client->removePaymentMethod('SUBSC', $cardId, 'AT');

        $this->assertSame(200, $result['status']);
    }

    public function testRemovePaymentMethodsFlagsLastPaymentMethod422(): void
    {
        $this->fileTransportMock->shouldReceive('warning')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(422);
        $response->shouldReceive('getData')->andReturn(['code' => 'LastPaymentMethod', 'message' => 'last']);

        $this->requesterMock->shouldReceive('delete')->andReturn($response);

        $result = $this->client->removePaymentMethod('SUBSC', 'CARD-1', 'AT');

        $this->assertSame(422, $result['status']);
        $this->assertSame('last_payment_method', $result['error']);
    }

    public function testRemovePaymentMethodsFlagsCannotRemoveDefault422AsCriticalError(): void
    {
        // CannotRemoveDefault é bug crítico (§4.5) — deve logar como 'error'.
        $this->fileTransportMock->shouldReceive('error')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(422);
        $response->shouldReceive('getData')->andReturn(['code' => 'CannotRemoveDefault', 'message' => 'default']);

        $this->requesterMock->shouldReceive('delete')->andReturn($response);

        $result = $this->client->removePaymentMethod('SUBSC', 'CARD-1', 'AT');

        $this->assertSame('cannot_remove_default', $result['error']);
    }

    /* ───────────────────────── addPaymentMethod — branches extras ───────────────────────── */

    public function testAddPaymentMethodReturnsErrorStatusWhenApiReturns4xx(): void
    {
        $this->fileTransportMock->shouldReceive('warning')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(422);
        $response->shouldReceive('getData')->andReturn(['code' => 'PaymentRejected', 'message' => 'rejected']);

        $this->requesterMock->shouldReceive('post')->andReturn($response);

        $result = $this->client->addPaymentMethod('SUBSC', 'TOK', 'AT', 'idem');

        $this->assertSame(422, $result['status']);
        $this->assertNull($result['new_card_id']);
    }

    /* ───────────────────────── removePaymentMethod — branches extras ───────────────────────── */

    /* ───────────── extractDefaultCardId via addPaymentMethod — branches internos ───────────── */

    public function testAddPaymentMethodReturnsNullWhenPaymentMethodsIsNotArray(): void
    {
        // payment_methods é um objeto/escalar → extractDefaultCardId retorna null
        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn(['profile' => ['payment_methods' => 'not-an-array']]);

        $this->requesterMock->shouldReceive('post')->andReturn($response);

        $result = $this->client->addPaymentMethod('SUBSC', 'TOK', 'AT', 'idem');
        $this->assertNull($result['new_card_id']);
    }

    public function testAddPaymentMethodSkipsNonArrayPmEntries(): void
    {
        // payment_methods contém um elemento não-array → ignorado, retorna null
        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn([
            'profile' => ['payment_methods' => ['not-an-array-element']],
        ]);

        $this->requesterMock->shouldReceive('post')->andReturn($response);

        $result = $this->client->addPaymentMethod('SUBSC', 'TOK', 'AT', 'idem');
        $this->assertNull($result['new_card_id']);
    }

    public function testAddPaymentMethodSkipsPmWithEmptyCardId(): void
    {
        // PM com default=true mas card_id vazio → ignorado
        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn([
            'profile' => ['payment_methods' => [
                ['default' => true, 'card_id' => ''],
            ]],
        ]);

        $this->requesterMock->shouldReceive('post')->andReturn($response);

        $result = $this->client->addPaymentMethod('SUBSC', 'TOK', 'AT', 'idem');
        $this->assertNull($result['new_card_id']);
    }

    /* ───────────── classifyRemoveError — código 422 sem match ───────────── */

    public function testRemovePaymentMethodsReturnsNullErrorOn422WithUnknownCode(): void
    {
        // 422 com código que não é LastPaymentMethod nem CannotRemoveDefault
        $this->fileTransportMock->shouldReceive('warning')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(422);
        $response->shouldReceive('getData')->andReturn(['code' => 'SomeOtherError']);

        $this->requesterMock->shouldReceive('delete')->andReturn($response);

        $result = $this->client->removePaymentMethod('SUBSC', 'CARD', 'AT');
        $this->assertNull($result['error']);
    }

    public function testRemovePaymentMethodsReturnsNullErrorOn422WithEmptyCode(): void
    {
        // 422 sem código → classifyRemoveError retorna null
        $this->fileTransportMock->shouldReceive('warning')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(422);
        $response->shouldReceive('getData')->andReturn([]);

        $this->requesterMock->shouldReceive('delete')->andReturn($response);

        $result = $this->client->removePaymentMethod('SUBSC', 'CARD', 'AT');
        $this->assertNull($result['error']);
    }

    public function testRemovePaymentMethodsReturnsNullErrorOnNon422(): void
    {
        $this->fileTransportMock->shouldReceive('warning')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(500);
        $response->shouldReceive('getData')->andReturn([]);

        $this->requesterMock->shouldReceive('delete')->andReturn($response);

        $result = $this->client->removePaymentMethod('SUBSC', 'CARD-1', 'AT');

        $this->assertSame(500, $result['status']);
        $this->assertNull($result['error']);
    }

    public function testRemovePaymentMethodsHandlesStringResponseFromGetData(): void
    {
        // responseToArray fallback: getData() returns a JSON string instead of array
        $this->fileTransportMock->shouldReceive('info')->once();

        $json = json_encode(['profile' => ['payment_methods' => []]]);
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn($json);

        $this->requesterMock->shouldReceive('delete')->andReturn($response);

        $result = $this->client->removePaymentMethod('SUBSC', 'CARD-1', 'AT');

        $this->assertSame(200, $result['status']);
        $this->assertNull($result['error']);
    }

    public function testLogErrorRoutesThroughErrorTransport(): void
    {
        $this->fileTransportMock
            ->shouldReceive('error')
            ->once()
            ->with(
                'op=mit http_status=422 code=CPP_TAAP_0602002',
                AutomaticPaymentsClient::LOG_SOURCE,
                Mockery::on(function ($context) {
                    return ($context['http_status'] ?? null) === 422
                        && ($context['code'] ?? null) === 'CPP_TAAP_0602002';
                })
            );

        $this->client->log('error', 'op=mit http_status=422 code=CPP_TAAP_0602002', [
            'http_status' => 422,
            'code'        => 'CPP_TAAP_0602002',
        ]);

        $this->addToAssertionCount(1);
    }

    /* ───────────────────────── cit() ───────────────────────── */

    public function testCitReturnsResponseAndLogsApprovedOnSuccess(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(101);

        $responseData = [
            'payment'      => ['id' => 'MOCK-PAY-CIT-1001', 'status' => 'approved'],
            'subscription' => ['id' => 'CPP-WSUB-1001', 'external_id' => 'WC-SUB-789'],
        ];
        $response = new Response();
        $response->setStatus(201);
        $response->setData($responseData);

        $this->subscriptionsHelperMock->shouldReceive('buildCitSeed')->with($order, 'card-tok')->andReturn('cit:101:1700000000:card-tok_12345');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->with('cit:101:1700000000:card-tok_12345')->andReturn('test-idem-key-uuid');
        $this->requesterMock
            ->shouldReceive('post')
            ->once()
            ->with(
                Mockery::on(fn($path) => str_ends_with($path, '/intents/cit')),
                Mockery::on(fn($h) => isset($h['X-Idempotency-Key']) && $h['X-Idempotency-Key'] === 'test-idem-key-uuid'),
                Mockery::any()
            )
            ->andReturn($response);

        $this->fileTransportMock->shouldReceive('info')->twice();

        $result = $this->client->cit('TEST-TOKEN', $order, ['token' => 'card-tok', 'transaction' => ['amount' => 49.90]]);

        $this->assertSame($response, $result);
        $this->assertSame('CPP-WSUB-1001', $result->getData()['subscription']['id']);
    }

    public function testCitThrowsRuntimeExceptionWhenSubscriptionIdMissingOrphanDetection(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(102);

        $responseData = ['payment' => ['id' => 'MOCK-PAY-ORPHAN'], 'subscription' => []];
        $response = new Response();
        $response->setStatus(201);
        $response->setData($responseData);

        $this->subscriptionsHelperMock->shouldReceive('buildCitSeed')->andReturn('cit:102:1700000001');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('test-idem-key-orphan');
        $this->subscriptionsHelperMock->shouldReceive('mapApiErrorToUserMessage')
            ->with(201, 'OrphanPayment')
            ->andReturn('Erro interno. Tente novamente.');

        $this->requesterMock->shouldReceive('post')->andReturn($response);

        $this->fileTransportMock->shouldReceive('info')->once();
        $this->fileTransportMock->shouldReceive('error')->once()->with(
            Mockery::pattern('/orphan_detected/'),
            AutomaticPaymentsClient::LOG_SOURCE,
            Mockery::any()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Erro interno. Tente novamente.');

        $this->client->cit('TEST-TOKEN', $order, []);
    }

    /**
     * 4xx response: orphan detection must NOT fire — subscription.id is naturally absent
     * on error responses. cit() returns the response so the handler can map the error.
     */
    public function testCitDoesNotThrowOrphanOnHttpErrorResponse(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(104);

        $response = new Response();
        $response->setStatus(422);
        $response->setData(['error' => 'PaymentRejected', 'message' => 'Insufficient funds']);

        $this->subscriptionsHelperMock->shouldReceive('buildCitSeed')->andReturn('cit:104:ts');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-422');

        $this->requesterMock->shouldReceive('post')->andReturn($response);
        $this->fileTransportMock->shouldReceive('info')->once();

        $result = $this->client->cit('TEST-TOKEN', $order, []);

        $this->assertSame(422, $result->getStatus());
    }

    public function testCitPropagatesExceptionFromRequesterWithoutLoggingApproved(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(103);

        $this->subscriptionsHelperMock->shouldReceive('buildCitSeed')->andReturn('cit:103:1700000002');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('test-idem-key-422');

        $this->requesterMock->shouldReceive('post')->andThrow(new \Exception('HTTP 422 PaymentRejected'));

        $this->fileTransportMock->shouldReceive('info')->once();
        $this->fileTransportMock->shouldReceive('info')->with(Mockery::pattern('/status=approved/'), Mockery::any(), Mockery::any())->never();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('HTTP 422 PaymentRejected');

        $this->client->cit('TEST-TOKEN', $order, []);
    }

    /* ───────────────────────── mit ───────────────────────── */

    public function testMitSuccessReturnsStatusAndData(): void
    {
        $payload = [
            'subscription' => ['id' => 'sub-123'],
            'transaction'  => ['external_reference' => 'order-456'],
        ];
        $idempotencyKey = '8a1f8e1f-91dc-4d9d-9c9f-1e2d3c4b5a6f';
        $responseData   = ['id' => 'intent-789', 'status' => 'approved'];

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(201);
        $responseMock->shouldReceive('getData')->andReturn($responseData);

        $this->requesterMock
            ->shouldReceive('post')
            ->once()
            ->with(Mockery::on(fn($path) => str_ends_with($path, '/intents/mit')), Mockery::type('array'), $payload)
            ->andReturn($responseMock);

        $this->fileTransportMock->shouldReceive('info')->twice()->withAnyArgs();

        $result = $this->client->mit('TEST-ACCESS-TOKEN', $payload, $idempotencyKey);

        $this->assertSame(201, $result['status']);
        $this->assertFalse($result['credential_revoked']);
        $this->assertSame($responseData, $result['data']);
    }

    public function testMitSendsIdempotencyKeyInHeaders(): void
    {
        $idempotencyKey = '8a1f8e1f-91dc-4d9d-9c9f-1e2d3c4b5a6f';
        $payload        = [
            'subscription' => ['id' => 'sub-123'],
            'transaction'  => ['external_reference' => 'order-456'],
        ];

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(201);
        $responseMock->shouldReceive('getData')->andReturn([]);

        $this->requesterMock
            ->shouldReceive('post')
            ->once()
            ->with(
                Mockery::on(fn($path) => str_ends_with($path, '/intents/mit')),
                Mockery::on(function ($headers) use ($idempotencyKey) {
                    return ($headers['X-Idempotency-Key'] ?? null) === $idempotencyKey;
                }),
                Mockery::any()
            )
            ->andReturn($responseMock);

        $this->fileTransportMock->shouldReceive('info')->twice()->withAnyArgs();

        $this->client->mit('TEST-ACCESS-TOKEN', $payload, $idempotencyKey);

        $this->addToAssertionCount(1);
    }

    public function testMitReturnsCredentialRevokedOnHttp401(): void
    {
        $payload = [
            'subscription' => ['id' => 'sub-123'],
            'transaction'  => ['external_reference' => 'order-456'],
        ];

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(401);
        $responseMock->shouldReceive('getData')->andReturn([]);

        $this->requesterMock
            ->shouldReceive('post')
            ->once()
            ->andReturn($responseMock);

        $this->fileTransportMock->shouldReceive('info')->once()->withAnyArgs();
        $this->fileTransportMock->shouldReceive('error')->once()->withAnyArgs();

        $result = $this->client->mit('TEST-ACCESS-TOKEN', $payload, 'idem-key-401');

        $this->assertSame(401, $result['status']);
        $this->assertTrue($result['credential_revoked']);
    }

    public function testMitReturnsCredentialRevokedOnHttp403(): void
    {
        $payload = [
            'subscription' => ['id' => 'sub-123'],
            'transaction'  => ['external_reference' => 'order-456'],
        ];

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(403);
        $responseMock->shouldReceive('getData')->andReturn([]);

        $this->requesterMock
            ->shouldReceive('post')
            ->once()
            ->andReturn($responseMock);

        $this->fileTransportMock->shouldReceive('info')->once()->withAnyArgs();
        $this->fileTransportMock->shouldReceive('error')->once()->withAnyArgs();

        $result = $this->client->mit('TEST-ACCESS-TOKEN', $payload, 'idem-key-403');

        $this->assertSame(403, $result['status']);
        $this->assertTrue($result['credential_revoked']);
    }

    /* ───────────────────────── deleteSubscription ───────────────────────── */

    public function testDeleteSubscriptionReturnsSuccessOn204(): void
    {
        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(204);

        $this->requesterMock
            ->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn($path) => str_ends_with($path, '/subscriptions/CPP-WSUB-abc123')), Mockery::type('array'))
            ->andReturn($responseMock);

        $this->fileTransportMock->shouldReceive('info')->twice()->withAnyArgs();

        $result = $this->client->deleteSubscription('TEST-ACCESS-TOKEN', 'CPP-WSUB-abc123');

        $this->assertSame(204, $result['status']);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['not_found']);
    }

    public function testDeleteSubscriptionReturnsNotFoundOn404(): void
    {
        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(404);

        $this->requesterMock
            ->shouldReceive('delete')
            ->once()
            ->andReturn($responseMock);

        $this->fileTransportMock->shouldReceive('info')->once()->withAnyArgs();
        $this->fileTransportMock->shouldReceive('warning')->once()->withAnyArgs();

        $result = $this->client->deleteSubscription('TEST-ACCESS-TOKEN', 'CPP-WSUB-abc123');

        $this->assertSame(404, $result['status']);
        $this->assertFalse($result['success']);
        $this->assertTrue($result['not_found']);
    }

    public function testDeleteSubscriptionPropagatesErrorStatusOn5xx(): void
    {
        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(500);

        $this->requesterMock
            ->shouldReceive('delete')
            ->once()
            ->andReturn($responseMock);

        $this->fileTransportMock->shouldReceive('info')->once()->withAnyArgs();
        $this->fileTransportMock->shouldReceive('error')->once()->withAnyArgs();

        $result = $this->client->deleteSubscription('TEST-ACCESS-TOKEN', 'CPP-WSUB-abc123');

        $this->assertSame(500, $result['status']);
        $this->assertFalse($result['success']);
        $this->assertFalse($result['not_found']);
    }

    public function testDeleteSubscriptionDoesNotSendIdempotencyKeyInHeaders(): void
    {
        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(204);

        $this->requesterMock
            ->shouldReceive('delete')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(function ($headers) {
                    return !array_key_exists('X-Idempotency-Key', $headers);
                })
            )
            ->andReturn($responseMock);

        $this->fileTransportMock->shouldReceive('info')->twice()->withAnyArgs();

        $this->client->deleteSubscription('TEST-ACCESS-TOKEN', 'CPP-WSUB-abc123');

        $this->addToAssertionCount(1);
    }

    /* ───────────── responseToArray — getData() retorna null ───────────── */

    public function testAddPaymentMethodHandlesNullGetDataGracefully(): void
    {
        $this->fileTransportMock->shouldReceive('info')->once();

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn(null);

        $this->requesterMock->shouldReceive('post')->andReturn($response);

        $result = $this->client->addPaymentMethod('SUBSC', 'TOK', 'AT', 'idem');

        $this->assertSame(200, $result['status']);
        $this->assertNull($result['new_card_id']);
        $this->assertSame([], $result['data']);
    }
}
