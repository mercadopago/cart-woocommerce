<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Transactions\CreditsTransaction;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CreditsTransactionTest extends TestCase
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
        $expectedPaymentMetadata->checkout_type = 'credits';

        $credits = Mockery::mock(CreditsTransaction::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $credits->shouldReceive('getInternalMetadataStoreAndSellerInfo')->andReturn(new PaymentMetadata());

        $result = $credits->getInternalMetadata();
        $this->assertEquals($expectedPaymentMetadata, $result);
    }
}
