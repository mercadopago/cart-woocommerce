<?php

/**
 * Class MPRestClient
 */
class MPRestClient extends AbstractRestClient
{
    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function get($request)
    {
        $request['method'] = 'GET';
        return self::execAbs($request, WC_WooMercadoPago_Constants::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function post($request)
    {
        $request['method'] = 'POST';
        return self::execAbs($request, WC_WooMercadoPago_Constants::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function put($request)
    {
        $request['method'] = 'PUT';
        return self::execAbs($request, WC_WooMercadoPago_Constants::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function delete($request)
    {
        $request['method'] = 'DELETE';
        return self::execAbs($request, WC_WooMercadoPago_Constants::API_MP_BASE_URL);
    }

}
