<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Notification\IpnNotification;
use MercadoPago\Woocommerce\Notification\NotificationFactory;
use MercadoPago\Woocommerce\Notification\WebhookNotification;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class NotificationFactoryTest extends TestCase
{
    use WoocommerceMock;
    use MockeryPHPUnitIntegration;

    private $originalMercadoPago;

    private $hadMercadoPago;

    private $mercadoPago;

    private $gateway;

    public function setUp(): void
    {
        $this->hadMercadoPago = array_key_exists('mercadopago', $GLOBALS);
        $this->originalMercadoPago = $GLOBALS['mercadopago'] ?? null;
        $this->mercadoPago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->gateway = Mockery::mock(MercadoPagoGatewayInterface::class);
        $GLOBALS['mercadopago'] = $this->mercadoPago;
    }

    public function tearDown(): void
    {
        if ($this->hadMercadoPago) {
            $GLOBALS['mercadopago'] = $this->originalMercadoPago;
        } else {
            unset($GLOBALS['mercadopago']);
        }

        parent::tearDown();
    }

    public function testCreatesWebhookNotificationForPaymentWebhook(): void
    {
        $notification = (new NotificationFactory())->createNotificationHandler(
            $this->gateway,
            ['type' => 'payment', 'source_news' => 'webhooks']
        );

        $this->assertInstanceOf(WebhookNotification::class, $notification);
        $this->assertCommonDependencies($notification);
        $this->assertSame($this->mercadoPago->helpers->requester, $notification->requester);
    }

    public function testCreatesIpnNotificationForMerchantOrderIpn(): void
    {
        $notification = (new NotificationFactory())->createNotificationHandler(
            $this->gateway,
            ['topic' => 'merchant_order', 'source_news' => 'ipn']
        );

        $this->assertInstanceOf(IpnNotification::class, $notification);
        $this->assertCommonDependencies($notification);
        $this->assertSame($this->mercadoPago->helpers->requester, $notification->requester);
    }

    public function testCreatesCoreNotificationForCorePayload(): void
    {
        $notification = (new NotificationFactory())->createNotificationHandler(
            $this->gateway,
            ['topic' => 'payment', 'source_news' => 'core']
        );

        $this->assertInstanceOf(CoreNotification::class, $notification);
        $this->assertCommonDependencies($notification);
        $notificationsClient = new \ReflectionProperty(CoreNotification::class, 'notificationsClient');
        $notificationsClient->setAccessible(true);
        $this->assertSame(
            $this->mercadoPago->coreApi->notifications,
            $notificationsClient->getValue($notification)
        );
    }

    public function testFallsBackToCoreNotificationForUnknownPayload(): void
    {
        $notification = (new NotificationFactory())->createNotificationHandler(
            $this->gateway,
            ['type' => 'unknown', 'source_news' => 'unknown']
        );

        $this->assertInstanceOf(CoreNotification::class, $notification);
        $this->assertCommonDependencies($notification);
        $notificationsClient = new \ReflectionProperty(CoreNotification::class, 'notificationsClient');
        $notificationsClient->setAccessible(true);
        $this->assertSame(
            $this->mercadoPago->coreApi->notifications,
            $notificationsClient->getValue($notification)
        );
    }

    private function assertCommonDependencies($notification): void
    {
        $this->assertSame($this->gateway, $notification->gateway);
        $this->assertSame($this->mercadoPago->logs, $notification->logs);
        $this->assertSame($this->mercadoPago->orderStatus, $notification->orderStatus);
        $this->assertSame($this->mercadoPago->sellerConfig, $notification->seller);
        $this->assertSame($this->mercadoPago->storeConfig, $notification->store);
    }
}
