<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\ErrorMessages;
use MercadoPago\Woocommerce\Tests\Mocks\ArrayMock;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use Mockery;
use PHPUnit\Framework\TestCase;

class ErrorMessagesTest extends TestCase
{
    /**
     * @var ErrorMessages
     */
    private $errorMessages;

    /**
     * @var Mockery\MockInterface|StoreTranslations
     */
    private $storeTranslationsMock;

    public function setUp(): void
    {
        $this->storeTranslationsMock = Mockery::mock(StoreTranslations::class);

        // Setup default translations - using actual messages from StoreTranslations.php
        $this->storeTranslationsMock->buyerRefusedMessages = new ArrayMock(function () {
            return '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.';
        });
        $this->storeTranslationsMock->buyerRefusedMessages['buyer_default'] = '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.';
        $this->storeTranslationsMock->buyerRefusedMessages['buyer_cc_rejected_high_risk'] = '<strong>For safety reasons, your payment was declined</strong><br>We recommended paying with your usual payment method and device for online purchases.';
        $this->storeTranslationsMock->buyerRefusedMessages['buyer_cc_rejected_call_for_authorize'] = '<strong>Your bank needs you to authorize the payment</strong><br>Please call the telephone number on your card or pay with another method.';
        $this->storeTranslationsMock->buyerRefusedMessages['buyer_cc_rejected_insufficient_amount'] = '<strong>Your credit card has no available limit</strong><br>Please pay using another card or choose another payment method.';
        $this->storeTranslationsMock->buyerRefusedMessages['buyer_yape_default'] = '<strong>Yape declined your payment</strong><br>Your payment could not be processed. Please try again or choose another payment method.';
        $this->storeTranslationsMock->buyerRefusedMessages['buyer_yape_cc_rejected_max_attempts'] = '<strong>Yape declined your payment</strong><br>After three incorrect approval codes, the payment can\'t be done with Yape for your safety.';

        $this->storeTranslationsMock->commonMessages = new ArrayMock(function () {
            return 'A problem was occurred when processing your payment. Please, try again.';
        });
        $this->storeTranslationsMock->commonMessages['cho_form_error'] = '<strong>Your payment was declined because something went wrong</strong><br>Please make sure all the information was entered correctly.';

        $this->storeTranslationsMock->checkoutErrorMessages = new ArrayMock(function () {
            return '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.';
        });
        $this->storeTranslationsMock->checkoutErrorMessages['invalid_users'] = '<strong>Credentials don\'t match the environment</strong><br>Enter the correct test or production keys to complete the payment.';
        $this->storeTranslationsMock->checkoutErrorMessages['payer_email_invalid'] = '<strong>The e-mail isn\'t valid for payment</strong><br>Enter another e-mail to complete the payment.';
        $this->storeTranslationsMock->checkoutErrorMessages['card_details_incorrect'] = '<strong>One or more card details were entered incorrectly</strong><br>Please enter them again exactly as they appear on the card to complete the payment.';
        $this->storeTranslationsMock->checkoutErrorMessages['api_fail'] = '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.';
        $this->storeTranslationsMock->checkoutErrorMessages['invalid_transaction_amount'] = '<strong>The amount to be paid is outside the allowed limit for this payment method</strong><br>Enter an amount within the limits or use another payment method.';
        $this->storeTranslationsMock->checkoutErrorMessages['user_identification_invalid'] = '<strong>One or more of the buyer details were entered incorrectly</strong><br>Please check the identification number and try again.';

        $this->storeTranslationsMock->checkoutErrorMessagesV2 = new ArrayMock(function () {
            return '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.';
        });
        $this->storeTranslationsMock->checkoutErrorMessagesV2['communication_error_retry'] = '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['store_setup_error'] = '<strong>Your payment was declined by the store</strong><br>Try another payment method or contact the seller.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['payment_declined_device'] = '<strong>For safety reasons, your payment was declined</strong><br>We recommend paying with your usual payment method and device for online purchases.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['card_no_limit'] = '<strong>Your credit card has no available limit</strong><br>Choose another payment method.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['invalid_test_email'] = '<strong>The test e-mail you entered is not valid</strong><br>Enter a valid email to complete the payment.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['invalid_email'] = '<strong>The email you entered is not valid</strong><br>Enter a valid email to complete the payment.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['payment_generic_error'] = '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['payment_not_completed'] = '<strong>It was not possible to complete the payment</strong><br>Please use another method to complete the purchase.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['incorrect_card_details'] = '<strong>One or more card details were entered incorrectly</strong><br>Please enter them again exactly as they appear on the card to complete the payment.';

        $this->errorMessages = new ErrorMessages($this->storeTranslationsMock);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Data provider for error message V1 tests
     *
     * @return array[]
     */
    public function errorMessagesV1Provider(): array
    {
        return [
            'error_400' => [
                'input_message' => '400',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.'
            ],
            'error_exception' => [
                'input_message' => 'exception',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.'
            ],
            'cho_form_error' => [
                'input_message' => 'cho_form_error',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>Please make sure all the information was entered correctly.'
            ],
            'invalid_users_involved' => [
                'input_message' => 'Invalid users involved',
                'expected_message' => '<strong>Credentials don\'t match the environment</strong><br>Enter the correct test or production keys to complete the payment.'
            ],
            'invalid_operators_users' => [
                'input_message' => 'Invalid operators users involved',
                'expected_message' => '<strong>The e-mail isn\'t valid for payment</strong><br>Enter another e-mail to complete the payment.'
            ],
            'invalid_card_validation' => [
                'input_message' => 'Invalid card_number_validation',
                'expected_message' => '<strong>One or more card details were entered incorrectly</strong><br>Please enter them again exactly as they appear on the card to complete the payment.'
            ],
            'api_fail_gateway' => [
                'input_message' => 'POST to Gateway Transactions API fail',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.'
            ],
            'api_fail_token' => [
                'input_message' => 'Connection to Card Token API fail',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.'
            ],
            'card_data_required' => [
                'input_message' => 'The parameter cardholder.name cannot be null or empty',
                'expected_message' => '<strong>One or more card details were entered incorrectly</strong><br>Please enter them again exactly as they appear on the card to complete the payment.'
            ]
        ];
    }

    /**
     * Data provider for error message V2 tests
     *
     * @return array[]
     */
    public function errorMessagesV2Provider(): array
    {
        return [
            'invalid_test_user_email' => [
                'input_message' => 'Invalid test user email',
                'expected_message' => '<strong>The test e-mail you entered is not valid</strong><br>Enter a valid email to complete the payment.'
            ],
            'communication_error' => [
                'input_message' => 'It was not possible to complete the payment due to a communication error. Please try again later or use another payment method.',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.'
            ],
            'store_setup_error' => [
                'input_message' => 'Your payment was declined due to an error in the store setup. Please get in touch with the store support and try again later.',
                'expected_message' => '<strong>Your payment was declined by the store</strong><br>Try another payment method or contact the seller.'
            ],
            'payment_declined_device' => [
                'input_message' => 'Your payment was declined. We recommend that you use the device and payment method you usually use for online shopping.',
                'expected_message' => '<strong>For safety reasons, your payment was declined</strong><br>We recommend paying with your usual payment method and device for online purchases.'
            ],
            'card_no_limit' => [
                'input_message' => 'Your credit card has no available limit. We recommend choosing another payment method.',
                'expected_message' => '<strong>Your credit card has no available limit</strong><br>Choose another payment method.'
            ]
        ];
    }

    /**
     * Test findErrorMessage with V1 error messages
     *
     * @dataProvider errorMessagesV1Provider
     * @param string $input_message The input error message
     * @param string $expected_message The expected translated message
     */
    public function testFindErrorMessageV1(string $input_message, string $expected_message)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result);
    }

    /**
     * Test findErrorMessage with V2 error messages
     *
     * @dataProvider errorMessagesV2Provider
     * @param string $input_message The input error message
     * @param string $expected_message The expected translated message
     */
    public function testFindErrorMessageV2(string $input_message, string $expected_message)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result);
    }

    /**
     * Test findErrorMessage with partial match
     */
    public function testFindErrorMessageWithPartialMatch()
    {
        $result = $this->errorMessages->findErrorMessage('Some error with Invalid users involved in the middle');
        $this->assertEquals('<strong>Credentials don\'t match the environment</strong><br>Enter the correct test or production keys to complete the payment.', $result);
    }

    /**
     * Test findErrorMessage returns default message for unknown errors
     */
    public function testFindErrorMessageReturnsDefaultForUnknownError()
    {
        $result = $this->errorMessages->findErrorMessage('This is a completely unknown error message');
        $this->assertEquals('<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.', $result);
    }

    /**
     * Test getDefaultErrorMessage
     */
    public function testGetDefaultErrorMessage()
    {
        $result = $this->errorMessages->getDefaultErrorMessage();
        $this->assertEquals('<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.', $result);
    }

    /**
     * Test getErrorMessages returns merged array
     */
    public function testGetErrorMessagesReturnsMergedArray()
    {
        $result = $this->errorMessages->getErrorMessages();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Verify it contains V1 messages
        $this->assertArrayHasKey('Invalid users involved', $result);
        $this->assertArrayHasKey('cho_form_error', $result);

        // Verify it contains V2 messages
        $this->assertArrayHasKey('Your credit card has no available limit. We recommend choosing another payment method.', $result);
        $this->assertArrayHasKey('Invalid test user email', $result);
    }

    /**
     * Test that V1 and V2 messages are properly merged without duplicates
     */
    public function testV1AndV2MessagesAreMergedCorrectly()
    {
        $allMessages = $this->errorMessages->getErrorMessages();

        // Verify that V1 messages are present
        $this->assertArrayHasKey('Invalid users involved', $allMessages, 'V1 message should be present in merged array');
        $this->assertArrayHasKey('Invalid transaction_amount', $allMessages, 'V1 message should be present in merged array');
        $this->assertArrayHasKey('cho_form_error', $allMessages, 'V1 message should be present in merged array');

        // Verify that V2 messages are present
        $this->assertArrayHasKey('Invalid test user email', $allMessages, 'V2 message should be present in merged array');
        $this->assertArrayHasKey('Your credit card has no available limit. We recommend choosing another payment method.', $allMessages, 'V2 message should be present in merged array');

        // Verify that both V1 and V2 messages have correct translations
        $this->assertEquals(
            '<strong>Credentials don\'t match the environment</strong><br>Enter the correct test or production keys to complete the payment.',
            $allMessages['Invalid users involved'],
            'V1 message should have correct translation'
        );

        $this->assertEquals(
            '<strong>The test e-mail you entered is not valid</strong><br>Enter a valid email to complete the payment.',
            $allMessages['Invalid test user email'],
            'V2 message should have correct translation'
        );
    }

    /**
     * Test findErrorMessage with empty string
     */
    public function testFindErrorMessageWithEmptyString()
    {
        $result = $this->errorMessages->findErrorMessage('');
        $this->assertEquals('<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.', $result);
    }

    /**
     * Data provider for case insensitive tests
     *
     * @return array[]
     */
    public function caseInsensitiveProvider(): array
    {
        return [
            'lowercase' => [
                'input_message' => 'invalid users involved',
                'expected_message' => '<strong>Credentials don\'t match the environment</strong><br>Enter the correct test or production keys to complete the payment.',
                'description' => 'Should match regardless of case difference (lowercase)'
            ],
            'mixed_case' => [
                'input_message' => 'Invalid users involved',
                'expected_message' => '<strong>Credentials don\'t match the environment</strong><br>Enter the correct test or production keys to complete the payment.',
                'description' => 'Should match with correct case (mixed case)'
            ],
            'uppercase' => [
                'input_message' => 'INVALID USERS INVOLVED',
                'expected_message' => '<strong>Credentials don\'t match the environment</strong><br>Enter the correct test or production keys to complete the payment.',
                'description' => 'Should match with uppercase'
            ]
        ];
    }

    /**
     * Test findErrorMessage is case insensitive
     *
     * @dataProvider caseInsensitiveProvider
     * @param string $input_message The input error message
     * @param string $expected_message The expected translated message
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageIsCaseInsensitive(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }

    /**
     * Data provider for ambiguous partial match tests
     *
     * @return array[]
     */
    public function ambiguousPartialMatchProvider(): array
    {
        return [
            'partial_invalid_users_involved' => [
                'input_message' => 'Invalid users involved',
                'expected_message' => '<strong>Credentials don\'t match the environment</strong><br>Enter the correct test or production keys to complete the payment.',
                'description' => 'Full match "Invalid users involved" should return correct message'
            ],
            'partial_invalid_operators' => [
                'input_message' => 'Invalid operators users involved',
                'expected_message' => '<strong>The e-mail isn\'t valid for payment</strong><br>Enter another e-mail to complete the payment.',
                'description' => 'Full match "Invalid operators users involved" should return correct message'
            ]
        ];
    }

    /**
     * Test findErrorMessage with messages that could be ambiguous
     * Tests that exact or near-exact matches work correctly
     *
     * @dataProvider ambiguousPartialMatchProvider
     * @param string $input_message The input error message
     * @param string $expected_message The expected translated message
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageWithSimilarMessages(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }

    /**
     * Data provider for buyer refused keys tests (exact key matching)
     *
     * @return array[]
     */
    public function buyerRefusedKeysProvider(): array
    {
        return [
            'buyer_default_key' => [
                'input_message' => 'buyer_default',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should match exact buyer_default key'
            ],
            'buyer_cc_rejected_high_risk_key' => [
                'input_message' => 'buyer_cc_rejected_high_risk',
                'expected_message' => '<strong>For safety reasons, your payment was declined</strong><br>We recommended paying with your usual payment method and device for online purchases.',
                'description' => 'Should match exact buyer_cc_rejected_high_risk key'
            ],
            'buyer_cc_rejected_call_for_authorize_key' => [
                'input_message' => 'buyer_cc_rejected_call_for_authorize',
                'expected_message' => '<strong>Your bank needs you to authorize the payment</strong><br>Please call the telephone number on your card or pay with another method.',
                'description' => 'Should match exact buyer_cc_rejected_call_for_authorize key'
            ],
            'buyer_yape_default_key' => [
                'input_message' => 'buyer_yape_default',
                'expected_message' => '<strong>Yape declined your payment</strong><br>Your payment could not be processed. Please try again or choose another payment method.',
                'description' => 'Should match exact buyer_yape_default key'
            ],
            'buyer_yape_cc_rejected_max_attempts_key' => [
                'input_message' => 'buyer_yape_cc_rejected_max_attempts',
                'expected_message' => '<strong>Yape declined your payment</strong><br>After three incorrect approval codes, the payment can\'t be done with Yape for your safety.',
                'description' => 'Should match exact buyer_yape_cc_rejected_max_attempts key'
            ]
        ];
    }

    /**
     * Test findErrorMessage with buyer refused keys (exact key matching)
     *
     * @dataProvider buyerRefusedKeysProvider
     * @param string $input_message The input error message key
     * @param string $expected_message The expected translated message
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageWithBuyerRefusedKeys(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }

    /**
     * Data provider for checkout error messages V2 keys tests
     *
     * @return array[]
     */
    public function checkoutErrorMessagesV2KeysProvider(): array
    {
        return [
            'invalid_email_key' => [
                'input_message' => 'invalid_email',
                'expected_message' => '<strong>The email you entered is not valid</strong><br>Enter a valid email to complete the payment.',
                'description' => 'Should match exact invalid_email key'
            ],
            'invalid_test_email_key' => [
                'input_message' => 'invalid_test_email',
                'expected_message' => '<strong>The test e-mail you entered is not valid</strong><br>Enter a valid email to complete the payment.',
                'description' => 'Should match exact invalid_test_email key'
            ]
        ];
    }

    /**
     * Test findErrorMessage with checkout error messages V2 keys
     *
     * @dataProvider checkoutErrorMessagesV2KeysProvider
     * @param string $input_message The input error message key
     * @param string $expected_message The expected translated message
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageWithCheckoutErrorMessagesV2Keys(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }

    /**
     * Data provider for keys with trailing punctuation tests
     *
     * @return array[]
     */
    public function keysWithTrailingPunctuationProvider(): array
    {
        return [
            'buyer_default_with_period' => [
                'input_message' => 'buyer_default.',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should match key even with trailing period'
            ],
            'buyer_default_with_exclamation' => [
                'input_message' => 'buyer_default!',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should match key even with trailing exclamation mark'
            ],
            'buyer_default_with_question' => [
                'input_message' => 'buyer_default?',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should match key even with trailing question mark'
            ],
            'cho_form_error_with_period' => [
                'input_message' => 'cho_form_error.',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>Please make sure all the information was entered correctly.',
                'description' => 'Should match cho_form_error key even with trailing period'
            ],
            'invalid_email_with_period' => [
                'input_message' => 'invalid_email.',
                'expected_message' => '<strong>The email you entered is not valid</strong><br>Enter a valid email to complete the payment.',
                'description' => 'Should match invalid_email key even with trailing period'
            ]
        ];
    }

    /**
     * Test findErrorMessage with keys that have trailing punctuation
     *
     * @dataProvider keysWithTrailingPunctuationProvider
     * @param string $input_message The input error message key with punctuation
     * @param string $expected_message The expected translated message
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageWithTrailingPunctuation(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }

    /**
     * Data provider for messages with escape characters tests
     *
     * @return array[]
     */
    public function messagesWithEscapeCharactersProvider(): array
    {
        return [
            'message_with_escaped_apostrophe' => [
                'input_message' => 'The payment method selected isn\'t available at the store.',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should handle escaped apostrophe in message (partial match not found, returns default)'
            ],
            'v1_message_with_escaped_apostrophe' => [
                'input_message' => 'Installments attribute can\'t be null',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.',
                'description' => 'Should match V1 message (installments_required) with escaped apostrophe after normalization'
            ]
        ];
    }

    /**
     * Test findErrorMessage with messages containing escape characters
     *
     * @dataProvider messagesWithEscapeCharactersProvider
     * @param string $input_message The input error message with escape characters
     * @param string $expected_message The expected translated message
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageWithEscapeCharacters(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }

    /**
     * Data provider for idempotency tests (already translated messages)
     *
     * @return array[]
     */
    public function alreadyTranslatedMessagesProvider(): array
    {
        return [
            'message_with_strong_tag' => [
                'input_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should return already translated message as-is (idempotency with <strong>)'
            ],
            'message_with_br_tag' => [
                'input_message' => '<strong>For safety reasons, your payment was declined</strong><br>We recommended paying with your usual payment method and device for online purchases.',
                'expected_message' => '<strong>For safety reasons, your payment was declined</strong><br>We recommended paying with your usual payment method and device for online purchases.',
                'description' => 'Should return already translated message as-is (idempotency with <br>)'
            ],
            'message_with_b_tag' => [
                'input_message' => '<b>For safety reasons, your payment was declined</b><br>We recommend paying with another method.',
                'expected_message' => '<b>For safety reasons, your payment was declined</b><br>We recommend paying with another method.',
                'description' => 'Should return already translated message as-is (idempotency with <b>)'
            ],
            'message_with_self_closing_br' => [
                'input_message' => '<strong>Payment error</strong><br/>Please try again.',
                'expected_message' => '<strong>Payment error</strong><br/>Please try again.',
                'description' => 'Should return already translated message as-is (idempotency with <br/>)'
            ]
        ];
    }

    /**
     * Test findErrorMessage returns already translated messages as-is (idempotency)
     *
     * @dataProvider alreadyTranslatedMessagesProvider
     * @param string $input_message The input already translated message
     * @param string $expected_message The expected message (same as input)
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageIdempotency(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }

    /**
     * Data provider for keys with whitespace tests
     *
     * @return array[]
     */
    public function keysWithWhitespaceProvider(): array
    {
        return [
            'key_with_leading_space' => [
                'input_message' => ' buyer_default',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should match key with leading whitespace'
            ],
            'key_with_trailing_space' => [
                'input_message' => 'buyer_default ',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should match key with trailing whitespace'
            ],
            'key_with_both_spaces' => [
                'input_message' => '  buyer_default  ',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
                'description' => 'Should match key with leading and trailing whitespace'
            ]
        ];
    }

    /**
     * Test findErrorMessage with keys that have whitespace
     *
     * @dataProvider keysWithWhitespaceProvider
     * @param string $input_message The input error message key with whitespace
     * @param string $expected_message The expected translated message
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageWithWhitespace(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }

    /**
     * Test findErrorMessage preserves periods in middle of keys
     */
    public function testFindErrorMessagePreservesMiddlePeriods()
    {
        // This test verifies that periods in the middle of a string are preserved
        // Only trailing periods should be removed
        $result = $this->errorMessages->findErrorMessage('buyer.something.else');
        // Should return default since 'buyer.something.else' is not a valid key
        $this->assertEquals(
            '<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.',
            $result,
            'Periods in middle should be preserved, key not found returns default'
        );
    }

    /**
     * Data provider for input without punctuation but keyword with punctuation tests
     *
     * @return array[]
     */
    public function inputWithoutPunctuationKeywordWithPunctuationProvider(): array
    {
        return [
            'v2_message_without_trailing_period' => [
                'input_message' => 'Your credit card has no available limit. We recommend choosing another payment method',
                'expected_message' => '<strong>Your credit card has no available limit</strong><br>Choose another payment method.',
                'description' => 'Should match V2 keyword even when input is missing trailing period'
            ],
            'v2_communication_error_without_period' => [
                'input_message' => 'It was not possible to complete the payment due to a communication error. Please try again later',
                'expected_message' => '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.',
                'description' => 'Should match V2 keyword even when input is missing trailing period'
            ]
        ];
    }

    /**
     * Test findErrorMessage matches keywords when input is missing trailing punctuation
     *
     * @dataProvider inputWithoutPunctuationKeywordWithPunctuationProvider
     * @param string $input_message The input message without trailing punctuation
     * @param string $expected_message The expected translated message
     * @param string $description Description of the test case
     */
    public function testFindErrorMessageMatchesKeywordWithPunctuationDifference(string $input_message, string $expected_message, string $description)
    {
        $result = $this->errorMessages->findErrorMessage($input_message);
        $this->assertEquals($expected_message, $result, $description);
    }
}
