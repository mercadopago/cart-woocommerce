<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Strings;
use Mockery;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WC_Order;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CoreNotificationTest extends TestCase
{
    use WoocommerceMock;
    use MockeryPHPUnitIntegration;

    private $notification;

    public function setUp(): void
    {
        $this->notification = new CoreNotification(
            Mockery::mock(MercadoPagoGatewayInterface::class),
            Mockery::mock(Logs::class),
            Mockery::mock(OrderStatus::class),
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class)
        );
    }

    /**
     * @testWith [[],[]]
     *           [{"refunds_notifying": "fake"},{"payment_type_id": "creditcard", "payment_method_info": {"installments": 2, "installment_amount": 10, "last_four_digits": "1234"}, "total_amount":10, "paid_amount": 10}]
     *           [{"current_refund": {"id": 1, "amount": 1}},{"refunds": {"1":{}}}]
     */
    public function testUpdatePaymentDetails(array $data, array $payment)
    {
        $data = array_merge([
            "payments_details" => [
                $payment = array_merge([
                    "id" => random()->numberBetween(1),
                    "payment_type_id" => random()->word()
                ], $payment)
            ]
        ], $data);

        $paymentData = (object) [
            "refund" => random()->optional()->numberBetween()
        ];

        $refundedAmount = $paymentData->refund ?? 0;
        if (isset($data["current_refund"]) && isset($payment["refunds"][$data["current_refund"]["id"]])) {
            $refundedAmount += $data["current_refund"]["amount"];
        }

        Mockery::getConfiguration()->setConstantsMap([
            PaymentMetadata::class => [
                'PAYMENT_IDS_META_KEY' => random()->word(),
            ]
        ]);

        $paymentMetadata = Mockery::mock("overload:" . PaymentMetadata::class)
            ->expects()
            ->getPaymentMetaKey($payment["id"])
            ->andReturn("PaymentMetaKey")
            ->getMock()
            ->expects()
            ->extractPaymentDataFromMeta(null)
            ->andReturn($paymentData)
            ->getMock()
            ->expects()
            ->formatPaymentMetadata($payment, $refundedAmount)
            ->andReturn(["formatedPaymentMetadata"])
            ->getMock();

        $order = Mockery::mock(WC_Order::class)
            ->expects()
            ->get_meta("PaymentMetaKey")
            ->getMock()
            ->expects()
            ->update_meta_data("PaymentMetaKey", ["formatedPaymentMetadata"])
            ->getMock();

        if (Strings::contains($payment["payment_type_id"], "card")) {
            $order
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/installments$/"),
                    $payment["payment_method_info"]["installments"]
                )
                ->getMock()
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/installment_amount$/"),
                    $payment["payment_method_info"]["installment_amount"]
                )
                ->getMock()
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/transaction_amount$/"),
                    $payment["total_amount"]
                )
                ->getMock()
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/total_paid_amount$/"),
                    $payment["paid_amount"]
                )
                ->getMock()
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/card_last_four_digits$/"),
                    $payment["payment_method_info"]["last_four_digits"]
                );
        }

        if (!isset($data["refunds_notifying"])) {
            $paymentMetadata
                ->expects()
                ->joinPaymentIds([$payment["id"]])
                ->andReturn($payment["id"]);
            $order
                ->expects()
                ->update_meta_data(PaymentMetadata::PAYMENT_IDS_META_KEY, $payment["id"]);
        }

        $this->notification->updatePaymentDetails($order, $data);
    }

    /**
     * @testWith [{"notification_id": "P-67890"}]
     *           ["P-67890"]
     */
    public function testGetNotificationId($input)
    {
        $notification = Mockery::mock(CoreNotification::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects()
            ->getInput()
            ->andReturn(json_encode($input))
            ->getMock();

        $this->assertEquals("P-67890", $notification->getNotificationId());
    }

    /**
     * @testWith ["P-12345", true]
     *           ["M-12345", true]
     *           ["12345", false]
     *           ["P-12345-12345", false]
     *           ["P12345", false]
     *           ["P-ABCDE", false]
     *           ["P-", false]
     */
    public function testValidateNotificationId(string $id, bool $expected)
    {
        $this->assertEquals($expected, $this->notification->validateNotificationId($id));
    }

    public function testGetSdkInstance()
    {
        $this->notification->seller
            ->expects()
            ->getCredentialsAccessToken()
            ->andReturn(
                $accessToken = random()->uuid()
            );

        Mockery::mock("alias:" . Device::class)
            ->expects()
            ->getDeviceProductId()
            ->andReturn(
                $productId = random()->uuid()
            );

        $this->notification->store
            ->expects()
            ->getIntegratorId()
            ->andReturn(
                $integratorId = random()->uuid()
            );

        Mockery::mock("overload:" . Sdk::class)
            ->shouldReceive("__construct")
            ->once()
            ->with($accessToken, MP_PLATFORM_ID, $productId, $integratorId);

        $this->assertInstanceOf(Sdk::class, $this->notification->getSdkInstance());
    }

    /**
     * @testWith [[]]
     *           [{"payer": {"email": "fake@fake"}}]
     *           [{"payments_details": {"fake": "fake"}}]
     */
    public function testGetProcessedStatus(array $data): void
    {
        $data = array_merge([
            'status' => random()->word()
        ], $data);

        $order = Mockery::mock(WC_Order::class)
            ->expects()
            ->save()
            ->getMock();

        if (!empty($data['payer']['email'])) {
            $order
                ->expects()
                ->update_meta_data('Buyer email', $data['payer']['email']);
        }

        $notification = Mockery::mock(CoreNotification::class)->makePartial();

        if (!empty($data['payments_details'])) {
            $notification
                ->expects()
                ->updatePaymentDetails($order, $data);
        }

        $notification->getProcessedStatus($order, $data);
    }
}
