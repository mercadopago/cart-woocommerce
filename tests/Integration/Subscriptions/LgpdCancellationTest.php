<?php

namespace MercadoPago\Woocommerce\Tests\Integration\Subscriptions;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers as WCHelpers;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Hooks\Subscriptions;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Integration tests for LGPD-compliant subscription cancellation.
 *
 * Validates that WCS cancellation is NEVER blocked by AP API errors.
 * The user's right to cancel their subscription must be respected regardless
 * of external service availability.
 *
 * @spec feat-001 US-4 (cancellation) | DD-14
 * @covers \MercadoPago\Woocommerce\Hooks\Subscriptions::onSubscriptionCancelled
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LgpdCancellationTest extends TestCase
{
    private Subscriptions $hook;

    private Mockery\MockInterface $apClient;

    private Mockery\MockInterface $subscriptionsHelper;

    private Mockery\MockInterface $store;

    private Mockery\MockInterface $logs;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->apClient = Mockery::mock(AutomaticPaymentsClient::class);
        $this->subscriptionsHelper = Mockery::mock(SubscriptionsHelper::class);
        $this->store = Mockery::mock(Store::class);

        $logFile = Mockery::mock(File::class);
        $logFile->shouldReceive('info')->byDefault();
        $logFile->shouldReceive('error')->byDefault();
        $logFile->shouldReceive('warning')->byDefault();

        $this->logs = Mockery::mock(Logs::class);
        $this->logs->file = $logFile;

        WP_Mock::userFunction('is_admin')->andReturn(false);
        WP_Mock::userFunction('add_action')->byDefault();
        WP_Mock::userFunction('get_option')->byDefault()->andReturn([]);
        WP_Mock::userFunction('update_option')->byDefault()->andReturnTrue();

        $countryMock = Mockery::mock(Country::class);
        $countryMock->shouldReceive('getCountryConfigs')->byDefault()->andReturn(['currency' => 'BRL']);
        $helpersMock = Mockery::mock(WCHelpers::class);
        $helpersMock->country = $countryMock;
        $helpersMock->currency = Mockery::mock(Currency::class);

        $orderMetadataMock = Mockery::mock(\MercadoPago\Woocommerce\Order\OrderMetadata::class);
        $orderMetadataMock->shouldReceive('setCustomMetadata')->byDefault();

        $this->hook = new Subscriptions(
            $this->apClient,
            $this->subscriptionsHelper,
            $this->store,
            $this->logs,
            $helpersMock,
            $orderMetadataMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function makeSubscriptionMock(int $subId): Mockery\MockInterface
    {
        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn($subId);
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscription->shouldReceive('get_status')->andReturn('cancelled');
        return $subscription;
    }

    /**
     * AC-1: 5xx error from AP API does NOT block WCS cancellation.
     *
     * The method should return without throwing, allowing WCS to proceed.
     */
    public function testCancellationNotBlockedBy5xxError(): void
    {
        $subId            = 501;
        $mpSubscriptionId = 'CPP-WSUB-5001';

        $subscription = $this->makeSubscriptionMock($subId);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscription, '_mp_subscription_id', '')
            ->once()
            ->andReturn($mpSubscriptionId);

        $this->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->once()
            ->andReturn('TEST-preapproval-token');

        // Simulate 503 Service Unavailable
        $this->apClient
            ->shouldReceive('deleteSubscription')
            ->once()
            ->andReturn([
                'status'    => 503,
                'success'   => false,
                'not_found' => false,
            ]);

        // Method should complete without throwing — WCS cancellation not blocked
        $this->hook->onSubscriptionCancelled($subscription);

        // If we reach here, the test passed - cancellation was not blocked
        $this->expectNotToPerformAssertions();
    }

    /**
     * AC-2: Transport exception does NOT block WCS cancellation.
     */
    public function testCancellationNotBlockedByTransportException(): void
    {
        $subId            = 502;
        $mpSubscriptionId = 'CPP-WSUB-5002';

        $subscription = $this->makeSubscriptionMock($subId);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn($mpSubscriptionId);

        $this->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->andReturn('TEST-preapproval-token');

        // Simulate network failure
        $this->apClient
            ->shouldReceive('deleteSubscription')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));

        // Method should complete without throwing
        $this->hook->onSubscriptionCancelled($subscription);

        $this->expectNotToPerformAssertions();
    }

    /**
     * AC-3: 204 No Content (success) is handled gracefully.
     */
    public function testCancellation204SuccessHandledGracefully(): void
    {
        $subId            = 503;
        $mpSubscriptionId = 'CPP-WSUB-5003';

        $subscription = $this->makeSubscriptionMock($subId);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn($mpSubscriptionId);

        $this->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->andReturn('TEST-preapproval-token');

        $this->apClient
            ->shouldReceive('deleteSubscription')
            ->once()
            ->andReturn([
                'status'    => 204,
                'success'   => true,
                'not_found' => false,
            ]);

        $this->hook->onSubscriptionCancelled($subscription);

        $this->expectNotToPerformAssertions();
    }

    /**
     * AC-4: 404 Not Found (subscription already deleted) is treated as success.
     */
    public function testCancellation404TreatedAsSuccess(): void
    {
        $subId            = 504;
        $mpSubscriptionId = 'CPP-WSUB-5004';

        $subscription = $this->makeSubscriptionMock($subId);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn($mpSubscriptionId);

        $this->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->andReturn('TEST-preapproval-token');

        $this->apClient
            ->shouldReceive('deleteSubscription')
            ->once()
            ->andReturn([
                'status'    => 404,
                'success'   => false,
                'not_found' => true,
            ]);

        // 404 should NOT trigger error log - it's expected
        $this->logs->file->shouldNotReceive('error');

        $this->hook->onSubscriptionCancelled($subscription);

        $this->expectNotToPerformAssertions();
    }

    /**
     * AC-5: Missing _mp_subscription_id still allows WCS cancellation.
     *
     * The subscription might have been created before the AP integration was enabled.
     */
    public function testCancellationProceedsWithMissingSubscriptionId(): void
    {
        $subId = 505;

        $subscription = $this->makeSubscriptionMock($subId);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscription, '_mp_subscription_id', '')
            ->once()
            ->andReturn('');

        // Should NOT call deleteSubscription if there's no MP subscription ID
        $this->apClient->shouldNotReceive('deleteSubscription');

        $this->hook->onSubscriptionCancelled($subscription);

        $this->expectNotToPerformAssertions();
    }
}
