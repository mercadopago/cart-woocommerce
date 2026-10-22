<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use Exception;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\AbstractNotification;
use MercadoPago\Woocommerce\Notification\IpnNotification;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WC_Order;

class AbstractNotificationTest extends TestCase
{
    use WoocommerceMock;
    use MockeryPHPUnitIntegration;

    /** @var MockInterface|IpnNotification */
    private $notification;

    /** @var MockInterface|File */
    private $file;

    public function setUp(): void
    {
        $this->notification = Mockery::mock(IpnNotification::class)->makePartial();
        $this->file = Mockery::mock(File::class);
        $this->notification->logs = Mockery::mock(Logs::class);
        $this->notification->logs->file = $this->file;
        $this->notification->gateway = Mockery::mock(MercadoPagoGatewayInterface::class);
    }

    public function testHandleReceivedNotificationLogsReceivedData(): void
    {
        $data = [];

        $this->file->shouldReceive('info')
            ->once()
            ->with('Received data content', AbstractNotification::class, $data);
        $this->file->shouldReceive('error')
            ->once()
            ->with('No ID or TOPIC param in Request IPN', IpnNotification::class, $data);
        $this->notification->shouldReceive('setResponse')
            ->once()
            ->with(422, 'No ID or TOPIC param in Request IPN');

        $this->notification->handleReceivedNotification($data);
    }

    public function testProcessStatusDelegatesToOrderStatus(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $data = ['status' => 'approved'];
        $gatewayClass = get_class($this->notification->gateway);
        $this->notification->orderStatus = Mockery::mock(OrderStatus::class);
        $this->notification->orderStatus->shouldReceive('processStatus')
            ->once()
            ->with('approved', $data, $order, $gatewayClass)
            ->andReturn(true);

        $this->assertTrue($this->notification->processStatus('approved', $order, $data));
    }

    public function testUpdateMetaWritesOrderMetadata(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('update_meta_data')
            ->once()
            ->with('_notification_status', 'approved');

        $this->notification->updateMeta($order, '_notification_status', 'approved');
    }

    public function testSetResponseSendsJsonPayloadAndStatus(): void
    {
        $response = ['status' => 202, 'message' => 'Notification accepted'];
        \WP_Mock::userFunction('wp_send_json')
            ->once()
            ->with($response, 202);

        $this->notification->setResponse(202, 'Notification accepted');
    }

    public function testHandleSuccessfulRequestPassesOrderToHandler(): void
    {
        $data = ['external_reference' => 'WC-84'];
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->once()->andReturn(84);

        \WP_Mock::userFunction('get_option')
            ->once()
            ->with('_mp_store_identificator', 'WC-')
            ->andReturn('WC-');
        \WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(84)
            ->andReturn($order);

        $this->file->shouldReceive('info')->twice()->withAnyArgs();
        $this->notification->shouldReceive('handleSuccessfulRequestInternal')
            ->once()
            ->with($data, $order);

        $this->notification->handleSuccessfulRequest($data);
    }

    public function testHandleSuccessfulRequestSendsTerminal422WhenHandlerThrows(): void
    {
        $data = ['external_reference' => 'WC-84'];
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->once()->andReturn(84);
        $response = null;
        $status = null;
        $requestTerminated = new \Error('wp_send_json terminated the request');

        \WP_Mock::userFunction('get_option')
            ->once()
            ->with('_mp_store_identificator', 'WC-')
            ->andReturn('WC-');
        \WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(84)
            ->andReturn($order);
        \WP_Mock::userFunction('wp_send_json')
            ->once()
            ->andReturnUsing(static function ($payload, $statusCode) use (&$response, &$status, $requestTerminated): void {
                $response = $payload;
                $status = $statusCode;

                // WordPress sends the response and terminates instead of returning.
                throw $requestTerminated;
            });

        $this->file->shouldReceive('info')->twice()->withAnyArgs();
        // Logging is outside this response contract; a log before termination is valid.
        $this->file->shouldReceive('error')->zeroOrMoreTimes();
        $this->notification->shouldReceive('handleSuccessfulRequestInternal')
            ->once()
            ->with($data, $order)
            ->andThrow(new Exception('processing failed'));

        try {
            $this->notification->handleSuccessfulRequest($data);
            $this->fail('The JSON response must terminate the notification request.');
        } catch (\Error $termination) {
            $this->assertSame($requestTerminated, $termination);
        }

        $this->assertSame(422, $status);
        $this->assertIsArray($response);
        $this->assertSame(422, $response['status']);
        $this->assertArrayHasKey('message', $response);
        $this->assertIsString($response['message']);
    }
}
