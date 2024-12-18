<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Transactions\WalletButtonTransaction;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WalletButtonTransactionTest extends TestCase
{
    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetInternalMetadata()
    {
        $expectedPaymentMetadata = new PaymentMetadata();
        $expectedPaymentMetadata->checkout = 'pro';
        $expectedPaymentMetadata->checkout_type = 'wallet_button';

        $walletButton = Mockery::mock(WalletButtonTransaction::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $walletButton->shouldReceive('getInternalMetadataStoreAndSellerInfo')->andReturn(new PaymentMetadata());

        $result = $walletButton->getInternalMetadata();
        $this->assertEquals($expectedPaymentMetadata, $result);
    }
}
