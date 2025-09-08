<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Traits\TransactionMock;
use MercadoPago\Woocommerce\Transactions\AbstractPreferenceTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Mockery;
use stdClass;
use WP_Mock;

class AbstractPreferenceTransactionTest extends TestCase
{
    use TransactionMock;

    private string $transactionClass = AbstractPreferenceTransaction::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|AbstractPreferenceTransaction
     */
    private $transaction;

    public function testCreatePreference(): void
    {
        $this->transaction
            ->expects()
            ->logTransactionPayload();

        $this->transaction->transaction
            ->expects()
            ->save()
            ->andReturn($data = [
                'random' => random()->word()
            ]);

        $this->transaction->mercadopago->logs->file = Mockery::mock(File::class)
            ->expects()
            ->info('Preference created', '', $data)
            ->getMock();

        $this->assertEquals($data, $this->transaction->createPreference());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testSetAutoReturnTransaction(bool $autoReturn): void
    {
        $this->transaction->mercadopago->hooks->options
            ->expects()
            ->getGatewayOption($this->transaction->gateway, 'auto_return')
            ->andReturn($autoReturn ? 'yes' : 'no');

        $this->assertNull($this->transaction->setAutoReturnTransaction());
        $this->assertSame($autoReturn ? 'approved' : null, $this->transaction->transaction->auto_return);
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testSetBackUrlsTransaction(bool $mockOptions): void
    {
        $expected = [
            'success' => random()->url(),
            'failure' => random()->url(),
            'pending' => random()->url(),
        ];

        $order = $this->mockTransactionOrder();

        if ($mockOptions) {
            $this->transaction->mercadopago->hooks->options
                ->expects()->getGatewayOption($this->transaction->gateway, 'success_url')->andReturn($expected['success'])
                ->getMock()->expects()->getGatewayOption($this->transaction->gateway, 'failure_url')->andReturn($expected['failure'])
                ->getMock()->expects()->getGatewayOption($this->transaction->gateway, 'pending_url')->andReturn($expected['pending']);
        } else {
            $this->transaction->mercadopago->hooks->options
                ->expects()
                ->getGatewayOption($this->transaction->gateway, Mockery::type('string'))
                ->times(3)
                ->andReturn(null);
            $this->transaction->mercadopago->helpers->strings
                ->expects()
                ->fixUrlAmpersand(Mockery::type('string'))
                ->times(3)
                ->andReturnArg(0);
            WP_Mock::userFunction('esc_url')
                ->andReturnArg(0);
            $this->transaction->gateway
                ->expects()
                ->get_return_url($order)
                ->times(2)
                ->andReturn($expected['success']);
            $order
                ->expects()
                ->get_cancel_order_url()
                ->andReturn($expected['failure']);
            $expected['pending'] = $expected['success'];
        }

        $this->transaction->transaction->back_urls = new stdClass();
        $this->transaction->setBackUrlsTransaction();

        $this->assertObjectEqualsArray($expected, $this->transaction->transaction->back_urls);
    }

    public function testSetPayerTransaction(): void
    {
        $expected = $this->preferenceSetPayerTransactionMock();

        $this->transaction->setPayerTransaction();

        $this->assertObjectEqualsArray($expected, $this->transaction->transaction->payer);
    }

    /**
     * @testWith [true, true]
     *           [false, false]
     *           [true, false]
     *           [false, true]
     */
    public function testSetCommonTransaction(bool $isTestMode, bool $isTestUser): void
    {
        $expected = [
            'binary_mode' => random()->boolean(),
            'external_reference' => random()->uuid(),
            'notification_url' => random()->url(),
            'metadata' => [],
            'statement_descriptor' => random()->company(),
        ];

        $this->setCommonTransactionMock($expected);

        $this->transaction->mercadopago->storeConfig
            ->expects()
            ->isTestMode()
            ->andReturn($isTestMode);

        $this->transaction->mercadopago->sellerConfig
            ->expects()
            ->isTestUser()
            ->andReturn($isTestUser);

        if (!$isTestMode && !$isTestUser) {
            $expected['sponsor_id'] = random()->numberBetween();
            $this->setNotAccessibleProperty($this->transaction, 'countryConfigs', Arrays::only($expected, 'sponsor_id'));
        }

        $this->transaction->transaction = new stdClass();

        $this->transaction->setCommonTransaction();

        $this->assertObjectEqualsArray($expected, $this->transaction->transaction);
    }
}
