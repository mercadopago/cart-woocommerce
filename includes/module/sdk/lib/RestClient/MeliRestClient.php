<?php
/**
 * Class MeliRestClient
 */
class MeliRestClient extends AbstractRestClient
{
    const API_MELI_BASE_URL = 'https://api.mercadolibre.com';

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function get($request)
    {
        $request['method'] = 'GET';
        return self::execAbs($request, self::API_MELI_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function post($request)
    {
        $request['method'] = 'POST';
        return self::execAbs($request, self::API_MELI_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function put($request)
    {
        $request['method'] = 'PUT';
        return self::execAbs($request, self::API_MELI_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function delete($request)
    {
        $request['method'] = 'DELETE';
        return self::execAbs($request, self::API_MELI_BASE_URL);
    }
}
