<?php

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixBuyerError;
use PHPUnit\Framework\TestCase;

class SmartPixBuyerErrorTest extends TestCase
{
    /**
     * @dataProvider rejectionStatusDetailProvider
     */
    public function testGivenARejectionStatusDetailWhenMappedThenTheBuyerGetsTheFigmaScenarioCode(
        ?string $statusDetail,
        string $expectedCode
    ): void {
        $this->assertSame($expectedCode, SmartPixBuyerError::fromRejectionStatusDetail($statusDetail));
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function rejectionStatusDetailProvider(): array
    {
        return [
            'Open Finance and Pix account error' => ['rejected_account_error', SmartPixBuyerError::REJECTED],
            'Open Finance and Pix settlement failure' => ['rejected_settlement_failed', SmartPixBuyerError::REJECTED],
            'Pix business rule' => ['rejected_by_biz_rule', SmartPixBuyerError::REJECTED],
            'Pix regulations' => ['rejected_by_regulations', SmartPixBuyerError::REJECTED],
            'Pix high risk' => ['rejected_high_risk', SmartPixBuyerError::REJECTED],
            'insufficient balance' => ['insufficient_amount', SmartPixBuyerError::INSUFFICIENT_BALANCE],
            'daily limit exceeded' => ['rejected_cap_exceeded', SmartPixBuyerError::LIMIT_EXCEEDED],
            'unknown detail fails safe' => ['rejected_new_upstream_reason', SmartPixBuyerError::REJECTED],
            'missing detail fails safe' => [null, SmartPixBuyerError::REJECTED],
            'empty detail fails safe' => ['', SmartPixBuyerError::REJECTED],
            'uppercase insufficient balance fails safe' => ['INSUFFICIENT_AMOUNT', SmartPixBuyerError::REJECTED],
            'uppercase limit exceeded fails safe' => ['REJECTED_CAP_EXCEEDED', SmartPixBuyerError::REJECTED],
            'padded detail fails safe' => [' insufficient_amount', SmartPixBuyerError::REJECTED],
            'buyer code as detail fails safe' => [SmartPixBuyerError::INSUFFICIENT_BALANCE, SmartPixBuyerError::REJECTED],
        ];
    }

    public function testGivenACodeWhenCheckedThenOnlyTheStableBuyerCodesAreAccepted(): void
    {
        $this->assertTrue(SmartPixBuyerError::isCode(SmartPixBuyerError::PROCESSING_FAILED));
        $this->assertTrue(SmartPixBuyerError::isCode(SmartPixBuyerError::LIMIT_EXCEEDED));
        $this->assertFalse(SmartPixBuyerError::isCode('smart_pix_unavailable'));
        $this->assertFalse(SmartPixBuyerError::isCode('insufficient_amount'));
    }

    public function testGivenACodeWhenCheckedThenOnlyRejectionsAreExpectedOutcomes(): void
    {
        $this->assertTrue(SmartPixBuyerError::isRejection(SmartPixBuyerError::REJECTED));
        $this->assertTrue(SmartPixBuyerError::isRejection(SmartPixBuyerError::INSUFFICIENT_BALANCE));
        $this->assertTrue(SmartPixBuyerError::isRejection(SmartPixBuyerError::LIMIT_EXCEEDED));
        $this->assertFalse(SmartPixBuyerError::isRejection(SmartPixBuyerError::PROCESSING_FAILED));
        $this->assertFalse(SmartPixBuyerError::isRejection('smart_pix_unavailable'));
    }

    public function testGivenTheBundleContractWhenReadThenTheTransportKeysAreStable(): void
    {
        $this->assertSame('mp-smart-pix-error', SmartPixBuyerError::NOTICE_DATA_KEY);
        $this->assertSame('smart_pix_error', SmartPixBuyerError::PAYMENT_DETAILS_KEY);
    }
}
