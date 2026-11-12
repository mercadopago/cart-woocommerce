<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\RealSmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\SmartPixOrderDetails;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixEnvironment;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_Order_Item_Product;
use WP_Mock;

class RealSmartPixOrderClientTest extends TestCase
{
    /** @dataProvider contractDiagnosticProvider */
    public function testHttpSuccessReportsTheExactRejectedFieldOnlyInServerDiagnostics(bool $query, string $field, string $expected): void
    {
        $message = '';
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->withArgs(function ($name, $value, $details, $diagnostic) use (&$message): bool {
            $message = $diagnostic;
            $this->assertSame('smart_pix_server_error', $name);
            $this->assertSame('invalid_contract', $details['category']);
            return true;
        });
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $raw = $this->pending();
        $raw[$field] = 'PRIVATE_DIAGNOSTIC_SENTINEL';
        $raw['payer'] = ['email' => 'buyer@example.test', 'token' => 'PRIVATE_DIAGNOSTIC_SENTINEL'];
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse($query ? 200 : 201, ['x-request-id' => 'contract-error-request'], '', $raw));
        $result = $query ? $client->query($this->request(true)) : $client->create($this->request(), $this->payment());
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertStringContainsString($query ? 'get.order_read' : 'create.create_request', $message);
        $this->assertStringContainsString($expected, substr($message, 0, 180));
        $this->assertStringNotContainsString('PRIVATE_DIAGNOSTIC_SENTINEL', $message);
        $this->assertStringNotContainsString('buyer@example.test', $message);
        $this->assertStringNotContainsString($expected, json_encode($result));
        $this->assertSame('contract-error-request', $client->requestDetails()['request_id']);
    }

    public static function contractDiagnosticProvider(): array
    {
        return [[true, 'currency', 'order.currency must be BRL'], [false, 'currency', 'order.currency must be BRL'],
            [true, 'total_paid_amount', 'order.total_paid_amount must be 0.00'],
            [false, 'total_amount', 'order.total_amount must be a positive decimal string']];
    }

    use SmartPixOrderDetailsFixture;

    /** Marks a request key to remove in data providers. */
    private const UNSET = "\0unset";

    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        WP_Mock::userFunction('get_site_url', ['return' => 'https://store.example.test']);
        WP_Mock::userFunction('home_url', ['return' => 'https://store.example.test']);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testCreationAdmissionRunsBeforeTransportButNotForLocalValidationFailures(): void
    {
        [$client, $http] = $this->client();
        $events = [];
        $http->shouldReceive('request')->once()->andReturnUsing(function () use (&$events) {
            $events[] = 'http';
            return new HttpResponse(201, [], '', $this->pending());
        });
        $callback = static function () use (&$events): void { $events[] = 'admitted'; };
        $client->create([], $this->payment(), $callback);
        $this->assertSame([], $events);
        $client->create($this->request(), $this->payment(), $callback);
        $this->assertSame(['admitted', 'http'], $events);
    }

    public function testARejectedAdmissionNeverReachesTheHttpTransport(): void
    {
        [$client, $http] = $this->client();
        $http->shouldNotReceive('request');
        $this->expectException(\RuntimeException::class);
        $client->create($this->request(), $this->payment(), static function (): void {
            throw new \RuntimeException('already_started');
        });
    }

    public function testQueryResponseUsesItsOwnRequestIdAndMissingHeadersClearThePreviousOne(): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->times(3)->andReturn(
            new HttpResponse(201, ['X-Request-Id' => 'create-request'], '', $this->pending()),
            new HttpResponse(503, ['x-request-id' => 'get-request'], '', []),
            new HttpResponse(429, [], '', [])
        );
        $client->create($this->request(), $this->payment());
        $this->assertSame('create-request', $client->requestDetails()['request_id']);
        $client->query($this->request(true));
        $this->assertSame(['api_route' => 'GET /plugins-platforms/v1/orders/{id}',
            'request_id' => 'get-request'], $client->requestDetails());
        $this->assertSame('create-request', $client->creationRequestId());
        $client->query($this->request(true));
        $this->assertArrayNotHasKey('request_id', $client->requestDetails());
    }

    public function testCreationRefusalPreservesOriginalCauseAndCurrentCorrelationOnlyInServerTelemetry(): void
    {
        $context = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext();
        $context->observeSdkInstance('sdk-original');
        $sink = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixCoreMonitorMetricSink(
            'homol', '9.8.1', '8.9.5', $context, '12345', 'https://store.example.test'
        );
        $sent = [];
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $options) use (&$sent): array {
            $sent = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertStringEndsWith('/smart_pix_server_error', $url);
            return [];
        });
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink, null, $context));
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(422, ['x-request-id' => 'create-refused'], '', [
            'error' => 'invalid_order', 'message' => 'Consent expired for SYNTHETIC_PROOF',
            'cause' => [['code' => 'CONSENT_EXPIRED', 'description' => 'A new authorization is required.']],
            'payer' => ['email' => 'private@example.test'],
        ]));

        $result = $client->create($this->request(), $this->payment());

        $this->assertSame('validation', $result['error']['category']);
        $this->assertStringNotContainsString('CONSENT_EXPIRED', (string) json_encode($result));
        $this->assertStringContainsString('CONSENT_EXPIRED', $sent['message']);
        $this->assertStringContainsString('Consent expired for [REDACTED]', $sent['message']);
        $this->assertStringNotContainsString('SYNTHETIC_PROOF', $sent['message']);
        $this->assertStringNotContainsString('private@example.test', $sent['message']);
        $this->assertSame('create-refused', $sent['details']['request_id']);
        $this->assertSame('sdk-original', $sent['details']['sdk_instance_id']);
        $this->assertSame('12345', $sent['details']['cust_id']);
        $this->assertSame('https://store.example.test', $sent['platform']['url']);
        $this->assertSame('create_processing_failed', $sent['details']['event']);
    }

    public function testCreateUsesThePpCurlContractAndPreservesExactServerAmount(): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->withArgs(function (string $method, string $path, array $headers, string $body): bool {
            $this->assertSame('POST', $method);
            $this->assertSame('/plugins-platforms/v1/orders', $path);
            $this->assertSame([
                'Authorization' => 'Bearer SYNTHETIC_CREDENTIAL', 'Content-Type' => 'application/json',
                'Accept' => 'application/json', 'X-Platform-Id' => MP_PLATFORM_ID,
                'X-Product-Id' => MP_PRODUCT_ID_DESKTOP, 'X-Idempotency-Key' => 'SYNTHETIC_KEY',
            ], $headers);
            $this->assertSame(json_encode([
                'processing_mode' => 'automatic', 'type' => 'online', 'description' => 'Synthetic product x 1',
                'external_reference' => 'WC-321', 'transaction_amount' => '125.50', 'sponsor_id' => '208686191',
                'notification_url' => 'https://store.example.test/wc-api/WC_WooMercadoPago_Pix_Gateway/?source_news=webhooks',
                'ip_address' => '203.0.113.10',
                'payments' => [[
                    'amount' => '125.50', 'date_of_expiration' => 'PT15M', 'payment_method' => [
                        'id' => 'smart_transfer', 'type' => 'smart_transfer', 'token' => 'SYNTHETIC_PROOF',
                        'open_finance_account_id' => '00000000-0000-4000-8000-000000000000',
                    ],
                ]],
                'payer' => [
                    'email' => 'buyer@example.test', 'first_name' => 'Synthetic', 'last_name' => 'Buyer',
                    'identification' => ['type' => 'CPF', 'number' => '12345678909'],
                ],
                'items' => [[
                    'id' => '321', 'title' => 'Consolidated Items', 'unit_price' => '125.50', 'quantity' => 1, 'type' => 'others',
                ]],
                'store' => ['id' => 'WC-', 'url' => 'https://store.example.test'],
                'seller' => ['referral_url' => 'https://store.example.test', 'website' => 'https://store.example.test'],
                'config' => ['capture' => true, 'binary_mode' => false, 'processing_mode' => 'automatic'],
                'metadata' => new \stdClass(),
            ]), $body);
            return true;
        })->andReturn(new HttpResponse(201, [], '', [
            'id' => 'ORDER_SYNTHETIC', 'external_reference' => 'WC-321', 'type' => 'online',
            'site_id' => 'MLB', 'currency' => 'BRL', 'total_amount' => '125.50', 'total_paid_amount' => '0.00',
            'status' => 'action_required', 'status_detail' => 'waiting_transfer',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC', 'amount' => '125.50', 'paid_amount' => '0.00',
                'status' => 'action_required', 'status_detail' => 'waiting_transfer',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]],
        ]));
        $result = $client->create($this->request(), $this->payment());
        $this->assertSame('success', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame('ORDER_SYNTHETIC', $result['order_reference']);
        $this->assertSame(['value' => '125.50', 'currency' => 'BRL'], $result['payments'][0]['amount']);
        $this->assertStringNotContainsString('SYNTHETIC_PROOF', json_encode($result));
    }

    /** @dataProvider discountedCreationProvider */
    public function testDiscountedCartSendsOneConsolidatedItemWithTheExactCharge(string $surface, string $amount): void
    {
        [$client, $http] = $this->client();
        $products = [];
        foreach (['Synthetic shirt', 'Synthetic book'] as $name) {
            $product = Mockery::mock(WC_Order_Item_Product::class);
            $product->shouldReceive('get_name')->andReturn($name);
            $product->shouldReceive('get_quantity')->andReturn(1);
            $product->shouldNotReceive('get_total');
            $product->shouldNotReceive('get_total_tax');
            $products[] = $product;
        }
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['items' => $products]);
        $request = $this->request();
        $request['surface'] = $surface;
        $request['amount']['value'] = $amount;
        $request['order_details'] = (new SmartPixOrderDetails())->build($order, $amount);
        $response = $this->pending();
        $response['total_amount'] = $amount;
        $response['transactions']['payments'][0]['amount'] = $amount;
        $http->shouldReceive('request')->once()->withArgs(function (string $method, string $path, array $headers, string $body) use ($amount): bool {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('POST', $method);
            $this->assertSame($amount, $payload['transaction_amount']);
            $this->assertSame($amount, $payload['payments'][0]['amount']);
            $this->assertSame('Synthetic shirt x 1, Synthetic book x 1', $payload['description']);
            $this->assertSame([[
                'id' => '321', 'title' => 'Consolidated Items', 'unit_price' => $amount, 'quantity' => 1, 'type' => 'others',
            ]], $payload['items']);
            $this->assertArrayNotHasKey('coupon_amount', $payload);
            $this->assertArrayNotHasKey('coupon_code', $payload);
            return true;
        })->andReturn(new HttpResponse(201, [], '', $response));

        $this->assertSame('success', $client->create($request, $this->payment())['outcome']);
    }

    public static function discountedCreationProvider(): array
    {
        return [
            'Classic percentage discount' => ['classic', '80.00'],
            'Blocks fixed discount with indivisible cents' => ['blocks', '79.99'],
            'Order Pay discount with shipping and taxes' => ['order_pay', '95.50'],
        ];
    }

    public function testCreateSendsNoIdentificationWhenTheOrderHasNoBillingCpf(): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->withArgs(function (string $method, string $path, array $headers, string $body): bool {
            $payload = json_decode($body, true);
            $this->assertSame('POST', $method);
            $this->assertSame('smart_transfer', $payload['payments'][0]['payment_method']['id']);
            $this->assertArrayNotHasKey('identification', $payload['payer']);
            $this->assertSame('buyer@example.test', $payload['payer']['email']);
            return true;
        })->andReturn(new HttpResponse(201, [], '', $this->pending()));
        $request = $this->request();
        unset($request['order_details']['payer']['identification']);

        $result = $client->create($request, $this->payment());

        $this->assertSame('success', $result['outcome']);
        $this->assertSame('processing', $result['state']);
    }

    public function testCreationReferencesAreCopiedFromTheValidatedResponseWithoutChangingThePublicContract(): void
    {
        [$client, $http] = $this->client();
        $data = $this->pending();
        $data['site_id'] = 'MLB';
        $data['payments'] = $data['transactions']['payments'];
        unset($data['transactions']);
        $data['payments'][0]['paid_amount'] = '0.00';
        $data['payments'][0]['references'] = [
            'order_id' => 'ORDSYNTHETIC001', 'transaction_intent_id' => 'syntheticintent001',
            'token' => 'SYNTHETIC_PRIVATE_PROOF',
        ];
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(201, [], '', $data));
        $result = $client->create($this->request(), $this->payment());
        $this->assertSame('success', $result['outcome']);
        $this->assertSame([
            'order_reference' => 'ORDER_SYNTHETIC', 'payment_reference' => 'PAYMENT_SYNTHETIC',
            'provider_order_reference' => 'ORDSYNTHETIC001', 'transaction_intent_reference' => 'syntheticintent001',
        ], $client->creationReferences());
        $this->assertStringNotContainsString('ORDSYNTHETIC001', json_encode($result));
        $this->assertStringNotContainsString('syntheticintent001', json_encode($result));
        $this->assertStringNotContainsString('PRIVATE', json_encode($client->creationReferences()));

        // A subsequent request that fails validation must never reuse these references.
        $client->create([], $this->payment());
        $this->assertNull($client->creationReferences());
    }

    public function testInvalidCreationCannotPublishUncorrelatedReferences(): void
    {
        [$client, $http] = $this->client();
        $data = $this->pending();
        $data['site_id'] = 'MLB';
        $data['external_reference'] = 'WC-999';
        $data['payments'] = $data['transactions']['payments'];
        unset($data['transactions']);
        $data['payments'][0]['references'] = ['order_id' => 'ORDOTHER001', 'transaction_intent_id' => 'syntheticintent001'];
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(201, [], '', $data));
        $this->assertSame('failure', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertNull($client->creationReferences());
    }

    /** @dataProvider createRouteProvider */
    public function testExplicitCreateReplayPreservesKeyBodyAndExactMoney(bool $test, string $base, string $surface): void
    {
        [$client, $http] = $this->client($test);
        $amount = '9007199254740993.01';
        $request = $this->request();
        $request['surface'] = $surface;
        $request['amount']['value'] = $amount;
        $request['order_details'] = $this->expectedOrderDetails($amount);
        $response = $this->pending();
        $response['site_id'] = 'MLB';
        $response['status'] = 'action_required';
        $response['status_detail'] = 'waiting_transfer';
        $response['total_amount'] = $amount;
        $response['payments'] = $response['transactions']['payments'];
        $response['payments'][0]['amount'] = $amount;
        $response['payments'][0]['paid_amount'] = '0.00';
        $response['payments'][0]['status'] = 'action_required';
        $response['payments'][0]['status_detail'] = 'waiting_transfer';
        unset($response['transactions']);
        $firstHeaders = null;
        $firstBody = null;
        $http->shouldReceive('request')->times(2)->withArgs(
            function (string $method, string $path, array $headers, string $body) use ($base, $amount, &$firstHeaders, &$firstBody): bool {
                $this->assertSame('POST', $method);
                $this->assertSame($base . '/v1/orders', $path);
                $this->assertSame('SYNTHETIC_KEY', $headers['X-Idempotency-Key']);
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame('208686191', $decoded['sponsor_id']);
                $this->assertSame($amount, $decoded['transaction_amount']);
                $this->assertSame($amount, $decoded['payments'][0]['amount']);
                $this->assertArrayNotHasKey('total_amount', $decoded);
                $this->assertArrayNotHasKey('transactions', $decoded);
                $this->assertArrayNotHasKey('surface', $decoded);
                if ($firstBody === null) {
                    $firstHeaders = $headers;
                    $firstBody = $body;
                } else {
                    $this->assertSame($firstHeaders, $headers);
                    $this->assertSame($firstBody, $body);
                }
                return true;
            }
        )->andReturn(new HttpResponse(201, [], '', $response), new HttpResponse(200, [], '', $response));
        $payment = $this->payment();
        $first = $client->create($request, $payment);
        $this->assertSame('success', $first['outcome']);
        $this->assertSame(['value' => $amount, 'currency' => 'BRL'], $first['payments'][0]['amount']);
        $this->assertSame($first, $client->create($request, $payment));
    }

    public static function createRouteProvider(): array
    {
        return [
            [true, '/plugins-platforms', 'classic'],
            [true, '/plugins-platforms', 'blocks'],
            [false, '/plugins-platforms', 'order_pay'],
        ];
    }

    /** @dataProvider readRouteProvider */
    public function testQueryUsesTheLinkedReferenceAndReadOnlyHeaders(bool $test, string $base, string $surface): void
    {
        [$client, $http] = $this->client($test);
        $request = $this->request(true);
        $request['surface'] = $surface;
        $http->shouldReceive('request')->once()->with('GET', $base . '/v1/orders/ORDER_SYNTHETIC', [
            'Authorization' => 'Bearer SYNTHETIC_CREDENTIAL', 'Accept' => 'application/json', 'X-Platform-Id' => MP_PLATFORM_ID,
        ])->andReturn(new HttpResponse(200, [], '', $this->pending()));
        $result = $client->query($request);
        $this->assertSame('success', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame('ORDER_SYNTHETIC', $result['order_reference']);
    }

    public static function readRouteProvider(): array
    {
        return [[true, '/plugins-platforms', 'server'], [false, '/plugins-platforms', 'order_pay']];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider developmentStoreModeProvider
     */
    public function testExplicitDevelopmentOverrideUsesHomologationForCreationAndQuery(bool $testMode): void
    {
        define('MP_SMART_PIX_ENV', SmartPixEnvironment::HOMOLOGATION);
        [$client, $http] = $this->client($testMode);
        $http->shouldReceive('request')->once()
            ->with('POST', '/homol/plugins-platforms/v1/orders', Mockery::type('array'), Mockery::on(function (string $body): bool {
                $this->assertSame('208686191', json_decode($body, true, 512, JSON_THROW_ON_ERROR)['sponsor_id']);
                return true;
            }))
            ->andReturn(new HttpResponse(201, [], '', $this->pending()));
        $http->shouldReceive('request')->once()
            ->with('GET', '/homol/plugins-platforms/v1/orders/ORDER_SYNTHETIC', Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->pending()));

        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertSame('success', $client->query($this->request(true))['outcome']);
    }

    public static function developmentStoreModeProvider(): array
    {
        return ['test mode' => [true], 'production mode' => [false]];
    }

    public function testAccreditedGetUsesLinkedPpOrderAndNormalizesProviderPayment(): void
    {
        [$client, $http] = $this->client();
        $response = [
            'id' => 'ORDER_SYNTHETIC', 'type' => 'online', 'external_reference' => 'WC-321',
            'site_id' => 'MLB', 'currency' => 'BRL', 'total_amount' => '125.5', 'total_paid_amount' => '125.5',
            'status' => 'processed', 'status_detail' => 'accredited',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC', 'amount' => '125.5', 'paid_amount' => '125.5',
                'status' => 'processed', 'status_detail' => 'accredited',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
                'references' => ['payment_id' => 'SYNTHETIC_PRIVATE_REFERENCE'],
            ]],
        ];
        $http->shouldReceive('request')->with('GET', '/plugins-platforms/v1/orders/ORDER_SYNTHETIC', [
            'Authorization' => 'Bearer SYNTHETIC_CREDENTIAL', 'Accept' => 'application/json', 'X-Platform-Id' => MP_PLATFORM_ID,
        ])->times(2)->andReturn(new HttpResponse(200, [], '', $response));
        $first = $client->query($this->request(true));
        $this->assertSame('terminal_success', $first['state']);
        $this->assertSame(['value' => '125.50', 'currency' => 'BRL'], $first['payments'][0]['amount']);
        $this->assertSame($first, $client->query($this->request(true)));
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE_REFERENCE', json_encode($first));
    }

    /** @dataProvider httpFailureProvider */
    public function testHttpFailuresRemainNormalizedAndKeepTheQueryReference(bool $query, int $status, string $category, bool $retryable): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse($status, [], '', ['message' => 'SYNTHETIC_PRIVATE']));
        $result = $query ? $client->query($this->request(true)) : $client->create($this->request(), $this->payment());
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame($category, $result['error']['category']);
        $this->assertSame($retryable, $result['error']['retryable']);
        $this->assertSame($retryable ? 'processing' : 'error', $result['state']);
        $this->assertSame($query ? 'ORDER_SYNTHETIC' : null, $result['order_reference']);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE', json_encode($result));
    }

    public function testTransientProviderQueryFailureKeepsLinkedOrderRecoverable(): void
    {
        [$client, $http] = $this->client();
        $approved = [
            'id' => 'ORDER_SYNTHETIC', 'type' => 'online', 'external_reference' => 'WC-321',
            'site_id' => 'MLB', 'currency' => 'BRL', 'total_amount' => '125.50', 'total_paid_amount' => '125.50',
            'status' => 'processed', 'status_detail' => 'accredited',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC', 'amount' => '125.50', 'paid_amount' => '125.50',
                'status' => 'processed', 'status_detail' => 'accredited',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]],
        ];
        $http->shouldReceive('request')
            ->with('GET', '/plugins-platforms/v1/orders/ORDER_SYNTHETIC', \Mockery::any())
            ->twice()
            ->andReturn(
                new HttpResponse(424, [], '', [
                    'code' => 'CPP_OA_0000008', 'status' => 424,
                    'message' => 'Failed to find order in database', 'original_message' => 'SYNTHETIC_PRIVATE',
                ]),
                new HttpResponse(200, [], '', $approved)
            );

        $transient = $client->query($this->request(true));
        $this->assertSame('failure', $transient['outcome']);
        $this->assertSame('processing', $transient['state']);
        $this->assertSame('unavailable', $transient['error']['category']);
        $this->assertTrue($transient['error']['retryable']);
        $this->assertSame('ORDER_SYNTHETIC', $transient['order_reference']);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE', json_encode($transient));

        $recovered = $client->query($this->request(true));
        $this->assertSame('terminal_success', $recovered['state']);
        $this->assertSame('ORDER_SYNTHETIC', $recovered['order_reference']);
    }

    public static function httpFailureProvider(): array
    {
        $shared = [[302, 'invalid_response', false], [400, 'validation', false], [401, 'authentication', false],
            [403, 'authentication', false], [404, 'unknown', false], [409, 'conflict', false],
            [429, 'rate_limit', true], [500, 'unavailable', true], [503, 'unavailable', true]];
        // Documented transient read failures stay recoverable for the linked Order.
        $queryOnly = [[418, 'unavailable', true], [422, 'unavailable', true], [424, 'unavailable', true]];
        // Create recovery for these documented failures awaits the provider policy;
        // pin the current final mapping so any change is deliberate.
        $createOnly = [[418, 'unknown', false], [422, 'validation', false], [423, 'unknown', false], [424, 'unknown', false]];
        $result = [];
        foreach ([false, true] as $query) {
            foreach (array_merge($shared, $query ? $queryOnly : $createOnly) as $case) {
                $result[] = array_merge([$query], $case);
            }
        }
        return $result;
    }

    /** @dataProvider indeterminateCreateResponseProvider */
    public function testSuccessfulHttpWithoutUsableOrderReferenceIsIndeterminateWithoutRetry(int $status, string $body, $data): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse($status, [], $body, $data));
        $result = $client->create($this->request(), $this->payment());
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
        $this->assertNull($result['order_reference']);
        $this->assertSame([], $result['payments']);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE', json_encode($result));
    }

    public static function indeterminateCreateResponseProvider(): array
    {
        $cases = [];
        foreach ([200, 201] as $status) {
            foreach (
                [['', null], ['SYNTHETIC_PRIVATE malformed JSON', null], ['{}', []], ['null', null],
                ['"SYNTHETIC_PRIVATE"', 'SYNTHETIC_PRIVATE'], ['', ['id' => '../invalid']]] as $response
            ) {
                $cases[] = array_merge([$status], $response);
            }
        }
        return $cases;
    }

    /** @dataProvider transportFailureProvider */
    public function testTransportFailuresAreIndeterminateAndDoNotRetry(bool $query, string $code, string $category): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->andThrow(new TransportException($code));
        $result = $query ? $client->query($this->request(true)) : $client->create($this->request(), $this->payment());
        $this->assertSame($category, $result['error']['category']);
        $this->assertTrue($result['error']['retryable']);
        $this->assertSame('processing', $result['state']);
    }

    public static function transportFailureProvider(): array
    {
        return [[false, 'timeout', 'timeout'], [true, 'request_timeout', 'timeout'],
            [false, 'http_request_failed', 'unavailable'], [true, 'http_request_failed', 'unavailable']];
    }

    /** @dataProvider invalidRequestProvider */
    public function testInvalidRequestsStopBeforeHttp(bool $query, string $case): void
    {
        [$client, $http] = $this->client();
        $http->shouldNotReceive('request');
        $request = $this->request($query);
        switch ($case) {
            case 'extra':
                $request['token'] = 'SYNTHETIC_PRIVATE';
                break;
            case 'wire_amount':
                $request['transaction_amount'] = '0.01';
                break;
            case 'wire_config':
                $request['config'] = ['capture' => false];
                break;
            case 'surface':
                $request['surface'] = 'unknown';
                break;
            case 'country':
                $request['country'] = 'MLA';
                break;
            case 'contract':
                $request['payment_contract'] = 'payments_v1';
                break;
            case 'attempt':
                $request['attempt_reference'] = '../321';
                break;
            case 'reference':
                $request['order_reference'] = 'https://example.test/order';
                break;
            case 'amount':
                $request['amount'] = $query ? ['value' => '125.50', 'currency' => 'BRL'] : ['value' => '0.00', 'currency' => 'BRL'];
                break;
            case 'float':
                $request['amount']['value'] = 125.50;
                break;
            case 'currency':
                $request['amount']['currency'] = 'USD';
                break;
            case 'refund':
                $request['refund_amount'] = ['value' => '1.00', 'currency' => 'BRL'];
                break;
        }
        $result = $query ? $client->query($request) : $client->create($request, $case === 'proof' ? null : $this->payment());
        $this->assertSame('validation', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
    }

    public static function invalidRequestProvider(): array
    {
        $result = [];
        foreach ([false, true] as $query) {
            foreach (['extra', 'surface', 'country', 'contract', 'attempt', 'reference', 'amount', 'refund'] as $case) {
                $result[] = [$query, $case];
            }
        }
        return array_merge($result, [[false, 'proof'], [false, 'float'], [false, 'currency'], [false, 'wire_amount'], [false, 'wire_config']]);
    }

    /** @dataProvider operationProvider */
    public function testMissingCredentialStopsBeforeHttp(bool $query): void
    {
        [$client, $http] = $this->client(true, '');
        $http->shouldNotReceive('request');
        $result = $query ? $client->query($this->request(true)) : $client->create($this->request(), $this->payment());
        $this->assertSame('authentication', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
    }

    public static function operationProvider(): array
    {
        return [[false], [true]];
    }

    /** @dataProvider operationProvider */
    public function testMismatchedResponseReferenceCannotBeLinked(bool $query): void
    {
        [$client, $http] = $this->client();
        $response = $this->pending();
        $response[$query ? 'id' : 'external_reference'] = 'OTHER_SYNTHETIC_REFERENCE';
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $response));
        $result = $query ? $client->query($this->request(true)) : $client->create($this->request(), $this->payment());
        $this->assertSame('linkage_mismatch', $result['error']['category']);
    }

    public function testInvalidUtf8ProofCannotProduceAnOutboundBody(): void
    {
        [$client, $http] = $this->client();
        $http->shouldNotReceive('request');
        $payment = new SmartPixAuthorizedPayment("\xB1", '00000000-0000-4000-8000-000000000000', 'SYNTHETIC_KEY');
        $this->assertSame('validation', $client->create($this->request(), $payment)['error']['category']);
    }

    public function testMalformedCancellationAndRefundNeverCallHttp(): void
    {
        [$client, $http] = $this->client();
        $http->shouldNotReceive('request');
        foreach (['cancel', 'refund'] as $method) {
            $result = $client->$method($this->request(true));
            $this->assertSame('failure', $result['outcome']);
            $this->assertFalse($result['error']['retryable']);
        }
    }

    public function testRefundOfAnUnpaidPreflightSnapshotNeverDispatchesAPost(): void
    {
        // A valid read emits no server error, so the refusal is the local approved-state guard.
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $http->shouldReceive('request')->once()
            ->with('GET', '/plugins-platforms/v1/orders/ORDER_SYNTHETIC', Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->pending()));
        $http->shouldNotReceive('request')->with('POST', Mockery::any(), Mockery::any(), Mockery::any());
        $dispatched = false;
        $request = $this->request(true);
        $request['refund_amount'] = ['value' => '125.50', 'currency' => 'BRL'];
        $request['operation_context'] = ['contract_version' => 'smart-pix-mutations/v1',
            'idempotency_key' => str_repeat('a', 64), 'seller_reference' => 'SYNTHETIC_SELLER'];

        $result = $client->refund($request, static function () use (&$dispatched): void {
            $dispatched = true;
        });

        $this->assertSame([
            'outcome' => 'failure', 'state' => 'error', 'order_reference' => 'ORDER_SYNTHETIC',
            'eligibility' => null, 'authorization' => null, 'payments' => [],
            'error' => ['category' => 'invalid_response', 'code' => 'SMART_PIX_INVALID_RESPONSE', 'retryable' => false],
        ], $result);
        $this->assertFalse($dispatched);
    }

    /** @dataProvider referenceBoundaryProvider */
    public function testOrdersReferenceBoundariesAreCheckedBeforeTransport($reference, bool $valid): void
    {
        [$client, $http] = $this->client();
        $request = $this->request(true);
        $request['order_reference'] = $reference;
        if ($valid) {
            $response = $this->pending();
            $response['id'] = $reference;
            $http->shouldReceive('request')->once()->with('GET', '/plugins-platforms/v1/orders/' . $reference, Mockery::type('array'))
                ->andReturn(new HttpResponse(200, [], '', $response));
        } else {
            $http->shouldNotReceive('request');
        }
        $result = $client->query($request);
        $this->assertSame($valid ? 'success' : 'failure', $result['outcome']);
        if ($valid) {
            $this->assertSame($reference, $result['order_reference']);
        } else {
            $this->assertSame('validation', $result['error']['category']);
        }
    }

    public static function referenceBoundaryProvider(): array
    {
        return [
            ['ABC', true], ['ORDER_A-1', true], [str_repeat('A', 128), true],
            ['AB', false], [str_repeat('A', 129), false], ['_ABC', false], ['../ABC', false],
            ['https://example.test/ABC', false], ["ABC\n", false], ['ABC/DEF', false], [null, false], [123, false],
        ];
    }

    public function testEmptySellerTokenStopsBeforeHttp(): void
    {
        [$client, $http] = $this->client(true, '');
        $http->shouldNotReceive('request');
        $this->assertSame('authentication', $client->create($this->request(), $this->payment())['error']['category']);
    }

    /** @dataProvider invalidPaymentProvider */
    public function testInvalidPaymentMaterialStopsBeforeHttp(string $token, string $account, string $key): void
    {
        [$client, $http] = $this->client();
        $http->shouldNotReceive('request');
        // Simulate a malformed internal object to exercise the adapter's own boundary.
        $reflection = new \ReflectionClass(SmartPixAuthorizedPayment::class);
        $payment = $reflection->newInstanceWithoutConstructor();
        foreach (['authorizedPseudotoken' => $token, 'openFinanceAccountId' => $account, 'idempotencyKey' => $key] as $field => $value) {
            $property = $reflection->getProperty($field);
            $property->setAccessible(true);
            $property->setValue($payment, $value);
        }
        $this->assertSame('validation', $client->create($this->request(), $payment)['error']['category']);
    }

    public static function invalidPaymentProvider(): array
    {
        $uuid = '00000000-0000-4000-8000-000000000000';
        return [['', $uuid, 'KEY'], ['PROOF', $uuid, ''], ['PROOF', 'invalid', 'KEY'], ['PROOF', $uuid . "\n", 'KEY']];
    }

    /**
     * @dataProvider invalidOrderDetailsProvider
     * @param list<string|int> $path
     * @param mixed            $value
     */
    public function testUnusableOrderDetailsStopBeforeHttp(array $path, $value): void
    {
        [$client, $http] = $this->client();
        $http->shouldNotReceive('request');
        $request = $this->request();
        $this->changeAt($request, $path, $value);

        $result = $client->create($request, $this->payment());

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('validation', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
        $this->assertStringNotContainsString('12345678909', json_encode($result));
    }

    public static function invalidOrderDetailsProvider(): array
    {
        $unset = self::UNSET;
        $item = ['id' => '10', 'title' => 'Synthetic product', 'unit_price' => '125.50', 'quantity' => 1, 'type' => 'others'];
        return [
            'caller supplied sponsor' => [['sponsor_id'], '999999'],
            'caller supplied sponsor in details' => [['order_details', 'sponsor_id'], '999999'],
            'create without order details' => [['order_details'], $unset],
            'nonarray order details' => [['order_details'], 'SYNTHETIC'],
            'extra details key' => [['order_details', 'shipments'], []],
            'missing description' => [['order_details', 'description'], $unset],
            'empty description' => [['order_details', 'description'], ''],
            'blank description' => [['order_details', 'description'], '   '],
            'nonstring description' => [['order_details', 'description'], 123],
            'missing address' => [['order_details', 'ip_address'], $unset],
            'invalid address' => [['order_details', 'ip_address'], 'not-an-ip'],
            'nonstring address' => [['order_details', 'ip_address'], 3405803786],
            'nonarray payer' => [['order_details', 'payer'], 'SYNTHETIC'],
            'extra payer key' => [['order_details', 'payer', 'phone'], '5511999999999'],
            'null identification' => [['order_details', 'payer', 'identification'], null],
            'invalid email' => [['order_details', 'payer', 'email'], 'not-an-email'],
            'nonstring email' => [['order_details', 'payer', 'email'], null],
            'empty first name' => [['order_details', 'payer', 'first_name'], ''],
            'blank last name' => [['order_details', 'payer', 'last_name'], '   '],
            'missing last name' => [['order_details', 'payer', 'last_name'], null],
            'extra identification key' => [['order_details', 'payer', 'identification', 'masked'], '123.456.789-09'],
            'missing identification number' => [['order_details', 'payer', 'identification', 'number'], $unset],
            'company identification' => [['order_details', 'payer', 'identification', 'type'], 'CNPJ'],
            'masked identification' => [['order_details', 'payer', 'identification', 'number'], '123.456.789-09'],
            'invalid identification' => [['order_details', 'payer', 'identification', 'number'], '52998224724'],
            'integer identification' => [['order_details', 'payer', 'identification', 'number'], 12345678909],
            'no items' => [['order_details', 'items'], []],
            'nonarray items' => [['order_details', 'items'], 'SYNTHETIC'],
            'keyed items' => [['order_details', 'items'], ['first' => $item]],
            'items list not starting at zero' => [['order_details', 'items'], [1 => $item]],
            'nonarray item' => [['order_details', 'items', 0], 'SYNTHETIC'],
            'extra item key' => [['order_details', 'items', 0, 'category_id'], 'others'],
            'missing item type' => [['order_details', 'items', 0, 'type'], $unset],
            'empty item id' => [['order_details', 'items', 0, 'id'], ''],
            'blank item title' => [['order_details', 'items', 0, 'title'], '   '],
            'one decimal unit price' => [['order_details', 'items', 0, 'unit_price'], '125.5'],
            'float unit price' => [['order_details', 'items', 0, 'unit_price'], 125.50],
            'leading zero unit price' => [['order_details', 'items', 0, 'unit_price'], '0125.50'],
            'three decimal unit price' => [['order_details', 'items', 0, 'unit_price'], '125.500'],
            'negative unit price' => [['order_details', 'items', 0, 'unit_price'], '-125.50'],
            'string quantity' => [['order_details', 'items', 0, 'quantity'], '1'],
            'float quantity' => [['order_details', 'items', 0, 'quantity'], 1.0],
            'zero quantity' => [['order_details', 'items', 0, 'quantity'], 0],
            'quantity above the decimal factor' => [['order_details', 'items', 0, 'quantity'], 1000000],
            'empty item type' => [['order_details', 'items', 0, 'type'], ''],
            'blank item type' => [['order_details', 'items', 0, 'type'], '   '],
            'nonstring item type' => [['order_details', 'items', 0, 'type'], 123],
            'null item type' => [['order_details', 'items', 0, 'type'], null],
            'oversized item type' => [['order_details', 'items', 0, 'type'], str_repeat('t', 257)],
            'items below the amount' => [['order_details', 'items', 0, 'unit_price'], '125.49'],
            'items above the amount' => [['order_details', 'items', 1], $item],
            'items multiplied above the amount' => [['order_details', 'items', 0, 'quantity'], 2],
        ];
    }

    public function testItemTypeAtTheLengthLimitIsSent(): void
    {
        [$client, $http] = $this->client();
        $type = str_repeat('t', 256);
        $request = $this->request();
        $request['order_details']['items'][0]['type'] = $type;
        $body = null;
        $this->expectCreate($http, $body);

        $this->assertSame('success', $client->create($request, $this->payment())['outcome']);
        $this->assertSame($type, $body['items'][0]['type']);
        $this->assertSame('125.50', $body['transaction_amount']);
    }

    public function testEmptyLastNameIsSent(): void
    {
        [$client, $http] = $this->client();
        $request = $this->request();
        $request['order_details']['payer']['last_name'] = '';
        $body = null;
        $this->expectCreate($http, $body);

        $this->assertSame('success', $client->create($request, $this->payment())['outcome']);
        $this->assertSame('', $body['payer']['last_name']);
    }

    public function testQueryCarryingOrderDetailsStopsBeforeHttp(): void
    {
        [$client, $http] = $this->client();
        $http->shouldNotReceive('request');
        $request = $this->request(true);
        $request['order_details'] = $this->expectedOrderDetails('125.50');

        $result = $client->query($request);

        $this->assertSame('validation', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
    }

    /** @dataProvider invalidStoreProvider */
    public function testUnusableStoreReferenceStopsBeforeHttp(string $storeId, string $siteUrl): void
    {
        $this->siteUrl($siteUrl);
        WP_Mock::userFunction('wc_get_logger', ['times' => 0]);
        [$client, $http] = $this->storeClient(['store_id' => $storeId]);
        $http->shouldNotReceive('request');

        $result = $client->create($this->request(), $this->payment());

        $this->assertSame('validation', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
        $this->assertNull($client->creationDurationSeconds());
    }

    public static function invalidStoreProvider(): array
    {
        return [
            'empty store id' => ['', 'https://store.example.test'],
            'blank store id' => ['   ', 'https://store.example.test'],
            'invalid site url' => ['WC-', 'not a url'],
            'empty site url' => ['WC-', ''],
            'relative site url' => ['WC-', '/store'],
        ];
    }

    public function testStoreReferenceIsTheTrimmedStoreIdentificator(): void
    {
        [$client, $http] = $this->storeClient(['store_id' => ' LOJA- ']);
        $body = null;
        $this->expectCreate($http, $body);

        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertSame(['id' => 'LOJA-', 'url' => 'https://store.example.test'], $body['store']);
    }

    /** @dataProvider sellerUrlProvider */
    public function testSellerUsesTheConfiguredStorefrontWithoutQueryOrFragment(string $homeUrl, string $expected): void
    {
        $this->siteUrl('https://admin.example.test/wordpress', $homeUrl);
        [$client, $http] = $this->client();
        $body = null;
        $this->expectCreate($http, $body);

        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertSame(['referral_url' => $expected, 'website' => $expected], $body['seller']);
        $this->assertSame('https://admin.example.test/wordpress', $body['store']['url']);
        $this->assertSame('PT15M', $body['payments'][0]['date_of_expiration']);
        $this->assertStringContainsString('source_news=webhooks', $body['notification_url']);
    }

    public static function sellerUrlProvider(): array
    {
        return [
            'public storefront differs from WordPress install' => ['https://shop.example.test/', 'https://shop.example.test/'],
            'query parameters' => ['https://shop.example.test/?q=1&campaign=synthetic', 'https://shop.example.test/'],
            'path port and fragment' => ['https://shop.example.test:8443/store/?q=4#checkout', 'https://shop.example.test:8443/store/'],
            'fragment before question mark' => ['https://shop.example.test/store#section?q=4', 'https://shop.example.test/store'],
        ];
    }

    /** @dataProvider invalidSellerUrlProvider */
    public function testInvalidSellerUrlStopsBeforeHttp(string $homeUrl): void
    {
        $this->siteUrl('https://store.example.test', $homeUrl);
        [$client, $http] = $this->client();
        $http->shouldNotReceive('request');

        $result = $client->create($this->request(), $this->payment());

        $this->assertSame('validation', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
        $this->assertNull($client->creationDurationSeconds());
    }

    public static function invalidSellerUrlProvider(): array
    {
        return [[''], ['not a url'], ['/store?q=1'], ['ftp://store.example.test']];
    }

    /** @dataProvider unavailableNotificationUrlProvider */
    public function testUnavailableNotificationUrlIsOmittedWithAFixedWarning(string $siteUrl, array $options): void
    {
        $this->siteUrl($siteUrl);
        if (($options['resolver'] ?? null) === 'default') {
            WP_Mock::userFunction('WC', ['return' => null]);
            $options['resolver'] = null;
        }
        $logger = Mockery::mock();
        $logger->shouldReceive('warning')->once()->with('Smart Pix notification URL unavailable.', [
            'source' => 'mercadopago-smart-pix', 'reason' => 'no_public_url',
        ]);
        WP_Mock::userFunction('wc_get_logger', ['times' => 1, 'return' => $logger]);
        [$client, $http] = $this->storeClient($options);
        $body = null;
        $this->expectCreate($http, $body);

        $result = $client->create($this->request(), $this->payment());

        $this->assertSame('success', $result['outcome']);
        $this->assertArrayNotHasKey('notification_url', $body);
        $this->assertSame('125.50', $body['transaction_amount']);
        $this->assertSame(['id' => 'WC-', 'url' => $siteUrl], $body['store']);
    }

    public static function unavailableNotificationUrlProvider(): array
    {
        $resolverUnused = static function (): string {
            throw new \LogicException('The API request URL must not be resolved.');
        };
        return [
            'localhost site' => ['http://localhost:8080', ['resolver' => $resolverUnused]],
            'woocommerce unavailable' => ['https://store.example.test', ['resolver' => 'default']],
            'empty api request url' => ['https://store.example.test', ['resolver' => static function (): string {
                return '';
            }]],
            'localhost custom domain' => ['https://store.example.test', [
                'custom_domain' => 'https://localhost/notify', 'resolver' => $resolverUnused,
            ]],
            'invalid custom domain' => ['https://store.example.test', [
                'custom_domain' => 'not a url', 'resolver' => $resolverUnused,
            ]],
        ];
    }

    /** @dataProvider loggerFailureProvider */
    public function testLoggingFailureCannotBlockOrChangeCreation(bool $loggerThrows): void
    {
        $this->siteUrl('http://localhost:8080');
        if ($loggerThrows) {
            $logger = Mockery::mock();
            $logger->shouldReceive('warning')->once()->andThrow(new \RuntimeException('SYNTHETIC_PRIVATE_LOGGER'));
            WP_Mock::userFunction('wc_get_logger', ['times' => 1, 'return' => $logger]);
        } else {
            WP_Mock::userFunction('wc_get_logger', ['times' => 1, 'return' => static function (): void {
                throw new \Error('SYNTHETIC_PRIVATE_LOGGER');
            }]);
        }
        [$client, $http] = $this->storeClient([]);
        $body = null;
        $this->expectCreate($http, $body);

        $result = $client->create($this->request(), $this->payment());

        $this->assertSame('success', $result['outcome']);
        $this->assertArrayNotHasKey('notification_url', $body);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE', json_encode($result));
    }

    public static function loggerFailureProvider(): array
    {
        return ['logger throws' => [true], 'logger factory throws' => [false]];
    }

    /** @dataProvider customDomainProvider */
    public function testCustomDomainBuildsThePixNotificationUrlWithoutWooCommerce(string $options, string $expected): void
    {
        WP_Mock::userFunction('wc_get_logger', ['times' => 0]);
        [$client, $http] = $this->storeClient([
            'custom_domain' => 'https://custom.example.test/notify',
            'custom_domain_options' => $options,
            'resolver' => static function (): string {
                throw new \LogicException('The API request URL must not be resolved.');
            },
        ]);
        $body = null;
        $this->expectCreate($http, $body);

        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertSame($expected, $body['notification_url']);
    }

    public static function customDomainProvider(): array
    {
        return [
            'with gateway query' => ['yes', 'https://custom.example.test/notify?wc-api=WC_WooMercadoPago_Pix_Gateway&source_news=webhooks'],
            'domain as configured' => ['no', 'https://custom.example.test/notify'],
        ];
    }

    public function testDefaultResolverRoutesToThePixWebhook(): void
    {
        WP_Mock::userFunction('wc_get_logger', ['times' => 0]);
        $woocommerce = Mockery::mock('WooCommerce');
        $woocommerce->shouldReceive('api_request_url')->once()->with('WC_WooMercadoPago_Pix_Gateway')
            ->andReturn('https://store.example.test/?wc-api=WC_WooMercadoPago_Pix_Gateway');
        WP_Mock::userFunction('WC', ['return' => $woocommerce]);
        [$client, $http] = $this->storeClient(['resolver' => null]);
        $body = null;
        $this->expectCreate($http, $body);

        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertSame(
            'https://store.example.test/?wc-api=WC_WooMercadoPago_Pix_Gateway&source_news=webhooks',
            $body['notification_url']
        );
    }

    /**
     * Expect one successful POST and decode its body into $body.
     * @param \Mockery\MockInterface $http
     */
    private function expectCreate($http, ?array &$body): void
    {
        $http->shouldReceive('request')->once()->withArgs(function (string $method, string $path, array $headers, string $raw) use (&$body): bool {
            $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return $method === 'POST';
        })->andReturn(new HttpResponse(201, [], '', $this->pending()));
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string|int>     $path
     * @param mixed                $value
     */
    private function changeAt(array &$values, array $path, $value): void
    {
        $key = array_shift($path);
        if ($path !== []) {
            $this->changeAt($values[$key], $path, $value);
        } elseif ($value === self::UNSET) {
            unset($values[$key]);
        } else {
            $values[$key] = $value;
        }
    }

    private function siteUrl(string $url, string $homeUrl = 'https://store.example.test'): void
    {
        WP_Mock::tearDown();
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        WP_Mock::userFunction('get_site_url', ['return' => $url]);
        WP_Mock::userFunction('home_url', ['return' => $homeUrl]);
    }

    /**
     * @param array<string, mixed> $options custom_domain, custom_domain_options, store_id and
     *                                      resolver (null selects the default WooCommerce resolver).
     */
    private function storeClient(array $options): array
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('SYNTHETIC_CREDENTIAL');
        $seller->shouldReceive('getCustIdFromAT')->andReturn('SYNTHETIC_SELLER');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andReturn(true);
        $store->shouldReceive('getCustomDomain')->andReturn($options['custom_domain'] ?? '');
        $store->shouldReceive('getCustomDomainOptions')->andReturn($options['custom_domain_options'] ?? 'yes');
        $store->shouldReceive('getStoreId')->andReturn($options['store_id'] ?? 'WC-');
        $resolver = array_key_exists('resolver', $options) ? $options['resolver'] : static function (): string {
            return 'https://store.example.test/wc-api/WC_WooMercadoPago_Pix_Gateway/';
        };
        return [new RealSmartPixOrderClient($http, $seller, $store, null, 'pending_query', $resolver), $http];
    }

    private function client(bool $test = true, string $credential = 'SYNTHETIC_CREDENTIAL', ?SmartPixTelemetry $telemetry = null, string $source = 'pending_query'): array
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn($credential);
        $seller->shouldReceive('getCustIdFromAT')->andReturn('SYNTHETIC_SELLER');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andReturn($test);
        $store->shouldReceive('getCustomDomain')->andReturn('');
        $store->shouldReceive('getCustomDomainOptions')->andReturn('yes');
        $store->shouldReceive('getStoreId')->andReturn('WC-');
        $apiRequestUrl = static function (): string {
            return 'https://store.example.test/wc-api/WC_WooMercadoPago_Pix_Gateway/';
        };
        return [new RealSmartPixOrderClient($http, $seller, $store, $telemetry, $source, $apiRequestUrl), $http];
    }

    public function testSuccessfulLinkedGetWithTelemetryEmitsNoServerErrors(): void
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $http->shouldReceive('request')->once()->with('GET', '/plugins-platforms/v1/orders/ORDER_SYNTHETIC', Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->pending()));
        $result = $client->query($this->request(true));
        $this->assertSame('success', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame(['value' => '125.50', 'currency' => 'BRL'], $result['payments'][0]['amount']);
    }

    /** @dataProvider telemetryHttpFailureProvider */
    public function testHttpErrorTelemetryUsesAuthoritativeStatusExactlyOnce(bool $query, int $status, string $category): void
    {
        $sink = $this->errorSink($query ? 'get' : 'create', $category, 'classic', $query ? 'pending_query' : 'sync_response');
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse($status, [], '', ['message' => 'SYNTHETIC_PRIVATE_RESPONSE']));
        $result = $query ? $client->query($this->request(true)) : $client->create($this->request(), $this->payment());
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame($query ? 'ORDER_SYNTHETIC' : null, $result['order_reference']);
        $this->assertStringNotContainsString('PRIVATE', json_encode($result));
    }

    public static function telemetryHttpFailureProvider(): array
    {
        $cases = [];
        foreach ([false, true] as $query) {
            foreach ([[401, 'http_4xx'], [404, 'http_4xx'], [418, 'http_4xx'], [429, 'http_4xx'], [503, 'http_5xx']] as $case) {
                $cases[] = array_merge([$query], $case);
            }
        }
        return $cases;
    }

    /** @dataProvider transportFailureProvider */
    public function testTransportErrorTelemetryPreservesIndeterminateResultAndNoRetry(bool $query, string $code, string $category): void
    {
        $sink = $this->errorSink($query ? 'get' : 'create', $category, 'classic', $query ? 'pending_query' : 'sync_response');
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $http->shouldReceive('request')->once()->andThrow(new TransportException($code));
        $result = $query ? $client->query($this->request(true)) : $client->create($this->request(), $this->payment());
        $this->assertSame($category, $result['error']['category']);
        $this->assertSame('processing', $result['state']);
        $this->assertTrue($result['error']['retryable']);
    }

    /** @dataProvider telemetryNormalizedFailureProvider */
    public function testHttpSuccessWithInvalidNormalizedFactsReportsContractOrStateError(bool $query, bool $unknown): void
    {
        $sink = $this->errorSink($query ? 'get' : 'create', $unknown ? 'unknown_state' : 'invalid_contract', 'classic', $query ? 'pending_query' : 'sync_response');
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $response = $unknown ? $this->pending() : [];
        if ($unknown) {
            $response['status'] = 'unknown_synthetic_state';
            $response['transactions']['payments'][0]['status'] = 'unknown_synthetic_state';
        }
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse($query ? 200 : 201, [], '', $response));
        $result = $query ? $client->query($this->request(true)) : $client->create($this->request(), $this->payment());
        $this->assertSame($unknown ? 'unknown' : 'invalid_response', $result['error']['category']);
        $this->assertSame('failure', $result['outcome']);
    }

    public static function telemetryNormalizedFailureProvider(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    /** @dataProvider telemetryLocalFailureProvider */
    public function testPreHttpErrorsEmitOnlyBoundedContext(bool $query, bool $authentication): void
    {
        $request = $this->request($query);
        if (!$authentication) {
            $request['surface'] = 'SYNTHETIC_PRIVATE_SURFACE';
        }
        $category = $authentication ? 'authentication' : 'validation';
        $sink = $this->errorSink($query ? 'get' : 'create', $category, $authentication ? 'classic' : 'server', $query ? 'pending_query' : 'sync_response');
        [$client, $http] = $this->client(true, $authentication ? '' : 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $http->shouldNotReceive('request');
        $result = $query ? $client->query($request) : $client->create($request, $this->payment());
        $this->assertSame($category, $result['error']['category']);
    }

    public static function telemetryLocalFailureProvider(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    public function testNotificationGetErrorUsesInjectedSourceWithoutAddingRequestFields(): void
    {
        $sink = $this->errorSink('notification', 'http_5xx', 'server', 'notification');
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink), 'notification');
        $request = $this->request(true);
        $request['surface'] = 'server';
        $http->shouldReceive('request')->once()->with('GET', '/plugins-platforms/v1/orders/ORDER_SYNTHETIC', Mockery::type('array'))
            ->andReturn(new HttpResponse(503, [], '', []));
        $this->assertSame('unavailable', $client->query($request)['error']['category']);
        $this->assertCount(7, $request);
        $this->assertArrayNotHasKey('source', $request);
    }

    public function testTelemetryThrowableCannotReplaceNormalizedHttpError(): void
    {
        $sink = $this->errorSink('get', 'http_5xx', 'classic', 'pending_query', true);
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(503, [], '', []));
        $result = $client->query($this->request(true));
        $this->assertSame('unavailable', $result['error']['category']);
        $this->assertTrue($result['error']['retryable']);
        $this->assertSame('ORDER_SYNTHETIC', $result['order_reference']);
    }

    public function testInvalidUtf8ProofReportsValidationWithoutHttpOrCredentialTelemetry(): void
    {
        $sink = $this->errorSink('create', 'validation', 'classic', 'sync_response');
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', new SmartPixTelemetry($sink));
        $http->shouldNotReceive('request');
        $payment = new SmartPixAuthorizedPayment("\xB1", '00000000-0000-4000-8000-000000000000', 'SYNTHETIC_KEY');
        $result = $client->create($this->request(), $payment);
        $this->assertSame('validation', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
    }

    public function testSuccessfulCreateCapturesOnlyExactPostTransportSeconds(): void
    {
        $now = 100.0;
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$now): float {
            return $now;
        });
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', $telemetry);
        $response = $this->pending();
        $http->shouldReceive('request')->once()->with('POST', '/plugins-platforms/v1/orders', Mockery::type('array'), Mockery::type('string'))
            ->andReturnUsing(static function () use (&$now, $response): HttpResponse {
                $now += 0.125;
                return new HttpResponse(201, [], '', $response);
            });
        $this->assertNull($client->creationDurationSeconds());
        $result = $client->create($this->request(), $this->payment());
        $this->assertSame('success', $result['outcome']);
        $this->assertSame(['value' => '125.50', 'currency' => 'BRL'], $result['payments'][0]['amount']);
        $this->assertSame(0.125, $client->creationDurationSeconds());
    }

    public function testCreateCapturesTheProviderRequestIdFromThePostResponseOnly(): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(
            201,
            ['X-Request-Id' => 'request-12345678'],
            '',
            $this->pending()
        ));

        $this->assertNull($client->creationRequestId());
        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertSame('request-12345678', $client->creationRequestId());

        // A later attempt that never reaches Orders cannot inherit the previous request ID.
        $this->assertSame('validation', $client->create($this->request())['error']['category']);
        $this->assertNull($client->creationRequestId());
    }

    public function testCreateCapturesTheProviderRequestIdOnAnHttpFailure(): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(
            503,
            ['x-request-id' => 'request-87654321'],
            '',
            []
        ));

        $this->assertSame('unavailable', $client->create($this->request(), $this->payment())['error']['category']);
        $this->assertSame('request-87654321', $client->creationRequestId());
    }

    /** @dataProvider unsafeRequestIdProvider */
    public function testCreateDoesNotForwardAnUnsafeResponseHeader(array $headers): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(201, $headers, '', $this->pending()));

        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertNull($client->creationRequestId());
    }

    public static function unsafeRequestIdProvider(): array
    {
        return [
            'missing' => [[]],
            'multiple values' => [['x-request-id' => ['request-12345678']]],
            'control character' => [['x-request-id' => "request-12345678\nsecret"]],
            'too long' => [['x-request-id' => str_repeat('a', 129)]],
        ];
    }

    /** @dataProvider failedCreateTimingProvider */
    public function testFailedCreationCapturesTransportBeforeErrorMetricLatency(bool $timeout): void
    {
        $now = 100.0;
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::ERROR, 'true', [
            'operation' => 'create', 'source' => 'sync_response', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'category' => $timeout ? 'timeout' : 'http_5xx',
        ], Mockery::type('string'))->andReturnUsing(static function () use (&$now): void {
            $now += 100.0;
        });
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$now): float {
            return $now;
        });
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', $telemetry);
        $http->shouldReceive('request')->once()->andReturnUsing(static function () use (&$now, $timeout): HttpResponse {
            $now += 0.25;
            if ($timeout) {
                throw new TransportException('timeout');
            }
            return new HttpResponse(503, [], '', ['message' => 'SYNTHETIC_PRIVATE']);
        });
        $result = $client->create($this->request(), $this->payment());
        $this->assertSame($timeout ? 'timeout' : 'unavailable', $result['error']['category']);
        $this->assertSame(200.25, $now);
        $this->assertSame(0.25, $client->creationDurationSeconds());
        $this->assertTrue($result['error']['retryable']);
    }

    public static function failedCreateTimingProvider(): array
    {
        return [[false], [true]];
    }

    public function testUnexpectedPostThrowableRetainsTimingAndPropagatesUnchanged(): void
    {
        $now = 100.0;
        $telemetry = new SmartPixTelemetry(null, static function () use (&$now): float {
            return $now;
        });
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', $telemetry);
        $original = new \Error('SYNTHETIC_PRIVATE_POST');
        $http->shouldReceive('request')->once()->andReturnUsing(static function () use (&$now, $original): void {
            $now += 0.25;
            throw $original;
        });
        try {
            $client->create($this->request(), $this->payment());
            $this->fail('Expected the original transport failure.');
        } catch (\Error $failure) {
            $this->assertSame($original, $failure);
            $this->assertSame(0.25, $client->creationDurationSeconds());
        }
    }

    public function testCreationWithoutPostResetsAStaleTransportDuration(): void
    {
        $now = 100.0;
        $telemetry = new SmartPixTelemetry(null, static function () use (&$now): float {
            return $now;
        });
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', $telemetry);
        $response = $this->pending();
        $http->shouldReceive('request')->once()->andReturnUsing(static function () use (&$now, $response): HttpResponse {
            $now += 0.25;
            return new HttpResponse(201, [], '', $response);
        });
        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertSame(0.25, $client->creationDurationSeconds());
        $this->assertSame('validation', $client->create($this->request())['error']['category']);
        $this->assertNull($client->creationDurationSeconds());
    }

    /** @dataProvider unusableCreationClockProvider */
    public function testUnusableClockDoesNotBlockSuccessfulPostOrInventDuration(array $clockValues): void
    {
        $telemetry = new SmartPixTelemetry(null, static function () use (&$clockValues): float {
            $value = array_shift($clockValues);
            if ($value instanceof \Throwable) {
                throw $value;
            }
            return $value;
        });
        [$client, $http] = $this->client(true, 'SYNTHETIC_CREDENTIAL', $telemetry);
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(201, [], '', $this->pending()));
        $this->assertSame('success', $client->create($this->request(), $this->payment())['outcome']);
        $this->assertNull($client->creationDurationSeconds());
    }

    public static function unusableCreationClockProvider(): array
    {
        return [
            [[100.0, 100.0]], [[100.0, 99.0]], [[100.0, NAN]],
            [[new \Error('SYNTHETIC_PRIVATE_CLOCK')]], [[100.0, new \Error('SYNTHETIC_PRIVATE_CLOCK')]],
        ];
    }

    private function errorSink(string $operation, string $category, string $surface, string $source, bool $throws = false): SmartPixMetricSink
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $expected = ['operation' => $operation, 'source' => $source, 'surface' => $surface,
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'category' => $category];
        $count = $sink->shouldReceive('count')->once()->withArgs(function ($name, $value, $details, $message = '') use ($expected): bool {
            $this->assertSame(SmartPixMetricSchema::ERROR, $name);
            $this->assertSame('true', $value);
            $this->assertSame($expected, $details);
            $this->assertIsString($message);
            return true;
        });
        if ($throws) {
            $count->andThrow(new \Error('SYNTHETIC_PRIVATE_TELEMETRY'));
        }
        $sink->shouldNotReceive('distribution');
        return $sink;
    }

    private function payment(): SmartPixAuthorizedPayment
    {
        return new SmartPixAuthorizedPayment('SYNTHETIC_PROOF', '00000000-0000-4000-8000-000000000000', 'SYNTHETIC_KEY');
    }

    private function request(bool $query = false): array
    {
        $request = [
            'surface' => 'classic', 'country' => 'MLB', 'payment_contract' => 'orders_smart_pix_v1',
            'attempt_reference' => 'WC-321', 'order_reference' => $query ? 'ORDER_SYNTHETIC' : null,
            'amount' => $query ? null : ['value' => '125.50', 'currency' => 'BRL'], 'refund_amount' => null,
        ];
        if (!$query) {
            $request['order_details'] = $this->expectedOrderDetails('125.50');
        }
        return $request;
    }

    private function pending(): array
    {
        return [
            'id' => 'ORDER_SYNTHETIC', 'external_reference' => 'WC-321', 'status' => 'processing',
            'status_detail' => 'in_process', 'type' => 'online', 'country_code' => 'BRA', 'currency' => 'BRL',
            'total_amount' => '125.50', 'total_paid_amount' => '0.00', 'transactions' => ['payments' => [[
                'id' => 'PAYMENT_SYNTHETIC', 'amount' => '125.50', 'status' => 'processing', 'status_detail' => 'in_process',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]]],
        ];
    }
}
