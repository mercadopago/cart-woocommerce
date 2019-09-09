<?php

/**
 * Class AbstractRestClient
 */
class AbstractRestClient
{
    const PRODUCT_ID = 'BC32CPFTRPP001U8NHRG';

    public static $email_admin = '';
    public static $site_locale = '';
    public static $check_loop = 0;

    /**
     * @param $request
     * @param $version
     * @param $baseUrl
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function execAbs($request, $version, $baseUrl)
    {
        $connect = self::build_request($request, $version, $baseUrl);
        return self::execute($request, $version, $connect);
    }

    /**
     * @param $request
     * @param $version
     * @param $apiBaseUrl
     * @return false|resource
     * @throws WC_WooMercadoPago_Exception
     */
    public static function build_request($request, $version, $apiBaseUrl)
    {
        if (!extension_loaded('curl')) {
            throw new WC_WooMercadoPago_Exception('cURL extension not found. You need to enable cURL in your php.ini or another configuration you have.');
        }

        if (!isset($request['method'])) {
            throw new WC_WooMercadoPago_Exception('No HTTP METHOD specified');
        }

        if (!isset($request['uri'])) {
            throw new WC_WooMercadoPago_Exception('No URI specified');
        }

        $headers = array('accept: application/json');
        if ($request['method'] == 'POST' ) {
            $headers[] = 'x-product-id: ' . self::PRODUCT_ID;
        }
        $json_content = true;
        $form_content = false;
        $default_content_type = true;

        if (isset($request['headers']) && is_array($request['headers'])) {
            foreach ($request['headers'] as $h => $v) {
                $h = strtolower($h);
                $v = strtolower($v);
                if ($h == 'content-type') {
                    $default_content_type = false;
                    $json_content = $v == 'application/json';
                    $form_content = $v == 'application/x-www-form-urlencoded';
                }
                array_push($headers, $h . ': ' . $v);
            }
        }
        if ($default_content_type) {
            array_push($headers, 'content-type: application/json');
        }

        $connect = curl_init();
        curl_setopt($connect, CURLOPT_USERAGENT, 'platform:v1-whitelabel,type:woocommerce,so:' . $version);
        curl_setopt($connect, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($connect, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($connect, CURLOPT_CAINFO, $GLOBALS['LIB_LOCATION'] . '/cacert.pem');
        curl_setopt($connect, CURLOPT_CUSTOMREQUEST, $request['method']);
        curl_setopt($connect, CURLOPT_HTTPHEADER, $headers);

        if (isset($request['params']) && is_array($request['params'])) {
            if (count($request['params']) > 0) {
                $request['uri'] .= (strpos($request['uri'], '?') === false) ? '?' : '&';
                $request['uri'] .= self::build_query($request['params']);
            }
        }

        curl_setopt($connect, CURLOPT_URL, $apiBaseUrl . $request['uri']);


        if (isset($request['data'])) {
            if ($json_content) {
                if (gettype($request['data']) == 'string') {
                    json_decode($request['data'], true);
                } else {
                    $request['data'] = json_encode($request['data']);
                }
                if (function_exists('json_last_error')) {
                    $json_error = json_last_error();
                    if ($json_error != JSON_ERROR_NONE) {
                        throw new WC_WooMercadoPago_Exception("JSON Error [{$json_error}] - Data: " . $request['data']);
                    }
                }
            } elseif ($form_content) {
                $request['data'] = self::build_query($request['data']);
            }
            curl_setopt($connect, CURLOPT_POSTFIELDS, $request['data']);
        }

        return $connect;
    }

    /**
     * @param $request
     * @param $version
     * @param $connect
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function execute($request, $version, $connect)
    {
        $response = null;
        $api_result = curl_exec($connect);
        $api_http_code = curl_getinfo($connect, CURLINFO_HTTP_CODE);

        if ($api_result === FALSE) {
            throw new WC_WooMercadoPago_Exception (curl_error($connect));
        }

        if ($api_http_code != null && $api_result != null) {
            $response = array('status' => $api_http_code, 'response' => json_decode($api_result, true));
        }

        if ($response != null && $response['status'] >= 400 && self::$check_loop == 0) {
            try {
                self::$check_loop = 1;
                $message = null;
                $payloads = null;
                $endpoint = null;
                $errors = array();
                if (isset($response['response'])) {
                    if (isset($response['response']['message'])) {
                        $message = $response['response']['message'];
                    }
                    if (isset($response['response']['cause'])) {
                        $message .= json_encode($response['response']['cause']);
                    }
                }
                if ($request != null) {
                    if (isset($request['data'])) {
                        if ($request['data'] != null) {
                            $payloads = json_encode($request['data']);
                        }
                    }
                    if (isset($request['uri'])) {
                        if ($request['uri'] != null) {
                            $endpoint = $request['uri'];
                        }
                    }
                }
                $errors[] = array(
                    'endpoint' => $endpoint,
                    'message' => $message,
                    'payloads' => $payloads
                );
                self::sendErrorLog($response['status'], $errors, $version);
            } catch (Exception $e) {
                throw new WC_WooMercadoPago_Exception('Error to call API LOGS' . $e);
            }
        }

        self::$check_loop = 0;
        curl_close($connect);
        return $response;
    }

    /**
     * @param $code
     * @param $errors
     * @param $version
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function sendErrorLog($code, $errors, $version)
    {
        $data = array(
            'code' => $code,
            'module' => 'WooCommerce',
            'module_version' => $version,
            'url_store' => $_SERVER['HTTP_HOST'],
            'errors' => $errors,
            'email_admin' => self::$email_admin,
            'country_initial' => self::$site_locale
        );
        $request = array(
            'uri' => '/modules/log',
            'data' => $data
        );
        $result_response = MeLiRestClient::post($request, $version);
        return $result_response;
    }

    /**
     * @param $params
     * @return string
     */
    public static function build_query($params)
    {
        if (function_exists('http_build_query')) {
            return http_build_query($params, '', '&');
        } else {
            foreach ($params as $name => $value) {
                $elements[] = "{$name}=" . urlencode($value);
            }
            return implode('&', $elements);
        }

    }

    /**
     * @param $email
     */
    public static function set_email($email)
    {
        self::$email_admin = $email;
    }

    /**
     * @param $country_code
     */
    public static function set_locale($country_code)
    {
        self::$site_locale = $country_code;
    }
}
