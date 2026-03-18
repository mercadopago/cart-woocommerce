<?php

namespace MercadoPago\Woocommerce\Tests\Exceptions;

use Exception;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use PHPUnit\Framework\TestCase;

class RejectedPaymentExceptionTest extends TestCase
{
    /**
     * Test that RejectedPaymentException extends the base Exception class
     */
    public function testExtendsException()
    {
        $exception = new RejectedPaymentException();

        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test constructor with default parameters
     */
    public function testConstructorWithDefaults()
    {
        $exception = new RejectedPaymentException();

        $this->assertEquals('Payment processing rejected', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getStatusDetail());
    }

    /**
     * Test constructor with custom message
     */
    public function testConstructorWithCustomMessage()
    {
        $customMessage = 'Credit card was rejected';
        $exception = new RejectedPaymentException($customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
        $this->assertNull($exception->getStatusDetail());
    }

    /**
     * Test constructor with all parameters
     */
    public function testConstructorWithAllParameters()
    {
        $message = 'Insufficient funds';
        $code = 400;
        $previousException = new Exception('Previous error');
        $statusDetail = 'cc_rejected_insufficient_amount';

        $exception = new RejectedPaymentException(
            $message,
            $code,
            $previousException,
            $statusDetail
        );

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertEquals($statusDetail, $exception->getStatusDetail());
    }

    /**
     * Test getStatusDetail returns the correct value
     *
     * @dataProvider statusDetailProvider
     */
    public function testGetStatusDetail(?string $statusDetail)
    {
        $exception = new RejectedPaymentException(
            'Payment rejected',
            0,
            null,
            $statusDetail
        );

        $this->assertEquals($statusDetail, $exception->getStatusDetail());
    }

    /**
     * Data provider for status details
     */
    public function statusDetailProvider(): array
    {
        return [
            'insufficient_amount' => ['cc_rejected_insufficient_amount'],
            'bad_filled_card_number' => ['cc_rejected_bad_filled_card_number'],
            'bad_filled_security_code' => ['cc_rejected_bad_filled_security_code'],
            'bad_filled_date' => ['cc_rejected_bad_filled_date'],
            'high_risk' => ['cc_rejected_high_risk'],
            'max_attempts' => ['cc_rejected_max_attempts'],
            'call_for_authorize' => ['cc_rejected_call_for_authorize'],
            'null_status_detail' => [null],
        ];
    }

    /**
     * Test exception can be caught as Exception
     */
    public function testCanBeCaughtAsException()
    {
        $caught = false;

        try {
            throw new RejectedPaymentException('Test error');
        } catch (Exception $e) {
            $caught = true;
            $this->assertInstanceOf(RejectedPaymentException::class, $e);
        }

        $this->assertTrue($caught);
    }

    /**
     * Test exception chaining with previous exception
     */
    public function testExceptionChaining()
    {
        $previousException = new Exception('API connection timeout');
        $exception = new RejectedPaymentException(
            'Payment rejected',
            0,
            $previousException,
            'cc_rejected_high_risk'
        );

        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertEquals('API connection timeout', $exception->getPrevious()->getMessage());
    }

    /**
     * Test constructor with only message and statusDetail
     */
    public function testConstructorWithMessageAndStatusDetail()
    {
        $exception = new RejectedPaymentException(
            'Card rejected',
            0,
            null,
            'cc_rejected_other_reason'
        );

        $this->assertEquals('Card rejected', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertEquals('cc_rejected_other_reason', $exception->getStatusDetail());
    }
}
