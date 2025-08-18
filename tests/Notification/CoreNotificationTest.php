<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use Mockery;
use WP_Mock;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\PP\Sdk\Sdk;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CoreNotificationTest extends TestCase
{
    use WoocommerceMock;

    private MercadoPagoGatewayInterface $gateway;
    private Logs $logs;
    private OrderStatus $orderStatus;
    private Seller $seller;
    private Store $store;

    public static $mockInput = null;
    private $coreNotification;

    public function setUp(): void
    {
        // Mock WordPress wp_is_mobile function
        WP_Mock::userFunction('wp_is_mobile', [
            'return' => false
        ]);

        WP_Mock::userFunction('site_url', [
            'return' => 'https://test.com'
        ]);

        // Mock WooCommerce global
        if (!isset($GLOBALS['woocommerce'])) {
            $GLOBALS['woocommerce'] = (object) ['version' => '8.0.0'];
        }

        // Define necessary constants
        if (!defined('MP_PRODUCT_ID_MOBILE')) {
            define('MP_PRODUCT_ID_MOBILE', 'WOOCOMMERCE_MP_TEST_MOBILE');
        }
        if (!defined('MP_VERSION')) {
            define('MP_VERSION', '8.2.0');
        }
        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }

        // Mock dependencies
        $this->gateway = Mockery::mock(MercadoPagoGatewayInterface::class);
        $this->logs = Mockery::mock(Logs::class);
        $this->logs->file = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $this->logs->file->shouldReceive('info')->andReturn(true);
        $this->logs->file->shouldReceive('error')->andReturn(true);
        $this->logs->file->shouldReceive('debug')->andReturn(true);
        $this->logs->file->shouldReceive('notice')->andReturn(true);
        $this->logs->file->shouldReceive('warning')->andReturn(true);

        $this->orderStatus = Mockery::mock(OrderStatus::class);
        $this->seller = Mockery::mock(Seller::class);
        $this->seller->shouldReceive('getCredentialsAccessToken')->andReturn('test_token');

        $this->store = Mockery::mock(Store::class);
        $this->store->shouldReceive('getIntegratorId')->andReturn('test_integrator');

        $this->coreNotification = new CoreNotification(
            $this->gateway,
            $this->logs,
            $this->orderStatus,
            $this->seller,
            $this->store
        );
    }


    public function testUpdatePaymentDetailsWithCreditCardPayment()
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('update_meta_data')->andReturn(true);

        $data = [
            'payments_details' => [
                [
                    'id' => 'payment_123',
                    'payment_type_id' => 'credit_card',
                    'total_amount' => 100.00,
                    'paid_amount' => 100.00,
                    'payment_method_info' => [
                        'installments' => 1,
                        'installment_amount' => 100.00,
                        'last_four_digits' => '1234'
                    ]
                ]
            ]
        ];

        $this->coreNotification->updatePaymentDetails($order, $data);

        $this->assertTrue(true);
    }

    public function testUpdatePaymentDetailsWithPixPayment()
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('update_meta_data')->andReturn(true);

        $data = [
            'payments_details' => [
                [
                    'id' => 'payment_456',
                    'payment_type_id' => 'pix',
                    'total_amount' => 50.00,
                    'paid_amount' => 50.00,
                    'payment_method_info' => []
                ]
            ]
        ];

        $this->coreNotification->updatePaymentDetails($order, $data);

        $this->assertTrue(true);
    }

    public function testUpdatePaymentDetailsWithRefundNotification()
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('update_meta_data')->andReturn(true);

        $data = [
            'payments_details' => [
                [
                    'id' => 'payment_789',
                    'payment_type_id' => 'credit_card',
                    'total_amount' => 100.00,
                    'paid_amount' => 100.00,
                    'payment_method_info' => [
                        'installments' => 1,
                        'installment_amount' => 100.00,
                        'last_four_digits' => '1234'
                    ],
                    'refunds' => [
                        'refund_123' => [
                            'amount' => 30.00,
                            'status' => 'approved'
                        ]
                    ]
                ]
            ],
            'current_refund' => [
                'id' => 'refund_123',
                'amount' => 30.00,
                'status' => 'approved'
            ],
            'refunds_notifying' => [
                [
                    'id' => 'refund_123',
                    'amount' => 30.00,
                    'status' => 'approved'
                ]
            ]
        ];

        $this->coreNotification->updatePaymentDetails($order, $data);

        $this->assertTrue(true);
    }

    public function testUpdatePaymentDetailsWithPartialRefund()
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('update_meta_data')->andReturn(true);

        $data = [
            'payments_details' => [
                [
                    'id' => 'payment_999',
                    'payment_type_id' => 'pix',
                    'total_amount' => 200.00,
                    'paid_amount' => 200.00,
                    'payment_method_info' => [],
                    'refunds' => [
                        'refund_456' => [
                            'amount' => 50.00,
                            'status' => 'approved'
                        ]
                    ]
                ]
            ],
            'current_refund' => [
                'id' => 'refund_456',
                'amount' => 50.00,
                'status' => 'approved'
            ],
            'refunds_notifying' => [
                [
                    'id' => 'refund_456',
                    'amount' => 50.00,
                    'status' => 'approved'
                ]
            ]
        ];

        $this->coreNotification->updatePaymentDetails($order, $data);

        $this->assertTrue(true);
    }

    public function testUpdatePaymentDetailsWithMultiplePayments()
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('update_meta_data')->andReturn(true);

        $data = [
            'payments_details' => [
                [
                    'id' => 'payment_001',
                    'payment_type_id' => 'credit_card',
                    'total_amount' => 150.00,
                    'paid_amount' => 150.00,
                    'payment_method_info' => [
                        'installments' => 3,
                        'installment_amount' => 50.00,
                        'last_four_digits' => '5678'
                    ]
                ],
                [
                    'id' => 'payment_002',
                    'payment_type_id' => 'pix',
                    'total_amount' => 75.00,
                    'paid_amount' => 75.00,
                    'payment_method_info' => []
                ],
                [
                    'id' => 'payment_003',
                    'payment_type_id' => 'debit_card',
                    'total_amount' => 25.00,
                    'paid_amount' => 25.00,
                    'payment_method_info' => [
                        'installments' => 1,
                        'installment_amount' => 25.00,
                        'last_four_digits' => '9012'
                    ]
                ]
            ],
            'current_refund' => [
                'id' => '',
                'amount' => 0
            ]
        ];

        $this->coreNotification->updatePaymentDetails($order, $data);

        $this->assertTrue(true);
    }

    public function testUpdatePaymentDetailsWithoutCurrentRefund()
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('update_meta_data')->andReturn(true);

        $data = [
            'payments_details' => [
                [
                    'id' => 'payment_777',
                    'payment_type_id' => 'credit_card',
                    'total_amount' => 80.00,
                    'paid_amount' => 80.00,
                    'payment_method_info' => [
                        'installments' => 2,
                        'installment_amount' => 40.00,
                        'last_four_digits' => '8888'
                    ],
                    'refunds' => [
                        'refund_999' => [
                            'amount' => 20.00,
                            'status' => 'approved'
                        ]
                    ]
                ]
            ]
        ];

        $this->coreNotification->updatePaymentDetails($order, $data);

        $this->assertTrue(true);
    }

    /**
     * Test sendRefundSuccessMetric method calls Datadog correctly
     */
    public function testSendRefundSuccessMetric(): void
    {
        // Arrange
        $datadogMock = Mockery::mock('alias:\MercadoPago\Woocommerce\Libraries\Metrics\Datadog');
        $datadogMock->shouldReceive('getInstance')
            ->once()
            ->andReturn($datadogMock);

        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_refund_success', 'refund_success', 'origin_mercadopago');

        $reflection = new \ReflectionClass($this->coreNotification);
        $method = $reflection->getMethod('sendRefundSuccessMetric');
        $method->setAccessible(true);

        // Act
        $method->invoke($this->coreNotification);

        // Assert - handled by Mockery expectations
        $this->assertTrue(true);
    }

    /**
     * Test sendRefundErrorMetric method calls Datadog correctly
     */
    public function testSendRefundErrorMetric(): void
    {
        // Arrange
        $errorCode = 'validation_failed';
        $errorMessage = 'Refund ID not found in notification';

        $datadogMock = Mockery::mock('alias:\MercadoPago\Woocommerce\Libraries\Metrics\Datadog');
        $datadogMock->shouldReceive('getInstance')
            ->once()
            ->andReturn($datadogMock);

        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_refund_error', $errorCode, $errorMessage);

        $reflection = new \ReflectionClass($this->coreNotification);
        $method = $reflection->getMethod('sendRefundErrorMetric');
        $method->setAccessible(true);

        // Act
        $method->invoke($this->coreNotification, $errorCode, $errorMessage);

        // Assert - handled by Mockery expectations
        $this->assertTrue(true);
    }

    public function testGetNotificationIdWithString()
    {
        $mock = Mockery::mock(CoreNotification::class, [
            $this->gateway,
            $this->logs,
            $this->orderStatus,
            $this->seller,
            $this->store
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $mock->shouldReceive('getInput')
            ->andReturn(json_encode('P-12345'));

        $result = $mock->getNotificationId();
        $this->assertEquals('P-12345', $result);
    }

    public function testGetNotificationIdWithObject()
    {
        $mock = Mockery::mock(CoreNotification::class, [
            $this->gateway,
            $this->logs,
            $this->orderStatus,
            $this->seller,
            $this->store
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $mock->shouldReceive('getInput')
            ->andReturn(json_encode(['notification_id' => 'P-67890']));

        $result = $mock->getNotificationId();
        $this->assertEquals('P-67890', $result);
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

        defined('MP_PRODUCT_ID_MOBILE') || define('MP_PRODUCT_ID_MOBILE', 'product-id-mobile-teste');

        $this->store->shouldReceive('getIntegratorId')->andReturn('integrator-id-teste');
        $this->seller->shouldReceive('getCredentialsAccessToken')->andReturn('access-token-teste');

        $this->coreNotification->getSdkInstance();
        $this->assertInstanceOf(Sdk::class, $this->coreNotification->getSdkInstance());
    }
}
