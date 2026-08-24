<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use Exception;
use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Exceptions\ApiException;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\AbstractTransaction;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WP_Mock;
use WP_Theme;
use WP_User;

class AbstractTransactionTest extends TestCase
{
    use TransactionMock;
    use MockeryPHPUnitIntegration;

    private string $transactionClass = AbstractTransaction::class;

    // On PHP 8.2 the phpdoc type hint below can become a native union type.
    /**
     * @var MockInterface|AbstractTransaction
     */
    private $transaction;

    public function setUp(): void
    {
        WP_Mock::userFunction('sanitize_post', [
            'return' => function ($data) {
                return $data;
            }
        ]);

        WP_Mock::userFunction('map_deep', [
            'return' => function ($data, $callback) {
                return is_array($data) ? array_map($callback, $data) : $callback($data);
            }
        ]);

        WP_Mock::userFunction('sanitize_text_field', [
            'return' => function ($text) {
                return $text;
            }
        ]);
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLogTransactionPayload()
    {
        $this->transaction->transaction = Mockery::mock(AbstractEntity::class)
            ->expects()
            ->toArray()
            ->andReturn([])
            ->getMock();

        Mockery::mock('alias:' . Arrays::class)
            ->expects()
            ->except([], ['token'])
            ->andReturn([])
            ->getMock()
            ->expects()
            ->last(Mockery::type('array'))
            ->andReturn('Transaction');

        $this->transaction->mercadopago->logs->file = Mockery::mock(File::class)
            ->expects()
            ->info('Transaction payload', '', [])
            ->getMock();

        $this->transaction->logTransactionPayload();
    }

    public function testGetSdk(): void
    {
        // Define constant if not exists (needed for Device::getDeviceProductId)
        if (!defined('MP_PRODUCT_ID_MOBILE')) {
            define('MP_PRODUCT_ID_MOBILE', 'BT7OFH09QS3001K5A0H0');
        }

        $this->transaction->mercadopago->sellerConfig
            ->expects()
            ->getCredentialsAccessToken()
            ->andReturn(random()->uuid());

        // Mock the underlying WordPress function instead of alias mocking Device class
        WP_Mock::userFunction('wp_is_mobile')
            ->andReturn(false);

        $this->transaction->mercadopago->storeConfig
            ->expects()
            ->getIntegratorId()
            ->andReturn(random()->uuid());

        $this->assertInstanceOf(Sdk::class, $this->transaction->getSdk());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testGetBinaryMode(bool $binaryMode): void
    {
        $this->transaction->mercadopago->hooks->options
            ->expects()
            ->getGatewayOption($this->transaction->gateway, 'binary_mode', 'no')
            ->andReturn($binaryMode ? 'yes' : 'no');

        $this->assertSame($binaryMode, $this->transaction->getBinaryMode());
    }

    public function testGetExternalReference(): void
    {
        $this
            ->mockTransactionOrder()
            ->expects()
            ->get_id()
            ->andReturn(
                $orderId = random()->uuid()
            );

        $this->transaction->mercadopago->storeConfig
            ->expects()
            ->getStoreId()
            ->andReturn(
                $storeId = random()->uuid()
            );

        $this->assertSame("$storeId$orderId", $this->transaction->getExternalReference());
    }

    public function testSetCommonTransaction(): void
    {
        $expected = [
            'binary_mode' => random()->boolean(),
            'external_reference' => random()->uuid(),
            'notification_url' => random()->url(),
            'metadata' => [],
            'statement_descriptor' => random()->company(),
        ];

        $this->setCommonTransactionMock($expected);

        $this->transaction->transaction = new \stdClass();

        $this->transaction->setCommonTransaction();

        $this->assertObjectEqualsArray($expected, $this->transaction->transaction);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @testWith [true]
     *           [false]
     */
    public function testGetInternalMetadata(bool $userExists): void
    {
        $mockFlowId = random()->uuid();

        $_POST['mercadopago_checkout_session'] = ['_mp_flow_id' => $mockFlowId];

        $expected = [
            'platform' => MP_PLATFORM_ID,
            'platform_version' => $wcVersion = random()->semver(),
            'module_version' => MP_VERSION,
            'php_version' => PHP_VERSION,
            'site_id' => random()->lexify('???'),
            'sponsor_id' => random()->numberBetween(),
            'collector' => random()->optional(0.5, '')->numberBetween(),
            'test_mode' => random()->boolean(),
            'details' => '',
            'seller_website' => random()->url(),
            'billing_address' => [
                'zip_code' => random()->postcode(),
                'street_name' => random()->streetName(),
                'city_name' => random()->city(),
                'state_name' => random()->state(),
                'country_name' => random()->country(),
            ],
            'user' => [
                'registered_user' => $userExists ? 'yes' : 'no',
                'user_email' => $userExists ? random()->email() : null,
                'user_registration_date' => $userExists ? random()->date('Y-m-d\TH:i:s.vP') : null,
            ],
            'cpp_extra' => [
                'platform_version' => $wcVersion,
                'module_version' => MP_VERSION,
            ],
            'blocks_payment' => random()->randomElement(['yes', 'no']),
            'settings' => [],
            'auto_update' => random()->boolean(),
            'theme' => [
                'theme_name' => random()->word(),
                'theme_version' => random()->semver(),
            ],
            'flow_id' => $mockFlowId,
        ];

        $this->transaction->mercadopago->woocommerce->version = $expected['platform_version'];

        $this->transaction->mercadopago->sellerConfig
            ->expects()
            ->getSiteId()
            ->andReturn($expected['site_id'])
            ->getMock()
            ->expects()
            ->getCollectorId()
            ->andReturn($expected['collector'])
            ->getMock()
            ->expects()
            ->isAutoUpdate()
            ->andReturn($expected['auto_update']);

        $this->setNotAccessibleProperty($this->transaction, 'countryConfigs', [
            'sponsor_id' => $expected['sponsor_id']
        ]);

        $this->transaction->mercadopago->storeConfig
            ->expects()
            ->isTestMode()
            ->andReturn($expected['test_mode']);

        $this->transaction->mercadopago->hooks->options
            ->expects()
            ->get('siteurl')
            ->andReturn($expected['seller_website']);

        $order = $this->mockTransactionOrder();

        // Mock get_id() specifically for this test
        $order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        $this->transaction->mercadopago->orderBilling
            ->expects()
            ->getZipcode($order)
            ->andReturn($expected['billing_address']['zip_code'])
            ->getMock()
            ->expects()
            ->getAddress1($order)
            ->andReturn($expected['billing_address']['street_name'])
            ->getMock()
            ->expects()
            ->getCity($order)
            ->andReturn($expected['billing_address']['city_name'])
            ->getMock()
            ->expects()
            ->getState($order)
            ->andReturn($expected['billing_address']['state_name'])
            ->getMock()
            ->expects()
            ->getCountry($order)
            ->andReturn($expected['billing_address']['country_name']);

        $this->transaction->mercadopago->helpers->currentUser
            ->expects()
            ->getCurrentUser()
            ->andReturn(
                $user = Mockery::mock(WP_User::class)
            );

        $user
            ->expects()
            ->exists()
            ->times(3)
            ->andReturn($userExists);

        if ($userExists) {
            $user->user_email = $expected['user']['user_email'];
            $user->user_registered = $expected['user']['user_registration_date'];

            Mockery::mock('alias:' . Date::class)
                ->expects()
                ->formatGmDate($user->user_registered)
                ->andReturnArg(0);
        }

        $this->transaction->mercadopago->orderMetadata
            ->expects()
            ->getPaymentBlocks($order)
            ->andReturn($expected['blocks_payment']);

        $this->transaction->mercadopago->metadataConfig
            ->expects()
            ->getGatewaySettings('')
            ->andReturn($expected['settings']);

        WP_Mock::userFunction('wp_get_theme')->andReturn(
            Mockery::mock(WP_Theme::class)
                ->expects()
                ->get('Name')
                ->andReturn($expected['theme']['theme_name'])
                ->getMock()
                ->expects()
                ->get('Version')
                ->andReturn($expected['theme']['theme_version'])
                ->getMock()
        );

        $this->transaction
            ->expects()
            ->extendInternalMetadata(Mockery::type(PaymentMetadata::class))
            ->andReturnArg(0);

        // Mock session helpers for flow_id
        $this->transaction->mercadopago->helpers->session
            ->expects()
            ->getSession(Mockery::any())
            ->andReturnNull();

        $this->transaction->mercadopago->helpers->session
            ->expects()
            ->deleteSession(Mockery::any());

        $expected['billing_address']['zip_code'] = str_replace('-', '', $expected['billing_address']['zip_code']);

        $this->assertObjectEqualsArray($expected, $this->transaction->getInternalMetadata());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSetCheckoutData(): void
    {
        $checkoutData = [
            '_mp_flow_id' => 'test-flow-id-123',
            'checkout_type' => 'wallet_button',
            'additional_data' => 'test-data'
        ];

        // Mock the transaction object
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = [];

        // Create a mock PaymentMetadata object
        $mockMetadata = Mockery::mock(PaymentMetadata::class);
        $mockMetadata->flow_id = 'test-flow-id-123';
        $mockMetadata->platform = MP_PLATFORM_ID;
        $mockMetadata->module_version = MP_VERSION;

        $this->transaction
            ->expects()
            ->getInternalMetadata()
            ->once()
            ->andReturn($mockMetadata);

        // Call setCheckoutData
        $result = $this->transaction->setCheckoutData($checkoutData);

        // Assert that it returns the same instance (fluent interface)
        $this->assertSame($this->transaction, $result);

        // Assert that metadata was recreated with new data (cast to array as per the method)
        $expectedMetadataArray = (array) $mockMetadata;
        $this->assertEquals($expectedMetadataArray, $this->transaction->transaction->metadata);
    }

    /**
     * Test that setCheckoutData properly calls getInternalMetadata and updates metadata
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSetCheckoutDataCallsGetInternalMetadata(): void
    {
        $checkoutData = ['_mp_flow_id' => 'new-flow-id-456'];

        // Mock the transaction object
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => 'old-flow-id'];

        // Create a mock PaymentMetadata object with updated flow_id
        $mockMetadata = Mockery::mock(PaymentMetadata::class);
        $mockMetadata->flow_id = 'new-flow-id-456';
        $mockMetadata->platform = MP_PLATFORM_ID;
        $mockMetadata->module_version = MP_VERSION;

        $this->transaction
            ->expects()
            ->getInternalMetadata()
            ->once()
            ->andReturn($mockMetadata);

        // Call setCheckoutData
        $result = $this->transaction->setCheckoutData($checkoutData);

        // Assert that it returns the same instance (fluent interface)
        $this->assertSame($this->transaction, $result);

        // Assert that metadata was recreated with updated data
        $expectedMetadataArray = (array) $mockMetadata;
        $this->assertEquals($expectedMetadataArray, $this->transaction->transaction->metadata);
        $this->assertEquals('new-flow-id-456', $this->transaction->transaction->metadata['flow_id']);
    }

    /**
     * Test that setCheckoutData works with empty checkout data
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSetCheckoutDataWithEmptyData(): void
    {
        $checkoutData = [];

        // Mock the transaction object
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = [];

        // Create a mock PaymentMetadata object without flow_id
        $mockMetadata = Mockery::mock(PaymentMetadata::class);
        $mockMetadata->flow_id = null;
        $mockMetadata->platform = MP_PLATFORM_ID;
        $mockMetadata->module_version = MP_VERSION;

        $this->transaction
            ->expects()
            ->getInternalMetadata()
            ->once()
            ->andReturn($mockMetadata);

        // Call setCheckoutData
        $result = $this->transaction->setCheckoutData($checkoutData);

        // Assert that it returns the same instance (fluent interface)
        $this->assertSame($this->transaction, $result);

        // Assert that metadata was recreated
        $expectedMetadataArray = (array) $mockMetadata;
        $this->assertEquals($expectedMetadataArray, $this->transaction->transaction->metadata);
        $this->assertNull($this->transaction->transaction->metadata['flow_id']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @testWith [true,  "homol"]
     *           [false, "prod"]
     */
    public function testSendApiErrorMetricDispatchesToDatadogWithContext(bool $isTestMode, string $expectedEnvironment): void
    {
        // No flow_id or checkout_type resolved on the transaction metadata → both degrade to null.
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => null, 'checkout_type' => null];

        $apiRoute  = '/checkout/preferences';
        $exception = new Exception('API failure', 400);
        $siteId    = 'MLB';
        $custId    = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn($isTestMode);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_api_error', '400', 'API failure', null, [
                'team'            => 'big',
                'api_route'       => $apiRoute,
                'site_id'         => $siteId,
                'environment'     => $expectedEnvironment,
                'cust_id'         => $custId,
                'sdk_instance_id' => null,
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendApiErrorMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, $exception);
    }

    /**
     * A populated flow_id and checkout_type in transaction metadata must be forwarded to Datadog
     * as sdk_instance_id and payment_method respectively. Classic and Blocks both land in metadata
     * via getInternalMetadata() — the resolution difference is covered by
     * testGetCheckoutSessionDataMergesSessionAndPostForBothCheckoutModes.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider apiErrorMetadataProvider
     */
    public function testSendApiErrorMetricForwardsMetadataFieldsToDatadog(string $apiRoute, int $statusCode, string $checkoutType): void
    {
        $flowId = random()->uuid();
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => $flowId, 'checkout_type' => $checkoutType];

        $exception = new Exception('API failure', $statusCode);
        $siteId    = 'MLB';
        $custId    = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn(false);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_api_error', (string) $statusCode, 'API failure', $checkoutType, [
                'team'            => 'big',
                'api_route'       => $apiRoute,
                'site_id'         => $siteId,
                'environment'     => 'prod',
                'cust_id'         => $custId,
                'sdk_instance_id' => $flowId,
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendApiErrorMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, $exception);
    }

    public function apiErrorMetadataProvider(): array
    {
        return [
            'classic checkout route' => ['/checkout/preferences', 500, 'credit_card'],
            'blocks checkout route'  => ['/v1/asgard/payments',   503, 'super_token'],
        ];
    }

    /**
     * Defensive: when the transaction metadata has no flow_id or checkout_type key, both degrade
     * to null without error.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSendApiErrorMetricSendsNullSdkInstanceIdWhenMetadataHasNoFlowId(): void
    {
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = [];

        $apiRoute  = '/checkout/preferences';
        $exception = new Exception('API failure', 422);
        $siteId    = 'MLB';
        $custId    = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn(false);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_api_error', '422', 'API failure', null, [
                'team'            => 'big',
                'api_route'       => $apiRoute,
                'site_id'         => $siteId,
                'environment'     => 'prod',
                'cust_id'         => $custId,
                'sdk_instance_id' => null,
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendApiErrorMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, $exception);
    }

    /**
     * Reachable edge: a store can submit an empty _mp_flow_id, which getInternalMetadata stores
     * verbatim ('' is not null, so the ?? null fallback does not fire). resolveMetadataField must
     * coerce the empty string to null so the metric carries no noise value.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSendApiErrorMetricCoercesEmptyFlowIdToNull(): void
    {
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => '', 'checkout_type' => ''];

        $apiRoute  = '/checkout/preferences';
        $exception = new Exception('API failure', 400);
        $siteId    = 'MLB';
        $custId    = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn(false);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_api_error', '400', 'API failure', null, [
                'team'            => 'big',
                'api_route'       => $apiRoute,
                'site_id'         => $siteId,
                'environment'     => 'prod',
                'cust_id'         => $custId,
                'sdk_instance_id' => null,
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendApiErrorMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, $exception);
    }

    /**
     * A successful creation (no exception) is a 2xx: the SDK only returns data on 200/201, throwing on 4xx/5xx.
     * The response body carries the payment's business status, so payment_status reflects it (approved here).
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSendPaymentCreateResultMetricRecordsApprovedPaymentAs2xx(): void
    {
        $flowId = random()->uuid();
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => $flowId, 'checkout_type' => 'credit_card'];

        $apiRoute = '/checkout/preferences';
        $siteId   = 'MLB';
        $custId   = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn(false);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_payment_create_result', '2xx', 'success', 'credit_card', [
                'team'             => 'big',
                'api_route'        => $apiRoute,
                'site_id'          => $siteId,
                'environment'      => 'prod',
                'cust_id'          => $custId,
                'sdk_instance_id'  => $flowId,
                'alert_type'       => 'success',
                'payment_status'   => 'approved',
                'device'           => 'unknown',
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendPaymentCreateResultMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, null, ['status' => 'approved']);
    }

    /**
     * A rejected card is NOT an API error: the API returns 2xx with status=rejected in the body. The metric must
     * record it as a 2xx success whose payment_status is 'rejected' — not as a 4xx/5xx.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSendPaymentCreateResultMetricRecordsRejectedPaymentAs2xx(): void
    {
        $flowId = random()->uuid();
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => $flowId, 'checkout_type' => 'credit_card'];

        $apiRoute = '/checkout/preferences';
        $siteId   = 'MLB';
        $custId   = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn(false);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_payment_create_result', '2xx', 'success', 'credit_card', [
                'team'             => 'big',
                'api_route'        => $apiRoute,
                'site_id'          => $siteId,
                'environment'      => 'prod',
                'cust_id'          => $custId,
                'sdk_instance_id'  => $flowId,
                'alert_type'       => 'success',
                'payment_status'   => 'rejected',
                'device'           => 'unknown',
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendPaymentCreateResultMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, null, ['status' => 'rejected']);
    }

    /**
     * An ApiException is only thrown for 4xx; no payment was created, so payment_status is null and the value
     * carries the 4xx class.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSendPaymentCreateResultMetricRecordsApiExceptionAs4xx(): void
    {
        $flowId = random()->uuid();
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => $flowId, 'checkout_type' => 'super_token'];

        $apiRoute  = '/checkout/preferences';
        $exception = new ApiException('Bad request', 'CPP_0001', 400, 'raw error chain');
        $siteId    = 'MLB';
        $custId    = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn(false);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_payment_create_result', '4xx', 'Bad request', 'super_token', [
                'team'             => 'big',
                'api_route'        => $apiRoute,
                'site_id'          => $siteId,
                'environment'      => 'prod',
                'cust_id'          => $custId,
                'sdk_instance_id'  => $flowId,
                'alert_type'       => 'error',
                'payment_status'   => null,
                'device'           => 'unknown',
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendPaymentCreateResultMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, $exception);
    }

    /**
     * Defensive: the SDK today only raises ApiException for 4xx, but the classification reads the status the
     * exception carries (getApiStatus), so an ApiException flagged 5xx must map to '5xx', not '4xx'.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSendPaymentCreateResultMetricClassifiesApiExceptionByCarriedStatus(): void
    {
        $flowId = random()->uuid();
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => $flowId, 'checkout_type' => 'credit_card'];

        $apiRoute  = '/checkout/preferences';
        $exception = new ApiException('Server Error', 'CPP_0002', 503, 'raw error chain');
        $siteId    = 'MLB';
        $custId    = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn(false);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_payment_create_result', '5xx', 'Server Error', 'credit_card', [
                'team'             => 'big',
                'api_route'        => $apiRoute,
                'site_id'          => $siteId,
                'environment'      => 'prod',
                'cust_id'          => $custId,
                'sdk_instance_id'  => $flowId,
                'alert_type'       => 'error',
                'payment_status'   => null,
                'device'           => 'unknown',
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendPaymentCreateResultMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, $exception);
    }

    /**
     * Any non-ApiException is the SDK's generic "Internal API Error" (5xx): no attributable status code.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSendPaymentCreateResultMetricRecordsGenericExceptionAs5xx(): void
    {
        $this->transaction->transaction = new \stdClass();
        $this->transaction->transaction->metadata = ['flow_id' => null, 'checkout_type' => null];

        $apiRoute  = '/checkout/preferences';
        $exception = new Exception('Internal API Error');
        $siteId    = 'MLB';
        $custId    = random()->uuid();

        $this->transaction->mercadopago->sellerConfig
            ->expects()->getSiteId()->andReturn($siteId)
            ->getMock()
            ->expects()->getCustIdFromAT()->andReturn($custId);

        $this->transaction->mercadopago->storeConfig
            ->expects()->isTestMode()->andReturn(false);

        Mockery::mock('alias:' . Datadog::class)
            ->expects()
            ->getInstance()
            ->andReturnSelf()
            ->getMock()
            ->expects()
            ->sendEvent('mp_payment_create_result', '5xx', 'Internal API Error', null, [
                'team'             => 'big',
                'api_route'        => $apiRoute,
                'site_id'          => $siteId,
                'environment'      => 'prod',
                'cust_id'          => $custId,
                'sdk_instance_id'  => null,
                'alert_type'       => 'error',
                'payment_status'   => null,
                'device'           => 'unknown',
            ]);

        $method = new ReflectionMethod(AbstractTransaction::class, 'sendPaymentCreateResultMetric');
        $method->setAccessible(true);
        $method->invoke($this->transaction, $apiRoute, $exception);
    }

    /**
     * Protects the resolution that feeds metadata['flow_id'] (and therefore sdk_instance_id):
     * getCheckoutSessionData must merge the WC session with the request POST and work for
     * both Classic (nested array under mercadopago_checkout_session) and Blocks (flat key
     * resolved via processBlocksCheckoutData).
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetCheckoutSessionDataMergesSessionAndPostForBothCheckoutModes(): void
    {
        $order = $this->mockTransactionOrder();
        $order->shouldReceive('get_id')->andReturn(123);

        // Classic: nested array present in POST.
        $_POST['mercadopago_checkout_session'] = ['_mp_flow_id' => 'classic-flow'];

        $this->transaction->mercadopago->helpers->session
            ->expects()->getSession('mp_checkout_session_123')->andReturnNull()
            ->getMock()
            ->expects()->deleteSession('mp_checkout_session_123');

        $classic = $this->transaction->getCheckoutSessionData();
        $this->assertSame('classic-flow', $classic['_mp_flow_id']);

        // Blocks: no nested key in POST → resolved via gateway->processBlocksCheckoutData,
        // and merged on top of session-persisted data (Order Pay / 3DS scenario).
        unset($_POST['mercadopago_checkout_session']);
        $_POST['mercadopago_checkout_session_mp_flow_id'] = 'blocks-flow';

        $this->transaction->mercadopago->helpers->session
            ->expects()->getSession('mp_checkout_session_123')->andReturn(['_mp_flow_id' => 'session-flow'])
            ->getMock()
            ->expects()->deleteSession('mp_checkout_session_123');

        $this->transaction->gateway
            ->expects()
            ->processBlocksCheckoutData('mercadopago_checkout_session', ['mercadopago_checkout_session_mp_flow_id' => 'blocks-flow'])
            ->andReturn(['_mp_flow_id' => 'blocks-flow']);

        $blocks = $this->transaction->getCheckoutSessionData();
        // POST value wins over session value via array_merge.
        $this->assertSame('blocks-flow', $blocks['_mp_flow_id']);
    }
}
