<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

use MercadoPago\Woocommerce\Helpers\Form;
use Mockery;

/**
 * Trait FormMock
 *
 * Provides helper methods for mocking the Form helper class in tests.
 * Note: Tests using this trait should add @runInSeparateProcess annotation
 * to avoid conflicts with alias mocks.
 *
 * @package MercadoPago\Woocommerce\Tests\Traits
 */
trait FormMock
{
    /**
     * Mock Form::sanitizedPostData() method
     *
     * @param mixed $returnValue The value to return from the mock
     * @param string|null $key Optional key parameter for sanitizedPostData
     * @return Mockery\MockInterface
     */
    protected function mockFormSanitizedPostData($returnValue = [], ?string $key = null)
    {
        $mock = Mockery::mock('alias:' . Form::class);

        $expectation = $mock->shouldReceive('sanitizedPostData');

        if ($key !== null) {
            $expectation->with($key);
        }

        return $expectation->andReturn($returnValue);
    }

    /**
     * Mock Form::sanitizedGetData() method
     *
     * @param mixed $returnValue The value to return from the mock
     * @return Mockery\MockInterface
     */
    protected function mockFormSanitizedGetData($returnValue = [])
    {
        return Mockery::mock('alias:' . Form::class)
            ->shouldReceive('sanitizedGetData')
            ->andReturn($returnValue);
    }

    /**
     * Mock Form with custom setup using a callback
     *
     * @param callable $setupCallback Callback that receives the mock instance
     * @return Mockery\MockInterface
     */
    protected function mockFormWithCustomSetup(callable $setupCallback)
    {
        $mock = Mockery::mock('alias:' . Form::class);
        $setupCallback($mock);
        return $mock;
    }

    /**
     * Mock Form::sanitizedPostData() to throw an exception
     *
     * @param string $exceptionClass The exception class to throw
     * @return Mockery\MockInterface
     */
    protected function mockFormSanitizedPostDataThrows(string $exceptionClass = \Exception::class)
    {
        return Mockery::mock('alias:' . Form::class)
            ->shouldReceive('sanitizedPostData')
            ->andThrow($exceptionClass);
    }
}
