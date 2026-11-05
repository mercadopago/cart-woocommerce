<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use MercadoPago\Woocommerce\Api\Http\HttpResponse as Response;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\IpnNotification;
use MercadoPago\Woocommerce\Order\OrderStatus;
use Mockery;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WC_Order;

class IpnNotificationTest extends TestCase
{
    use WoocommerceMock;
    use MockeryPHPUnitIntegration;

    /** @var MockInterface|IpnNotification */
    private $notification;

    public function setUp(): void
    {
        $this->notification = Mockery::mock(IpnNotification::class)->makePartial();

        $this->notification->logs = Mockery::mock(Logs::class);
        $this->notification->logs->file = Mockery::mock(File::class)
            ->shouldReceive('info', 'error')
            ->withAnyArgs()
            ->getMock();
    }

    /**
     * @testWith [[]]
     *           [{"id": "random", "topic": "fake"}]
     *           [{"id": "123", "topic": "merchant_order"}, {"status": 422, "data": []}]
     *           [{"id": "123", "topic": "merchant_order"}, {"status": 200, "data": {"payments": []}}]
     *           [{"id": "123", "topic": "merchant_order"}, {"status": 200, "data": {"payments": ["random"]}}]
     */
    public function testHandleReceivedNotification(array $data, array $response = [])
    {
        if (empty($data)) {
            $this->notification->expects()->setResponse(422, Mockery::type('string'));
            $this->notification->handleReceivedNotification($data);
            return;
        }

        if ($data['topic'] !== 'merchant_order') {
            $this->notification->expects()->setResponse(200, Mockery::type('string'));
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
            ->get("/merchant_orders/{$data['id']}", ["Authorization: Bearer $token"])
            ->andReturn(
                Mockery::mock(Response::class)
                    ->expects()
                    ->getStatus()
                    ->andReturn($response['status'])
                    ->getMock()
                    ->expects()
                    ->getData()
                    ->andReturn($response['data'])
                    ->between(1, 3)
                    ->getMock()
            )
            ->getMock();

        if ($response['status'] != 200) {
            $this->notification->expects()->setResponse(422, Mockery::type('string'));
            $this->notification->handleReceivedNotification($data);
            return;
        }

        if (empty($response['data']['payments'])) {
            $this->notification->expects()->setResponse(422, Mockery::type('string'));
            $this->notification->handleReceivedNotification($data);
            return;
        }

        // $response->getData()['ipn_type'] = 'merchant_order';

        $this->notification->expects()->handleSuccessfulRequest($response['data']);

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
     * @testWith [false, "pending"]
     *           [true, "approved"]
     *           [true, "refunded"]
     */
    public function testGetProcessedStatus(bool $completeData, string $status): void
    {
        $order = Mockery::mock(WC_Order::class)
            ->expects()
            ->save()
            ->getMock();

        $data = [
            'payments' => null,
            'shipping_cost' => 0,
            'total_amount' => 1,
        ];

        if ($completeData) {
            $payment = [
                'id' => random()->uuid(),
                'date_created' => random()->date(),
                'transaction_amount' => random()->randomFloat(),
                'total_paid_amount' => 0,
                'amount_refunded' => 0,
            ];

            switch ($status) {
                case 'approved':
                    $payment['status'] = 'approved';
                    $payment['total_paid_amount'] = 1;
                    break;
                case 'refunded':
                    $payment['status'] = 'refunded';
                    $payment['amount_refunded'] = 1;
                    break;
            }

            $data = array_merge($data, [
                'payments' => [
                    $payment
                ],
                'payer' => [
                    'email' => random()->email()
                ],
                'payment_type_id' => random()->word(),
                'payment_method_id' => random()->word(),
            ]);

            $this->notification->expects()->getPaymentInfo($payment['id'])->twice()->andReturn([
                'coupon_amount' => $coupon = random()->numberBetween()
            ]);

            $this->notification
                ->expects()
                ->updateMeta($order, 'Buyer email', $data['payer']['email'])
                ->getMock()
                ->expects()
                ->updateMeta($order, 'Payment type', $data['payment_type_id'])
                ->getMock()
                ->expects()
                ->updateMeta($order, 'Payment method', $data['payment_method_id'])
                ->getMock()
                ->expects()
                ->updateMeta($order, '_Mercado_Pago_Payment_IDs', $payment['id'])
                ->getMock()
                ->expects()
                ->updateMeta(
                    $order,
                    'Mercado Pago - Payment ' . $payment['id'],
                    '[Date ' . gmdate('Y-m-d H:i:s', strtotime($payment['date_created'])) .
                    ']/[Amount ' . $payment['transaction_amount'] .
                    ']/[Paid ' . $payment['total_paid_amount'] .
                    ']/[Coupon ' . $coupon .
                    ']/[Refund ' . $payment['amount_refunded'] . ']'
                );
        }

        $this->notification
            ->expects()
            ->updateMeta($order, '_used_gateway', 'WC_WooMercadoPago_Basic_Gateway');

        $this->assertEquals($status, $this->notification->getProcessedStatus($order, $data));
    }

    /**
     * @dataProvider processedStatusCouponProvider
     */
    public function testGetProcessedStatusCountsOnlyApprovedCoupons(
        float $total,
        float $shipping,
        array $attempts,
        string $expectedStatus
    ): void {
        $order = Mockery::mock(WC_Order::class);
        $order->expects()->save()->once();
        $order->expects()->update_meta_data('_used_gateway', 'WC_WooMercadoPago_Basic_Gateway');

        $this->notification->seller = Mockery::mock(Seller::class);
        $this->notification->seller->shouldReceive('getCredentialsAccessToken')->andReturn('synthetic-ipn-credential');
        $this->notification->requester = Mockery::mock(Requester::class);

        $payments = [];
        $paymentIds = [];
        foreach ($attempts as $index => $attempt) {
            [$status, $paid, $resource, $expectedCoupon, $refunded] = $attempt;
            $id = (string) (101 + $index);
            $paymentIds[] = $id;
            $payments[] = [
                'id' => $id,
                'status' => $status,
                'date_created' => '2026-10-27T12:00:00Z',
                'transaction_amount' => $total,
                'total_paid_amount' => $paid,
                'amount_refunded' => $refunded,
            ];

            $this->notification->requester->expects()
                ->get('/v1/payments/' . $id, ['Authorization: Bearer synthetic-ipn-credential'])
                ->twice()
                ->andReturn(new Response(200, [], '', $resource));
            $order->expects()->update_meta_data(
                'Mercado Pago - Payment ' . $id,
                '[Date 2026-10-27 12:00:00]/[Amount ' . $total .
                ']/[Paid ' . $paid . ']/[Coupon ' . $expectedCoupon . ']/[Refund ' . $refunded . ']'
            );
        }

        $order->expects()->update_meta_data('_Mercado_Pago_Payment_IDs', implode(', ', $paymentIds));

        $this->assertSame($expectedStatus, $this->notification->getProcessedStatus($order, [
            'shipping_cost' => $shipping,
            'total_amount' => $total,
            'payments' => $payments,
        ]));
    }

    public static function processedStatusCouponProvider(): array
    {
        $cases = [
            'coupon covers remaining amount' => [100, 0, [['approved', 90, ['coupon_amount' => 10], 10, 0]], 'approved'],
            'partial payment without coupon' => [100, 0, [['approved', 99, ['coupon_amount' => 0], 0, 0]], 'pending'],
            'pending payment on low value order' => [1, 0, [['pending', 0, ['coupon_amount' => 0], 0, 0]], 'pending'],
            'rejected payment on low value order' => [1, 0, [['rejected', 0, ['coupon_amount' => 0], 0, 0]], 'pending'],
            'rejected attempt cannot cover shortfall' => [101, 0, [
                ['approved', 99, ['coupon_amount' => 0], 0, 0],
                ['rejected', 0, ['coupon_amount' => 0], 0, 0],
            ], 'pending'],
            'several unsuccessful attempts' => [101, 0, [
                ['approved', 99, ['coupon_amount' => 0], 0, 0],
                ['pending', 0, ['coupon_amount' => 100], 100, 0],
                ['rejected', 0, ['coupon_amount' => 100], 100, 0],
            ], 'pending'],
            'full approved payment without coupon' => [100, 0, [['approved', 100, ['coupon_amount' => 0], 0, 0]], 'approved'],
            'numeric string coupon' => [100, 0, [['approved', 89.5, ['coupon_amount' => '10.50'], 10.5, 0]], 'approved'],
            'several approved payments and coupons' => [100, 0, [
                ['approved', 40, ['coupon_amount' => 5], 5, 0],
                ['approved', 50, ['coupon_amount' => 5], 5, 0],
            ], 'approved'],
            'shipping is covered' => [100, 10, [['approved', 100, ['coupon_amount' => 10], 10, 0]], 'approved'],
            'shipping is not covered' => [100, 10, [['approved', 99, ['coupon_amount' => 10], 10, 0]], 'pending'],
            'full refund including shipping' => [100, 10, [['refunded', 0, ['coupon_amount' => 10], 10, 110]], 'refunded'],
            'partial refund does not approve order' => [100, 0, [['refunded', 0, ['coupon_amount' => 100], 100, 99]], 'pending'],
        ];

        foreach (['pending', 'rejected', 'in_process', 'authorized', 'cancelled', 'charged_back'] as $status) {
            $cases[$status . ' ignores paid amount and positive coupon'] = [
                1, 0, [[$status, 100, ['coupon_amount' => 100], 100, 0]], 'pending',
            ];
        }

        $invalidCoupons = [
            'absent' => [],
            'null' => ['coupon_amount' => null],
            'text' => ['coupon_amount' => 'not-a-number'],
            'boolean' => ['coupon_amount' => true],
            'array' => ['coupon_amount' => [10]],
            'negative' => ['coupon_amount' => -10],
            'infinite' => ['coupon_amount' => INF],
            'nan' => ['coupon_amount' => NAN],
            'overflow string' => ['coupon_amount' => '1e999'],
            'null resource' => null,
            'scalar resource' => 'invalid-resource',
        ];
        foreach ($invalidCoupons as $name => $resource) {
            $cases[$name . ' coupon cannot cover partial payment'] = [100, 0, [['approved', 99, $resource, 0, 0]], 'pending'];
            $cases[$name . ' coupon preserves full payment'] = [100, 0, [['approved', 100, $resource, 0, 0]], 'approved'];
        }

        return $cases;
    }

    public function testGetPaymentInfo(): void
    {
        $this->notification->seller = Mockery::mock(Seller::class)
            ->expects()
            ->getCredentialsAccessToken()
            ->andReturn($token = random()->uuid())
            ->getMock();

        $id = random()->numberBetween();

        $resource = ['id' => (string) $id, 'coupon_amount' => 10, 'status' => 'approved'];

        $this->notification->requester = Mockery::mock(Requester::class)
            ->expects()
            ->get("/v1/payments/$id", ["Authorization: Bearer $token"])
            ->andReturn(
                Mockery::mock(Response::class)
                    ->expects()
                    ->getData()
                    ->andReturn($resource)
                    ->getMock()
            )
            ->getMock();

        $this->assertSame($resource, $this->notification->getPaymentInfo($id));
    }
}
