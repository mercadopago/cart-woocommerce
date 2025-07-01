<?php

namespace MercadoPago\Woocommerce\Notification;

// Mock global para file_get_contents('php://input')
function file_get_contents($filename) {
    if ($filename === 'php://input') {
        return CoreNotificationTest::$mockInput ?? '';
    }
    return \file_get_contents($filename);
}

use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;

class CoreNotificationTest extends TestCase
{
    private MercadoPagoGatewayInterface $gateway;
    private Logs $logs;
    private OrderStatus $orderStatus;
    private Seller $seller;
    private Store $store;

    public static $mockInput = null;
    private $coreNotification;

    protected function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();

        $this->gateway = Mockery::mock(MercadoPagoGatewayInterface::class);
        $this->logs = Mockery::mock(Logs::class);
        $this->orderStatus = Mockery::mock(OrderStatus::class);
        $this->seller = Mockery::mock(Seller::class);
        $this->store = Mockery::mock(Store::class);

        $this->coreNotification = new CoreNotification($this->gateway, $this->logs, $this->orderStatus, $this->seller, $this->store);
    }

    protected function tearDown(): void
    {
        self::$mockInput = null;
        Mockery::close();
    }

    public function mockPhpInput($body)
    {
        self::$mockInput = $body;
    }

    public function testGetNotificationId()
    {
        $body = json_encode('P-12345');
        $this->mockPhpInput($body);
        $this->assertEquals('P-12345', $this->coreNotification->getNotificationId());
        
        $body = json_encode(['notification_id' => 'P-12345']);
        $this->mockPhpInput($body);
        $this->assertEquals('P-12345', $this->coreNotification->getNotificationId());
    }
    public function testValidateNotificationId()
    {
        $this->assertTrue($this->coreNotification->validateNotificationId('P-12345'));
        $this->assertTrue($this->coreNotification->validateNotificationId('M-12345'));
        $this->assertFalse($this->coreNotification->validateNotificationId('12345'));
        $this->assertFalse($this->coreNotification->validateNotificationId('P-12345-12345'));
        $this->assertFalse($this->coreNotification->validateNotificationId('P12345'));
        $this->assertFalse($this->coreNotification->validateNotificationId('P-ABCDE'));
        $this->assertFalse($this->coreNotification->validateNotificationId('P-'));
    }

    public function testGetSdkInstance()
    {
        WP_Mock::userFunction('wp_is_mobile', [
            'return' => false,
        ]);

        defined('MP_PLATFORM_ID') || define('MP_PLATFORM_ID', 'platform-id-teste');
        defined('MP_PRODUCT_ID_DESKTOP') || define('MP_PRODUCT_ID_DESKTOP', 'product-id-desktop-teste');
        defined('MP_PRODUCT_ID_MOBILE') || define('MP_PRODUCT_ID_MOBILE', 'product-id-mobile-teste');

        $this->store->shouldReceive('getIntegratorId')->andReturn('integrator-id-teste');
        $this->seller->shouldReceive('getCredentialsAccessToken')->andReturn('access-token-teste');

        $this->coreNotification->getSdkInstance();
        $this->assertInstanceOf(Sdk::class, $this->coreNotification->getSdkInstance());
    }
} 