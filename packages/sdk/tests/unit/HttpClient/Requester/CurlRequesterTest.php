<?php

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\HttpClient\Requester\CurlRequester;
use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\CurlRequestMock;

/**
 * Class CurlRequesterTest
 *
 * @package MercadoPago\Tests\HttpClient\Requester
 */
class CurlRequesterTest extends TestCase
{

    function getPartialCurlRequesterMock($responseCode, $errno, $error)
    {
        $apiResponse = array(
            'http_code' => $responseCode
        );

        $mock = $this->getMockBuilder(CurlRequester::class)
            ->onlyMethods(['curlInit', 'curlClose', 'setOption', 'curlExec', 'curlErrno', 'curlError', 'curlGetInfo'])
            ->getMock();

        $mock->expects(self::any())->method('curlInit')->willReturn(array());

        $mock->expects(self::any())->method('curlGetInfo')->willReturn($apiResponse);

        $mock->expects(self::any())->method('curlExec')->willReturn(CurlRequestMock::CURL_EXEC_RESPONSE);

        if ($errno) {
            $mock->expects(self::any())->method('curlErrno')->willReturn($errno);
            $mock->expects(self::any())->method('curlError')->willReturn($error);
        }

        return $mock;
    }

    /**
     * A single example test.
     */
    function testCreateRequestSuccess()
    {
        $requester = $this->getPartialCurlRequesterMock(200, 0, '');

        // act
        $response = $requester->createRequest('method', 'uri', [], null);

        // assert
        $this->assertEquals(array(), $response);
    }

    /**
     * A single example test.
     */
    function testCreateRequestWithJsonArrayBodySuccess()
    {
        // arrange
        $requester = $this->getPartialCurlRequesterMock(200, 0, '');

        // act
        $response = $requester->createRequest('method', 'uri', [], array('test' => 123));

        // assert
        $this->assertEquals(array(), $response);
    }

    /**
     * A single example test.
     */
    function testCreateRequestWithJsonStringBodySuccess()
    {
        // arrange
        $requester = $this->getPartialCurlRequesterMock(200, 0, '');

        // act
        $response = $requester->createRequest('method', 'uri', [], '{"test":"123"}');

        // assert
        $this->assertEquals(array(), $response);
    }

    /**
     * A single example test.
     */
    function testCreateRequestWithFormDataBodySuccess()
    {
        // arrange
        $requester = $this->getPartialCurlRequesterMock(200, 0, '');
        $headers = array(
            'content-type' => 'application/x-www-form-urlencoded'
        );

        // act
        $response = $requester->createRequest('method', 'uri', $headers, array('test' => 123));

        // assert
        $this->assertEquals(array(), $response);
    }

    /**
     * A single example test.
     */
    function testSendRequestSuccess()
    {
        $mockResponse = new Response();
        $mockResponse->setStatus(200);
        $mockResponse->setData(json_decode('{"test":"123"}', true));
        $mockResponse->setHeaders([]);

        $requester = $this->getPartialCurlRequesterMock(200, 0, '');

        // act
        $response = $requester->sendRequest('curl_request');

        // assert
        $this->assertEquals($mockResponse, $response);
    }

    /**
     * A single example test.
     */
    function testSendRequestError()
    {
        $mockResponse = new Response();
        $mockResponse->setStatus(200);
        $mockResponse->setData(new stdClass());

        $requester = $this->getPartialCurlRequesterMock(200, 1, 'some_error');

        // assert + act
        $this->expectException(Exception::class);
        $requester->sendRequest('');
    }
}
