<?php

namespace MercadoPago\PP\Sdk\HttpClient\Requester;

use MercadoPago\PP\Sdk\Common\AbstractCollection;
use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\HttpClient\Response;

/**
 * Class CurlRequester
 *
 * @package MercadoPago\PP\Sdk\HttpClient\Requester
 */
class CurlRequester implements RequesterInterface
{
    /**
     * CurlRequester constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('cURL extension not found.' .
                'You need to enable cURL in your php.ini or another configuration you have.');
        }
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $headers
     * @param string|AbstractEntity|AbstractCollection|array|null $body
     *
     * @return resource
     * @throws \Exception
     */
    public function createRequest(string $method, string $uri, array $headers = [], $body = null)
    {
        $json_content         = true;
        $form_content         = false;
        $default_content_type = true;

        if (isset($headers) && is_array($headers)) {
            foreach ($headers as $h => $v) {
                if ('content-type' === $h) {
                    $default_content_type = false;
                    $json_content         = 'application/json' === $v;
                    $form_content         = 'application/x-www-form-urlencoded' === $v;
                    break;
                }
            }
        }
        if ($default_content_type) {
            $headers[] = 'content-type: application/json';
        }

        $connect = $this->curlInit();
        $this->setOption($connect, CURLOPT_USERAGENT, 'platform:v1-whitelabel,type:mp_sdk');
        $this->setOption($connect, CURLOPT_RETURNTRANSFER, true);

        // @TODO define CAINFO when implementing SDK
        // $this->setOption($connect, CURLOPT_SSL_VERIFYPEER, true);
        // $this->setOption( $connect, CURLOPT_CAINFO, $GLOBALS['LIB_LOCATION'] . '/cacert.pem' );

        $this->setOption($connect, CURLOPT_CUSTOMREQUEST, $method);
        $this->setOption($connect, CURLOPT_HTTPHEADER, $headers);
        $this->setOption($connect, CURLOPT_URL, $uri);

        if (isset($body)) {
            if ($json_content) {
                if (is_string($body)) {
                    json_decode($body, true);
                } else {
                    $body = json_encode($body);
                }
                if (function_exists('json_last_error')) {
                    $json_error = json_last_error();
                    if (JSON_ERROR_NONE !== $json_error) {
                        throw new \Exception("JSON Error [{$json_error}] - Data: " . $body);
                    }
                }
            } elseif ($form_content) {
                $body = self::buildFormData($body);
            }
            $this->setOption($connect, CURLOPT_POSTFIELDS, $body);
        }

        return $connect;
    }

    /**
     * @param resource $request
     *
     * @throws \Exception
     */
    public function sendRequest($request): Response
    {
        $response = new Response();
        $api_result = $this->curlExec($request);

        if ($this->curlErrno($request)) {
            throw new \Exception($this->curlError($request));
        }

        $info          = $this->curlGetInfo($request);
        $api_http_code = $info['http_code'];

        // @TODO: call logging service when ready

        if (null !== $api_http_code && null !== $api_result) {
            $response->setStatus($api_http_code);
            $response->setData(json_decode($api_result, true));
        }

        $this->curlClose($request);

        return $response;
    }

    /**
     * Build query
     *
     * @param array|object $params Params.
     *
     * @return string
     */
    public static function buildFormData(array $params): string
    {
        if (function_exists('http_build_query')) {
            return http_build_query($params, '', '&');
        } else {
            $elements = [];

            foreach ($params as $name => $value) {
                $elements[] = "{$name}=" . rawurldecode($value);
            }

            return implode('&', $elements);
        }
    }

    /**
     * @codeCoverageIgnore
     *
     * @return resource
     */
    protected function curlInit()
    {
        return curl_init();
    }

    /**
     * @codeCoverageIgnore
     */
    protected function curlClose($request)
    {
        curl_close($request);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function setOption($connect, $option, $value)
    {
        curl_setopt($connect, $option, $value);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function curlExec($request)
    {
        return curl_exec($request);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function curlErrno($request): int
    {
        return curl_errno($request);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function curlError($request): string
    {
        return curl_error($request);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function curlGetInfo($request)
    {
        return curl_getinfo($request);
    }
}
