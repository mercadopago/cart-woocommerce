<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use WP_Mock;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;

class PaymentMetadataTest extends TestCase
{
    public function setUp(): void
    {
        WP_Mock::setUp();

        if (!class_exists('MercadoPago\Woocommerce\Helpers\Date')) {
            WP_Mock::userFunction('MercadoPago\Woocommerce\Helpers\Date::getNowDate')
                ->with('Y-m-d H:i:s')
                ->andReturn('2024-01-15 10:30:00');
        }
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    // Test parsePaymentFieldData with valid formatted data
    public function testParsePaymentFieldDataWithValidData()
    {
        $fieldArray = [
            '[Date 2024-01-15 10:30:00]',
            '[Amount 150.75]',
            '[Payment Type credit_card]',
            '[Payment Method visa]',
            '[Paid 150.75]',
            '[Coupon 0]',
            '[Refund 0]'
        ];

        $result = PaymentMetadata::parsePaymentFieldData($fieldArray);

        $this->assertEquals('2024-01-15 10:30:00', $result->date);
        $this->assertEquals(150.75, $result->amount);
        $this->assertEquals('credit_card', $result->payment_type);
        $this->assertEquals('visa', $result->payment_method);
        $this->assertEquals(150.75, $result->paid);
        $this->assertEquals(0.0, $result->coupon);
        $this->assertEquals(0.0, $result->refund);
    }

    // Test parsePaymentFieldData with empty array
    public function testParsePaymentFieldDataWithEmptyArray()
    {
        $fieldArray = [];
        $result = PaymentMetadata::parsePaymentFieldData($fieldArray);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertFalse(property_exists($result, 'date'));
        $this->assertFalse(property_exists($result, 'amount'));
    }

    // Test parsePaymentFieldData with malformed data
    public function testParsePaymentFieldDataWithMalformedData()
    {
        $fieldArray = [
            'Invalid data without brackets',
            '[Date]', // Missing value
            '[Amount]', // Missing value
            '[Payment Type credit_card]', // Valid
            'Another invalid entry'
        ];

        $result = PaymentMetadata::parsePaymentFieldData($fieldArray);

        $this->assertEquals('credit_card', $result->payment_type);
        $this->assertFalse(property_exists($result, 'date'));
        $this->assertFalse(property_exists($result, 'amount'));
    }

    // Test parsePaymentFieldData with mixed numeric and string values
    public function testParsePaymentFieldDataWithMixedValues()
    {
        $fieldArray = [
            '[Amount 99.99]',
            '[Payment Type pix]',
            '[Paid 99.99]',
            '[Custom Field text_value]'
        ];

        $result = PaymentMetadata::parsePaymentFieldData($fieldArray);

        $this->assertEquals(99.99, $result->amount);
        $this->assertEquals('pix', $result->payment_type);
        $this->assertEquals(99.99, $result->paid);
        $this->assertEquals('text_value', $result->custom_field);
    }

    // Test parsePaymentFieldData with whitespace handling
    public function testParsePaymentFieldDataWithWhitespace()
    {
        $fieldArray = [
            '  [Date 2024-01-15]  ',
            ' [Amount  100.50 ] ',
            '[Payment Type  visa  ]'
        ];

        $result = PaymentMetadata::parsePaymentFieldData($fieldArray);

        $this->assertEquals('2024-01-15', $result->date);
        $this->assertEquals(100.50, $result->amount);
        $this->assertEquals('visa', $result->payment_type);
    }

    // Test formatPaymentMetadata with complete data
    public function testFormatPaymentMetadataWithCompleteData()
    {
        $paymentData = [
            'date' => '2024-01-15 10:30:00',
            'total_amount' => 150.75,
            'payment_type_id' => 'credit_card',
            'payment_method_id' => 'visa',
            'paid_amount' => 150.75,
            'coupon_amount' => 5.00
        ];
        $refundedAmount = 25.50;

        $result = PaymentMetadata::formatPaymentMetadata($paymentData, $refundedAmount);

        // Check if result contains expected parts
        $this->assertStringContainsString('[Date 2024-01-15 10:30:00]', $result);
        $this->assertStringContainsString('[Amount 150.75]', $result);
        $this->assertStringContainsString('[Payment Type credit_card]', $result);
        $this->assertStringContainsString('[Payment Method visa]', $result);
        $this->assertStringContainsString('[Paid 150.75]', $result);
        $this->assertStringContainsString('[Coupon 5]', $result);
        $this->assertStringContainsString('[Refund 25.5]', $result);
    }

    // Test formatPaymentMetadata with missing data and defaults
    public function testFormatPaymentMetadataWithMissingData()
    {
        $paymentData = [
            'total_amount' => 100.00
            // Missing other fields
        ];
        $refundedAmount = 0.0;

        $result = PaymentMetadata::formatPaymentMetadata($paymentData, $refundedAmount);

        $this->assertStringContainsString('[Amount 100]', $result);
        $this->assertStringContainsString('[Payment Type ]', $result);
        $this->assertStringContainsString('[Payment Method ]', $result);
        $this->assertStringContainsString('[Paid 0]', $result);
        $this->assertStringContainsString('[Coupon 0]', $result);
        $this->assertStringContainsString('[Refund 0]', $result);
    }

    // Test formatPaymentMetadata with zero refund amount
    public function testFormatPaymentMetadataWithZeroRefund()
    {
        $paymentData = [
            'date' => '2024-01-15 10:30:00',
            'total_amount' => 50.00,
            'payment_type_id' => 'pix',
            'payment_method_id' => 'pix',
            'paid_amount' => 50.00,
            'coupon_amount' => 0
        ];
        $refundedAmount = 0.0;

        $result = PaymentMetadata::formatPaymentMetadata($paymentData, $refundedAmount);

        $this->assertStringContainsString('[Refund 0]', $result);
        $this->assertStringContainsString('[Amount 50]', $result);
        $this->assertStringContainsString('[Payment Type pix]', $result);
    }

    // Test getPaymentMetaKey
    public function testGetPaymentMetaKey()
    {
        $paymentId = '12345678';
        $result = PaymentMetadata::getPaymentMetaKey($paymentId);

        $expected = 'Mercado Pago - Payment 12345678';
        $this->assertEquals($expected, $result);
    }

    // Test getPaymentMetaKey with empty string
    public function testGetPaymentMetaKeyWithEmptyString()
    {
        $paymentId = '';
        $result = PaymentMetadata::getPaymentMetaKey($paymentId);

        $expected = 'Mercado Pago - Payment ';
        $this->assertEquals($expected, $result);
    }

    // Test joinPaymentIds with multiple IDs
    public function testJoinPaymentIdsWithMultipleIds()
    {
        $paymentIds = ['12345', '67890', '11111'];
        $result = PaymentMetadata::joinPaymentIds($paymentIds);

        $expected = '12345, 67890, 11111';
        $this->assertEquals($expected, $result);
    }

    // Test joinPaymentIds with single ID
    public function testJoinPaymentIdsWithSingleId()
    {
        $paymentIds = ['12345'];
        $result = PaymentMetadata::joinPaymentIds($paymentIds);

        $expected = '12345';
        $this->assertEquals($expected, $result);
    }

    // Test joinPaymentIds with empty array
    public function testJoinPaymentIdsWithEmptyArray()
    {
        $paymentIds = [];
        $result = PaymentMetadata::joinPaymentIds($paymentIds);

        $expected = '';
        $this->assertEquals($expected, $result);
    }

    // Test extractPaymentDataFromMeta with valid formatted string
    public function testExtractPaymentDataFromMetaWithValidString()
    {
        $metaString = '[Date 2024-01-15 10:30:00]/[Amount 150.75]/[Payment Type credit_card]/[Payment Method visa]/[Paid 150.75]/[Coupon 0]/[Refund 25.5]';

        $result = PaymentMetadata::extractPaymentDataFromMeta($metaString);

        $this->assertEquals('2024-01-15 10:30:00', $result->date);
        $this->assertEquals(150.75, $result->amount);
        $this->assertEquals('credit_card', $result->payment_type);
        $this->assertEquals('visa', $result->payment_method);
        $this->assertEquals(150.75, $result->paid);
        $this->assertEquals(0.0, $result->coupon);
        $this->assertEquals(25.5, $result->refund);
    }

    // Test extractPaymentDataFromMeta with empty string
    public function testExtractPaymentDataFromMetaWithEmptyString()
    {
        $metaString = '';
        $result = PaymentMetadata::extractPaymentDataFromMeta($metaString);

        $this->assertInstanceOf(\stdClass::class, $result);
        // Should return empty object when no data
        $properties = get_object_vars($result);
        $this->assertEmpty($properties);
    }

    // Test extractPaymentDataFromMeta with malformed string
    public function testExtractPaymentDataFromMetaWithMalformedString()
    {
        $metaString = 'Invalid/[Amount 100]/More Invalid/[Payment Type visa]';

        $result = PaymentMetadata::extractPaymentDataFromMeta($metaString);

        $this->assertEquals(100.0, $result->amount);
        $this->assertEquals('visa', $result->payment_type);
        $this->assertFalse(property_exists($result, 'date'));
    }

    // Test integration: format and then extract
    public function testFormatAndExtractIntegration()
    {
        $originalData = [
            'date' => '2024-01-15 10:30:00',
            'total_amount' => 150.75,
            'payment_type_id' => 'credit_card',
            'payment_method_id' => 'visa',
            'paid_amount' => 150.75,
            'coupon_amount' => 5.00
        ];
        $refundedAmount = 25.50;

        // Format the data
        $formatted = PaymentMetadata::formatPaymentMetadata($originalData, $refundedAmount);

        // Extract it back
        $extracted = PaymentMetadata::extractPaymentDataFromMeta($formatted);

        // Compare extracted data with original
        $this->assertEquals($originalData['date'], $extracted->date);
        $this->assertEquals($originalData['total_amount'], $extracted->amount);
        $this->assertEquals($originalData['payment_type_id'], $extracted->payment_type);
        $this->assertEquals($originalData['payment_method_id'], $extracted->payment_method);
        $this->assertEquals($originalData['paid_amount'], $extracted->paid);
        $this->assertEquals($originalData['coupon_amount'], $extracted->coupon);
        $this->assertEquals($refundedAmount, $extracted->refund);
    }

    // Test edge case: very long payment method name
    public function testParsePaymentFieldDataWithLongValues()
    {
        $fieldArray = [
            '[Payment Method very_long_payment_method_name_with_underscores]',
            '[Payment Type credit_card_with_additional_info]',
            '[Amount 1234567.89]'
        ];

        $result = PaymentMetadata::parsePaymentFieldData($fieldArray);

        $this->assertEquals('very_long_payment_method_name_with_underscores', $result->payment_method);
        $this->assertEquals('credit_card_with_additional_info', $result->payment_type);
        $this->assertEquals(1234567.89, $result->amount);
    }

    // Test edge case: negative amounts
    public function testParsePaymentFieldDataWithNegativeAmounts()
    {
        $fieldArray = [
            '[Amount -50.25]',
            '[Refund -10.00]',
            '[Coupon -5.00]'
        ];

        $result = PaymentMetadata::parsePaymentFieldData($fieldArray);

        $this->assertEquals(-50.25, $result->amount);
        $this->assertEquals(-10.00, $result->refund);
        $this->assertEquals(-5.00, $result->coupon);
    }

    // Test constants accessibility
    public function testPublicConstantsAccessibility()
    {
        $this->assertEquals('Mercado Pago - Payment ', PaymentMetadata::PAYMENT_META_PREFIX);
        $this->assertEquals('_Mercado_Pago_Payment_IDs', PaymentMetadata::PAYMENT_IDS_META_KEY);
        $this->assertEquals(' - payment_type', PaymentMetadata::PAYMENT_TYPE_META_SUFFIX);
        $this->assertEquals(' - installments', PaymentMetadata::INSTALLMENTS_META_SUFFIX);
        $this->assertEquals(' - installment_amount', PaymentMetadata::INSTALLMENT_AMOUNT_META_SUFFIX);
        $this->assertEquals(' - transaction_amount', PaymentMetadata::TRANSACTION_AMOUNT_META_SUFFIX);
        $this->assertEquals(' - total_paid_amount', PaymentMetadata::TOTAL_PAID_AMOUNT_META_SUFFIX);
        $this->assertEquals(' - card_last_four_digits', PaymentMetadata::CARD_LAST_FOUR_DIGITS_META_SUFFIX);
    }
}
