<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\RefundValidationError;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use PHPUnit\Framework\TestCase;

class RefundValidationErrorTest extends TestCase
{
    private RefundValidationError $errors;

    protected function setUp(): void
    {
        // Translation strings are input data; no production method/map is mocked or read.
        $translations = (new \ReflectionClass(AdminTranslations::class))->newInstanceWithoutConstructor();
        $translations->refund = [
            'amount_must_be_positive' => 'Refund amount must be positive.',
            'payment_too_old' => 'Payment is too old to refund.',
            'invalid_payment_status' => 'Payment status cannot be refunded.',
            'payment_not_found' => 'Payment was not found.',
            'invalid_refund_amount' => 'Refund amount is invalid.',
            'insufficient_funds' => 'Insufficient funds for this refund.',
            'unknown_error' => 'Refund could not be completed.',
        ];
        $this->errors = new RefundValidationError($translations);
    }

    /** @dataProvider apiCodeProvider */
    public function testKnownCauseCodesUseIndependentTranslatedMessages($code, string $expected): void
    {
        $response = ['cause' => [['code' => $code, 'description' => 'Untranslated upstream description']]];

        $this->assertSame($expected, $this->errors->processCauseMessage($response));
    }

    /** @dataProvider apiCodeProvider */
    public function testOriginalMessageMapsLiteralApiCodes($code, string $expected): void
    {
        $this->assertSame($expected, $this->errors->processOriginalMessage([
            'original_message' => 'Provider rejected refund with code ' . $code . '.',
        ]));
    }

    public static function apiCodeProvider(): array
    {
        // Literal API codes and independent expected text catch wrong SUT constants/maps.
        return [
            'positive amount' => [4040, 'Refund amount must be positive.'],
            'old payment numeric string' => ['2024', 'Payment is too old to refund.'],
            'invalid status' => [2063, 'Payment status cannot be refunded.'],
            'missing payment' => [404, 'Payment was not found.'],
            'invalid amount' => [2017, 'Refund amount is invalid.'],
            'insufficient funds' => [2031, 'Insufficient funds for this refund.'],
        ];
    }

    public function testOnlyFirstCauseDeterminesMessageEvenWhenLaterCauseIsKnown(): void
    {
        $this->assertSame('First upstream reason.', $this->errors->processCauseMessage([
            'cause' => [
                ['code' => 9999, 'description' => 'First upstream reason.'],
                ['code' => 4040, 'description' => 'Later reason.'],
            ],
        ]));
    }

    public function testKnownFirstCauseWinsOverLaterCauseAndMissingDescription(): void
    {
        $this->assertSame('Payment was not found.', $this->errors->processCauseMessage([
            'cause' => [['code' => 404], ['code' => 2031, 'description' => 'Later reason.']],
        ]));
    }

    /** @dataProvider malformedCauseProvider */
    public function testAbsentOrMalformedCauseHasNoUserMessage(array $response): void
    {
        $this->assertNull($this->errors->processCauseMessage($response));
    }

    public static function malformedCauseProvider(): array
    {
        return [
            'absent' => [[]],
            'null' => [['cause' => null]],
            'string' => [['cause' => 'upstream error']],
            'empty list' => [['cause' => []]],
            'empty first cause' => [['cause' => [[]]]],
            'null first cause' => [['cause' => [null]]],
            'first cause has no code' => [['cause' => [['description' => 'No code.']]]],
        ];
    }

    /** @dataProvider unknownCauseProvider */
    public function testUnknownCauseUsesDescriptionOrConfiguredFallback(array $cause, string $expected): void
    {
        $this->assertSame($expected, $this->errors->processCauseMessage(['cause' => [$cause]]));
    }

    public static function unknownCauseProvider(): array
    {
        return [
            'description' => [['code' => 9999, 'description' => 'Provider-specific reason.'], 'Provider-specific reason.'],
            'missing description' => [['code' => 9999], 'Refund could not be completed.'],
            'empty description' => [['code' => 9999, 'description' => ''], 'Refund could not be completed.'],
        ];
    }

    /** @dataProvider unmatchedOriginalProvider */
    public function testUnmatchedOrInvalidOriginalMessageReturnsNull(array $response): void
    {
        $this->assertNull($this->errors->processOriginalMessage($response));
    }

    public static function unmatchedOriginalProvider(): array
    {
        return [
            'absent' => [[]],
            'empty' => [['original_message' => '']],
            'null' => [['original_message' => null]],
            'array' => [['original_message' => ['4040']]],
            'integer' => [['original_message' => 4040]],
            'unknown code' => [['original_message' => 'Provider code 9999.']],
            'no code' => [['original_message' => 'No matching provider error.']],
        ];
    }
}
