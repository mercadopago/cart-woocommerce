<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperty;
use MercadoPago\Woocommerce\Transactions\AbstractTransaction;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WC_Order;

class AbstractTransactionTest extends TestCase
{
    use WoocommerceMock;
    use SetNotAccessibleProperty;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|AbstractTransaction
     */
    private $transaction;

    public function setUp(): void
    {
        $this->transaction = Mockery::mock(AbstractTransaction::class)->makePartial();
        $this->transaction->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->transaction->gateway = Mockery::mock(AbstractGateway::class);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @doesNotPerformAssertions
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
        $this->setNotAccessibleProperty(
            $this->transaction,
            'order',
            Mockery::mock(WC_Order::class)
                ->expects()
                ->get_id()
                ->andReturn(
                    $orderId = random()->uuid()
                )
                ->getMock()
        );
        $this->transaction->mercadopago->storeConfig
            ->expects()
            ->getStoreId()
            ->andReturn(
                $storeId = random()->uuid()
            );

        $this->assertSame("$storeId$orderId", $this->transaction->getExternalReference());
    }
}
