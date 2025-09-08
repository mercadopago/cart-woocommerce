<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Transactions\AbstractPaymentTransaction;
use MercadoPago\Woocommerce\Transactions\AbstractTransaction;
use Mockery;
use Mockery\MockInterface;
use stdClass;
use WC_Order;

trait TransactionMock
{
    use WoocommerceMock;
    use AssertObject;
    use SetNotAccessibleProperty;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|AbstractTransaction
     */
    private $transaction;

    /**
     * @before
     */
    public function transactionSetup()
    {
        $this->transaction = Mockery::mock($this->transactionClass)->makePartial();
        $this->transaction->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->transaction->gateway = Mockery::mock(AbstractGateway::class);
        $this->transaction->transaction = Mockery::mock(
            $this->transaction instanceof AbstractPaymentTransaction ? Payment::class : Preference::class
        );
    }

    private function setCommonTransactionMock(array $expected): void
    {
        $this->transaction
            ->expects()->getBinaryMode()->andReturn($expected['binary_mode'])
            ->getMock()->expects()->getExternalReference()->andReturn($expected['external_reference'])
            ->getMock()->shouldAllowMockingProtectedMethods()->expects()->getNotificationUrl()->andReturn($expected['notification_url'])
            ->getMock()->expects()->getInternalMetadata()->andReturn(new PaymentMetadata());

        $this->transaction->mercadopago->storeConfig
            ->expects()
            ->getStoreName('Mercado Pago')
            ->andReturn($expected['statement_descriptor']);
    }

    /**
     * @return MockInterface|WC_Order
     */
    private function mockTransactionOrder()
    {
        $order = Mockery::mock(WC_Order::class);
        $this->setNotAccessibleProperty($this->transaction, 'order', $order);
        return $order;
    }

    public function preferenceSetPayerTransactionMock(array $expected = []): array
    {
        $expected = array_merge([
            'email' => random()->email(),
            'name' => random()->firstName(),
            'surname' => random()->lastName(),
            'phone' => [
                'number' => random()->phoneNumber()
            ],
            'address' => [
                'zip_code' => random()->postcode(),
                'street_name' => random()->streetAddress(),
            ]
        ], $expected);

        $order = $this->mockTransactionOrder();

        $this->transaction->mercadopago->orderBilling
            ->expects()->getEmail($order)->andReturn($expected['email'])
            ->getMock()->expects()->getFirstName($order)->andReturn($expected['name'])
            ->getMock()->expects()->getLastName($order)->andReturn($expected['surname'])
            ->getMock()->expects()->getPhone($order)->andReturn($expected['phone']['number'])
            ->getMock()->expects()->getZipcode($order)->andReturn($expected['address']['zip_code'])
            ->getMock()->expects()->getFullAddress($order)->andReturn($expected['address']['street_name']);

        $this->transaction->transaction->payer = new stdClass();
        $this->transaction->transaction->payer->phone = new stdClass();
        $this->transaction->transaction->payer->address = new stdClass();

        return $expected;
    }

    public function paymentSetPayerTransactionMock(array $expected = []): array
    {
        $expected = array_merge([
            'email' => random()->email(),
            'first_name' => random()->firstName(),
            'last_name' => random()->lastName(),
            'address' => [
                'city' => random()->city(),
                'federal_unit' => random()->stateAbbr(),
                'zip_code' => random()->postcode(),
                'street_name' => random()->streetName(),
            ]
        ], $expected);

        $order = $this->mockTransactionOrder();

        $this->transaction->mercadopago->orderBilling
            ->expects()->getEmail($order)->andReturn($expected['email'])
            ->getMock()->expects()->getFirstName($order)->andReturn($expected['first_name'])
            ->getMock()->expects()->getLastName($order)->andReturn($expected['last_name'])
            ->getMock()->expects()->getCity($order)->andReturn($expected['address']['city'])
            ->getMock()->expects()->getState($order)->andReturn($expected['address']['federal_unit'])
            ->getMock()->expects()->getZipcode($order)->andReturn($expected['address']['zip_code'])
            ->getMock()->expects()->getFullAddress($order)->andReturn($expected['address']['street_name']);

        $this->transaction->transaction->payer = new stdClass();
        $this->transaction->transaction->payer->address = new stdClass();

        return $expected;
    }
}
