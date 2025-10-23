<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\WebhookNotification;
use MercadoPago\Woocommerce\Order\OrderStatus;
use Mockery;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WC_Order;

class WebhookNotificationTest extends TestCase
{
    use WoocommerceMock;
    use MockeryPHPUnitIntegration;

    /** @var MockInterface|WebhookNotification */
    private $notification;

    public function setUp(): void
    {
        $logs = Mockery::mock(Logs::class);
        $logs->file = Mockery::mock(File::class)
            ->shouldReceive('info', 'error')
            ->withAnyArgs()
            ->getMock();

        $this->notification = Mockery::mock(WebhookNotification::class)->makePartial();
        $this->notification->logs = $logs;
    }

    /**
     * @testWith [[]]
     *           [{"data_id": "random", "type": "fake"}]
     *           [{"data_id": "123", "type": "payment"}, {"status": 422}]
     */
    public function testHandleReceivedNotification(array $data, array $response = [])
    {
        if (empty($data) || $data['type'] != 'payment') {
            $this->notification->expects()->setResponse(422, Mockery::type('string'));
            $this->notification->handleReceivedNotification($data);
            return;
        }

        $this->notification->seller = Mockery::mock(Seller::class)
            ->expects()
            ->getCredentialsAccessToken()
            ->andReturn($token = random()->uuid())
            ->getMock();
        $this->notification->requester = Mockery::mock(Requester::class)
            ->expects()
            ->get("/v1/payments/{$data['data_id']}", ["Authorization: Bearer $token"])
            ->andReturn(
                $responseMock = Mockery::mock(Response::class)
                    ->expects()
                    ->getStatus()
                    ->andReturn($response['status'])
                    ->getMock()
            )
            ->getMock();

        if ($response['status'] != 200) {
            $this->notification->expects()->setResponse(422, Mockery::type('string'));
            $this->notification->handleReceivedNotification($data);
            return;
        }

        $responseMock
            ->expects()
            ->getData()
            ->andReturn([]);

        $this->notification->expects()->handleSuccessfulRequest([]);

        $this->notification->handleReceivedNotification($data);
    }

    public function testHandleSuccessfulRequestInternal(): void
    {
        $data = [];
        $order = Mockery::mock(WC_Order::class)
            ->expects()
            ->get_status()
            ->getMock();

        $this->notification
            ->expects()
            ->getProcessedStatus($order, $data)
            ->andReturn($processedStatus = random()->word() . '_');

        $this->notification->orderStatus = Mockery::mock(OrderStatus::class)
            ->expects()
            ->mapMpStatusToWoocommerceStatus(str_replace('_', '', $processedStatus))
            ->getMock();

        $this->notification
            ->expects()
            ->processStatus($processedStatus, $order, $data);

        $this->notification->expects()->setResponse(200, Mockery::type('string'));

        $this->notification->handleSuccessfulRequestInternal($data, $order);
    }

    /**
     * @testWith [false]
     *           [true]
     */
    public function testGetProcessedStatus(bool $completeData): void
    {
        $order = Mockery::mock(WC_Order::class)
            ->expects()
            ->save()
            ->getMock();

        $data = [
            'id' => random()->uuid(),
            'date_created' => random()->date(),
            'transaction_amount' => random()->randomFloat(),
        ];

        if ($completeData) {
            $data = array_merge($data, [
                'status' => random()->word(),
                'transaction_details' => [
                    'total_paid_amount' => random()->randomFloat()
                ],
                'transaction_amount_refunded' => random()->randomFloat(),
                'coupon_amount' => random()->randomFloat(),
                'payer' => [
                    'email' => random()->email()
                ],
                'payment_type_id' => random()->word(),
                'payment_method_id' => random()->word(),
            ]);

            $this->notification
                ->expects()
                ->updateMeta($order, 'Buyer email', $data['payer']['email'])
                ->getMock()
                ->expects()
                ->updateMeta($order, 'Payment type', $data['payment_type_id'])
                ->getMock()
                ->expects()
                ->updateMeta($order, 'Payment method', $data['payment_method_id']);
        }

        $this->notification
            ->expects()
            ->updateMeta($order, '_used_gateway', get_class($this->notification))
            ->getMock()
            ->expects()
            ->updateMeta(
                $order,
                'Mercado Pago - Payment ' . $data['id'],
                '[Date ' . gmdate('Y-m-d H:i:s', strtotime($data['date_created'])) .
                ']/[Amount ' . $data['transaction_amount'] .
                ']/[Paid ' . ($data['transaction_details']['total_paid_amount'] ?? 0.00) .
                ']/[Coupon ' . ($data['coupon_amount'] ?? 0.00) .
                ']/[Refund ' . ($data['transaction_amount_refunded'] ?? 0.00) . ']'
            )
            ->getMock()
            ->expects()
            ->updateMeta($order, '_Mercado_Pago_Payment_IDs', $data['id']);

        $this->assertEquals($data['status'] ?? 'pending', $this->notification->getProcessedStatus(
            $order,
            $data
        ));
    }
}
