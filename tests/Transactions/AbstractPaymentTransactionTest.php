<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Traits\AssertArrayMap;
use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperty;
use MercadoPago\Woocommerce\Transactions\AbstractPaymentTransaction;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use WC_Order;

class AbstractPaymentTransactionTest extends TestCase
{
    use WoocommerceMock;
    use SetNotAccessibleProperty;
    use AssertArrayMap;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|AbstractPaymentTransaction
     */
    private $transaction;

    public function setUp(): void
    {
        $this->transaction = Mockery::mock(AbstractPaymentTransaction::class)->makePartial();
        $this->transaction->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->transaction->transaction = Mockery::mock(Payment::class)->makePartial();
        $this->transaction->gateway = Mockery::mock(AbstractGateway::class);
    }

    /**
     * @testWith [{"session_id": 1}]
     *           [{"session_id": null}]
     *           [[]]
     */
    public function testCreatePayment(array $checkout): void
    {
        $this->transaction
            ->expects()
            ->logTransactionPayload();

        if ($checkout) {
            $this->setNotAccessibleProperty($this->transaction, 'checkout', $checkout);
        }

        $this->transaction->transaction
            ->expects()
            ->save()
            ->andReturn($data = [
                'random' => random()->word()
            ]);

        $this->transaction->mercadopago->logs->file = Mockery::mock(File::class)
            ->expects()
            ->info('Payment created', '', $data)
            ->getMock();

        $this->assertEquals($data, $this->transaction->createPayment());
        $this->assertEquals($checkout['session_id'] ?? null, $this->transaction->transaction->__get('session_id'));
    }

    public function testSetPayerTransaction(): void
    {
        $this->setNotAccessibleProperty($this->transaction, 'order', $order = Mockery::mock(WC_Order::class));

        $expected = [
            "email" => random()->email(),
            "first_name" => random()->firstName(),
            "last_name" => random()->lastName(),
            "address" => [
                "city" => random()->city(),
                "federal_unit" => random()->stateAbbr(),
                "zip_code" => random()->numerify('########'),
                "street_name" => random()->streetName(),
            ]
        ];

        $this->transaction->mercadopago->orderBilling
            ->expects()->getEmail($order)->andReturn($expected["email"])
            ->getMock()->expects()->getFirstName($order)->andReturn($expected['first_name'])
            ->getMock()->expects()->getLastName($order)->andReturn($expected['last_name'])
            ->getMock()->expects()->getCity($order)->andReturn($expected['address']['city'])
            ->getMock()->expects()->getState($order)->andReturn($expected['address']['federal_unit'])
            ->getMock()->expects()->getZipcode($order)->andReturn($expected['address']['zip_code'])
            ->getMock()->expects()->getFullAddress($order)->andReturn($expected['address']['street_name']);

        $this->transaction->transaction->payer = new stdClass;
        $this->transaction->transaction->payer->address = new stdClass;

        $this->transaction->setPayerTransaction();

        $this->assertEquals(
            $expected,
            json_decode(json_encode($this->transaction->transaction->payer), true)
        );
    }
}
