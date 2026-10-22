<?php

namespace MercadoPago\Woocommerce\Tests\Api\Core;

use MercadoPago\Woocommerce\Api\Core\CoreApiClient;
use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Core\ExchangeClient;
use MercadoPago\Woocommerce\Api\Core\FunnelClient;
use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\Api\Core\PaymentsClient;
use MercadoPago\Woocommerce\Api\Core\PreferencesClient;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use Mockery;
use PHPUnit\Framework\TestCase;

class ResourceClientsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testPaymentsClientBuildsContextAndSuperTokenRequest(): void
    {
        $apiClient = Mockery::mock(CoreApiClient::class);
        $apiClient->expects()
            ->post(
                '/v1/asgard/payments',
                [
                    'Authorization' => 'Bearer token',
                    'x-platform-id' => 'platform',
                    'x-product-id' => 'product',
                    'x-integrator-id' => 'integrator',
                    'x-meli-session-id' => 'session',
                    'x-super-token' => 'super-token',
                ],
                Mockery::on(function (array $payload): bool {
                    return $payload['transaction_amount'] === 10
                        && $payload['payment_type_id'] === 'credit_card'
                        && !array_key_exists('unknown_field', $payload)
                        && !array_key_exists('id', $payload)
                        && !array_key_exists('status', $payload);
                })
            )
            ->andReturn(new HttpResponse(201, [], '', ['id' => 123, 'status' => 'approved']));

        $payment = (new PaymentsClient($apiClient))->create(
            ['transaction_amount' => 10, 'unknown_field' => 'discarded'],
            new CoreRequestContext('token', 'platform', 'product', 'integrator'),
            'session',
            'super-token',
            'credit_card'
        );

        $this->assertSame('approved', $payment->getStatus());
        $this->assertSame(123, $payment->toArray()['id']);
    }

    public function testPaymentsClientPreservesLegacyEntityShape(): void
    {
        $apiClient = Mockery::mock(CoreApiClient::class);
        $apiClient->expects()
            ->post(
                '/v1/asgard/payments',
                [],
                Mockery::on(function (array $payload): bool {
                    return $payload['payer']['phone'] === ['number' => null, 'area_code' => null]
                        && $payload['payer']['identification'] === ['type' => null, 'number' => null]
                        && !array_key_exists('payment_type_id', $payload)
                        && !array_key_exists('status', $payload)
                        && $payload['additional_info']['payer']['identification'] === ['type' => null, 'number' => null]
                        && $payload['transaction_details'] === [
                            'financial_institution' => null,
                            'bank_transfer_id' => null,
                            'transaction_id' => null,
                        ]
                        && $payload['point_of_interaction']['transaction_data'] === [
                            'bank_info' => ['origin_bank_id' => null],
                        ];
                })
            )
            ->andReturn(new HttpResponse(201, [], '', ['id' => 123]));

        $payment = (new PaymentsClient($apiClient))->create([
            'payer' => [
                'phone' => [],
                'identification' => [],
            ],
            'additional_info' => [
                'payer' => ['identification' => []],
            ],
            'payment_type_id' => 'pix',
            'status' => 'approved',
            'transaction_details' => [],
            'point_of_interaction' => ['transaction_data' => []],
        ], new CoreRequestContext());

        $this->assertSame(123, $payment->toArray()['id']);
    }

    public function testPreferencesClientUsesAsgardRoute(): void
    {
        $apiClient = Mockery::mock(CoreApiClient::class);
        $apiClient->expects()
            ->post(
                '/v1/asgard/preferences',
                ['Authorization' => 'Bearer token'],
                Mockery::on(function (array $payload): bool {
                    return !array_key_exists('items', $payload)
                        && $payload['auto_return'] === null
                        && is_array($payload['additional_info'])
                        && is_array($payload['payer']);
                })
            )
            ->andReturn(new HttpResponse(201, [], '', ['id' => 'preference-id']));

        $preference = (new PreferencesClient($apiClient))->create(
            ['items' => []],
            new CoreRequestContext('token')
        );

        $this->assertSame('preference-id', $preference->toArray()['id']);
    }

    public function testPreferencesClientUsesLegacyItemSchemasAndOmitsEmptyCollections(): void
    {
        $apiClient = Mockery::mock(CoreApiClient::class);
        $apiClient->expects()
            ->post(
                '/v1/asgard/preferences',
                [],
                Mockery::on(function (array $payload): bool {
                    return !array_key_exists('excluded_payment_methods', $payload['payment_methods'])
                        && !array_key_exists('currency_id', $payload['additional_info']['items'][0])
                        && $payload['items'][0]['currency_id'] === 'BRL'
                        && !array_key_exists('city', $payload['shipments']['receiver_address'])
                        && $payload['shipments']['receiver_address']['city_name'] === 'São Paulo';
                })
            )
            ->andReturn(new HttpResponse(201, [], '', ['id' => 'preference-id']));

        $preference = (new PreferencesClient($apiClient))->create([
            'items' => [['id' => 'preference-item', 'currency_id' => 'BRL']],
            'additional_info' => [
                'items' => [['id' => 'payment-item', 'currency_id' => 'BRL']],
            ],
            'payer' => ['phone' => []],
            'payment_methods' => ['excluded_payment_methods' => []],
            'shipments' => ['receiver_address' => ['city' => 'discarded', 'city_name' => 'São Paulo']],
        ], new CoreRequestContext());

        $this->assertSame('preference-id', $preference->toArray()['id']);
    }

    public function testNotificationAndExchangeClientsUseValidatedRoutes(): void
    {
        $context = new CoreRequestContext('token');
        $apiClient = Mockery::mock(CoreApiClient::class);
        $apiClient->expects()
            ->get('/v1/asgard/notification/P-123', ['Authorization' => 'Bearer token'])
            ->andReturn(new HttpResponse(200, [], '', ['external_reference' => 'WC-1']));
        $apiClient->expects()
            ->get('/ppcore/beta/payment-methods/v1/exchange?currency.id=USD', ['Authorization' => 'Bearer token'])
            ->andReturn(new HttpResponse(200, [], '', ['rate' => 5.25]));

        $notification = (new NotificationsClient($apiClient))->find('P-123', $context);
        $rate = (new ExchangeClient($apiClient))->getRate('USD', $context, true);

        $this->assertSame('WC-1', $notification->toArray()['external_reference']);
        $this->assertSame(5.25, $rate->getRate());
    }

    public function testFunnelClientUsesHeaderTokenWithoutPuttingItInPayload(): void
    {
        $apiClient = Mockery::mock(CoreApiClient::class);
        $apiClient->expects()
            ->post(
                '/v1/eplatforms/core/identification/seller-configuration/start-integration',
                [],
                ['platform_id' => 'woocommerce']
            )
            ->andReturn(new HttpResponse(201, ['X-CPP-Token' => 'cpp-token'], '', ['id' => 'integration-id']));
        $apiClient->expects()
            ->put(
                '/v1/eplatforms/core/identification/seller-configuration/update-integration',
                ['x-cpp-token' => 'cpp-token'],
                ['id' => 'integration-id', 'is_disabled' => true]
            )
            ->andReturn(new HttpResponse(204));

        $client = new FunnelClient($apiClient);
        $registration = $client->create(['platform_id' => 'woocommerce']);
        $client->update(['id' => 'integration-id', 'is_disabled' => true], 'cpp-token');

        $this->assertSame('integration-id', $registration->getId());
        $this->assertSame('cpp-token', $registration->getCppToken());
    }
}
