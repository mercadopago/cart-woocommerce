<?php

namespace MercadoPago\Woocommerce\Tests\Exceptions;

use Exception;
use MercadoPago\Woocommerce\Exceptions\RefundException;
use PHPUnit\Framework\TestCase;

class RefundExceptionTest extends TestCase
{
    /**
     * Test that RefundException extends the base Exception class
     */
    public function testExtendsException()
    {
        $exception = new RefundException();

        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test constructor with default parameters
     */
    public function testConstructorWithDefaults()
    {
        $exception = new RefundException();

        $this->assertEquals('Refund processing failed', $exception->getMessage());
        $this->assertEquals(RefundException::TYPE_UNKNOWN, $exception->getLoggingContext()['error_type']);
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getHttpStatusCode());
        $this->assertEmpty($exception->getResponseData());
    }

    /**
     * Test constructor with custom message
     */
    public function testConstructorWithCustomMessage()
    {
        $customMessage = 'Payment not found for refund';
        $exception = new RefundException($customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    /**
     * Test constructor with all parameters
     */
    public function testConstructorWithAllParameters()
    {
        $message = 'Refund validation failed';
        $errorType = RefundException::TYPE_VALIDATION;
        $code = 400;
        $previousException = new Exception('Previous error');
        $paymentId = 'MP-12345678';
        $orderId = 999;
        $httpStatusCode = 400;
        $context = [
            'response_data' => ['error' => 'invalid_amount'],
            'user_id' => 123
        ];

        $exception = new RefundException(
            $message,
            $errorType,
            $code,
            $previousException,
            $paymentId,
            $orderId,
            $httpStatusCode,
            $context
        );

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertEquals($httpStatusCode, $exception->getHttpStatusCode());
        $this->assertEquals(['error' => 'invalid_amount'], $exception->getResponseData());

        $loggingContext = $exception->getLoggingContext();
        $this->assertEquals($errorType, $loggingContext['error_type']);
        $this->assertEquals($paymentId, $loggingContext['payment_id']);
        $this->assertEquals($orderId, $loggingContext['order_id']);
        $this->assertEquals($httpStatusCode, $loggingContext['http_status_code']);
        $this->assertEquals($message, $loggingContext['error_message']);
        $this->assertEquals($context, $loggingContext['context']);
    }

    /**
     * Test getHttpStatusCode method
     *
     * @dataProvider httpStatusCodeProvider
     */
    public function testGetHttpStatusCode($statusCode)
    {
        $exception = new RefundException(
            'Test message',
            RefundException::TYPE_UNKNOWN,
            0,
            null,
            null,
            null,
            $statusCode
        );

        $this->assertEquals($statusCode, $exception->getHttpStatusCode());
    }

    /**
     * Data provider for HTTP status codes
     */
    public function httpStatusCodeProvider(): array
    {
        return [
            'status_400' => [400],
            'status_401' => [401],
            'status_404' => [404],
            'status_500' => [500],
            'status_503' => [503],
            'null_status' => [null],
        ];
    }

    /**
     * Test getLoggingContext method returns all expected fields
     */
    public function testGetLoggingContextStructure()
    {
        $exception = new RefundException(
            'Test error',
            RefundException::TYPE_SERVER_ERROR,
            0,
            null,
            'MP-99999',
            777,
            500,
            ['extra' => 'data']
        );

        $context = $exception->getLoggingContext();

        $this->assertArrayHasKey('error_type', $context);
        $this->assertArrayHasKey('payment_id', $context);
        $this->assertArrayHasKey('order_id', $context);
        $this->assertArrayHasKey('http_status_code', $context);
        $this->assertArrayHasKey('error_message', $context);
        $this->assertArrayHasKey('context', $context);

        $this->assertEquals(RefundException::TYPE_SERVER_ERROR, $context['error_type']);
        $this->assertEquals('MP-99999', $context['payment_id']);
        $this->assertEquals(777, $context['order_id']);
        $this->assertEquals(500, $context['http_status_code']);
        $this->assertEquals('Test error', $context['error_message']);
        $this->assertEquals(['extra' => 'data'], $context['context']);
    }

    /**
     * Test getResponseData method with response data in context
     */
    public function testGetResponseDataWithData()
    {
        $responseData = [
            'status' => 'rejected',
            'status_detail' => 'cc_rejected_insufficient_amount',
            'error_code' => 'E301'
        ];

        $exception = new RefundException(
            'Refund rejected',
            RefundException::TYPE_VALIDATION,
            0,
            null,
            null,
            null,
            null,
            ['response_data' => $responseData]
        );

        $this->assertEquals($responseData, $exception->getResponseData());
    }

    /**
     * Test getResponseData method without response data
     */
    public function testGetResponseDataWithoutData()
    {
        $exception = new RefundException();

        $this->assertEmpty($exception->getResponseData());
        $this->assertIsArray($exception->getResponseData());
    }

    /**
     * Test getResponseData with empty response_data key
     */
    public function testGetResponseDataWithEmptyData()
    {
        $exception = new RefundException(
            'Test',
            RefundException::TYPE_UNKNOWN,
            0,
            null,
            null,
            null,
            null,
            ['response_data' => []]
        );

        $this->assertEmpty($exception->getResponseData());
        $this->assertIsArray($exception->getResponseData());
    }

    /**
     * Test all error type constants are accessible
     *
     * @dataProvider errorTypeProvider
     */
    public function testErrorTypeConstants(string $errorType)
    {
        $exception = new RefundException(
            'Test message',
            $errorType
        );

        $context = $exception->getLoggingContext();
        $this->assertEquals($errorType, $context['error_type']);
    }

    /**
     * Data provider for error types
     */
    public function errorTypeProvider(): array
    {
        return [
            'validation_error' => [RefundException::TYPE_VALIDATION],
            'unauthorized_error' => [RefundException::TYPE_UNAUTHORIZED],
            'not_found_error' => [RefundException::TYPE_NOT_FOUND],
            'server_error' => [RefundException::TYPE_SERVER_ERROR],
            'unknown_error' => [RefundException::TYPE_UNKNOWN],
            'no_permission_error' => [RefundException::TYPE_NO_PERMISSION],
            'supertoken_not_supported' => [RefundException::TYPE_SUPERTOKEN_NOT_SUPPORTED],
        ];
    }

    /**
     * Test exception can be caught as Exception
     */
    public function testCanBeCaughtAsException()
    {
        $caught = false;

        try {
            throw new RefundException('Test error');
        } catch (Exception $e) {
            $caught = true;
            $this->assertInstanceOf(RefundException::class, $e);
        }

        $this->assertTrue($caught);
    }

    /**
     * Test exception with payment ID scenario
     */
    public function testWithPaymentIdScenario()
    {
        $paymentId = 'MP-123456789';
        $exception = new RefundException(
            'Payment not found',
            RefundException::TYPE_NOT_FOUND,
            0,
            null,
            $paymentId
        );

        $context = $exception->getLoggingContext();
        $this->assertEquals($paymentId, $context['payment_id']);
        $this->assertEquals(RefundException::TYPE_NOT_FOUND, $context['error_type']);
    }

    /**
     * Test exception with order ID scenario
     */
    public function testWithOrderIdScenario()
    {
        $orderId = 12345;
        $exception = new RefundException(
            'Order not found',
            RefundException::TYPE_NOT_FOUND,
            0,
            null,
            null,
            $orderId
        );

        $context = $exception->getLoggingContext();
        $this->assertEquals($orderId, $context['order_id']);
    }

    /**
     * Test unauthorized scenario
     */
    public function testUnauthorizedScenario()
    {
        $exception = new RefundException(
            'Invalid credentials',
            RefundException::TYPE_UNAUTHORIZED,
            401,
            null,
            null,
            null,
            401
        );

        $this->assertEquals(401, $exception->getCode());
        $this->assertEquals(401, $exception->getHttpStatusCode());

        $context = $exception->getLoggingContext();
        $this->assertEquals(RefundException::TYPE_UNAUTHORIZED, $context['error_type']);
    }

    /**
     * Test server error scenario with full context
     */
    public function testServerErrorScenario()
    {
        $context = [
            'response_data' => [
                'message' => 'Internal server error',
                'cause' => 'database_timeout'
            ],
            'attempt' => 3,
            'timestamp' => '2024-11-24T10:30:00Z'
        ];

        $exception = new RefundException(
            'Server error during refund processing',
            RefundException::TYPE_SERVER_ERROR,
            500,
            null,
            'MP-987654321',
            555,
            500,
            $context
        );

        $loggingContext = $exception->getLoggingContext();
        $this->assertEquals(500, $loggingContext['http_status_code']);
        $this->assertEquals(RefundException::TYPE_SERVER_ERROR, $loggingContext['error_type']);
        $this->assertEquals($context, $loggingContext['context']);

        $responseData = $exception->getResponseData();
        $this->assertEquals('Internal server error', $responseData['message']);
        $this->assertEquals('database_timeout', $responseData['cause']);
    }

    /**
     * Test supertoken not supported scenario
     */
    public function testSupertokenNotSupportedScenario()
    {
        $exception = new RefundException(
            'Refunds not supported for supertoken payments',
            RefundException::TYPE_SUPERTOKEN_NOT_SUPPORTED
        );

        $context = $exception->getLoggingContext();
        $this->assertEquals(RefundException::TYPE_SUPERTOKEN_NOT_SUPPORTED, $context['error_type']);
        $this->assertEquals('Refunds not supported for supertoken payments', $context['error_message']);
    }

    /**
     * Test no permission scenario
     */
    public function testNoPermissionScenario()
    {
        $exception = new RefundException(
            'User does not have permission to process refunds',
            RefundException::TYPE_NO_PERMISSION,
            403,
            null,
            null,
            888,
            403
        );

        $context = $exception->getLoggingContext();
        $this->assertEquals(RefundException::TYPE_NO_PERMISSION, $context['error_type']);
        $this->assertEquals(403, $context['http_status_code']);
        $this->assertEquals(888, $context['order_id']);
    }

    /**
     * Test exception chaining with previous exception
     */
    public function testExceptionChaining()
    {
        $previousException = new Exception('Connection timeout');
        $exception = new RefundException(
            'Failed to process refund',
            RefundException::TYPE_SERVER_ERROR,
            0,
            $previousException
        );

        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertEquals('Connection timeout', $exception->getPrevious()->getMessage());
    }

    /**
     * Test complex context data structure
     */
    public function testComplexContextData()
    {
        $complexContext = [
            'response_data' => [
                'id' => 12345,
                'status' => 'rejected',
                'details' => [
                    'code' => 'insufficient_funds',
                    'message' => 'The refund amount exceeds available balance'
                ]
            ],
            'request_metadata' => [
                'user_agent' => 'WooCommerce/7.0',
                'ip_address' => '192.168.1.1'
            ],
            'retry_count' => 2,
            'timestamp' => 1700828400
        ];

        $exception = new RefundException(
            'Complex refund error',
            RefundException::TYPE_VALIDATION,
            400,
            null,
            'MP-COMPLEX-123',
            999,
            400,
            $complexContext
        );

        $context = $exception->getLoggingContext();
        $this->assertEquals($complexContext, $context['context']);

        $responseData = $exception->getResponseData();
        $this->assertEquals(12345, $responseData['id']);
        $this->assertEquals('rejected', $responseData['status']);
        $this->assertIsArray($responseData['details']);
        $this->assertEquals('insufficient_funds', $responseData['details']['code']);
    }
}
