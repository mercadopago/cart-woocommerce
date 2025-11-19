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
        $this->storeTranslationsMock->checkoutErrorMessagesV2['payment_generic_error'] = '<strong>Your payment was declined because something went wrong</strong><br>We recommend trying again or paying with another method.';
        $this->storeTranslationsMock->checkoutErrorMessagesV2['payment_not_completed'] = '<strong>It was not possible to complete the payment</strong><br>Please use another method to complete the purchase.';

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
}
