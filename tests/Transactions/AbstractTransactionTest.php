<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\AbstractTransaction;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WP_Mock;
use WP_Theme;
use WP_User;

class AbstractTransactionTest extends TestCase
{
    use TransactionMock;
    use MockeryPHPUnitIntegration;

    private string $transactionClass = AbstractTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
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
        $this->transaction->mercadopago->sellerConfig
            ->expects()
            ->getCredentialsAccessToken()
            ->andReturn(random()->uuid());

        Mockery::mock('alias:' . Device::class)
            ->expects()
            ->getDeviceProductId()
            ->andReturn(random()->uuid());

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
}
