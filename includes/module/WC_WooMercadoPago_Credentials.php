<?php

/**
 * Class WC_WooMercadoPago_Credentials
 */
class WC_WooMercadoPago_Credentials
{
    const TYPE_ACCESS_CLIENT = 'client';
    const TYPE_ACCESS_TOKEN = 'token';

    public $payment;
    public $publicKey;
    public $accessToken;
    public $clientId;
    public $clientSecret;
    public $sandbox;
    public $log;

    /**
     * WC_WooMercadoPago_Credentials constructor.
     * @param $payment
     */
    public function __construct($payment = null)
    {
        $this->payment = $payment;
        $publicKey = get_option('_mp_public_key_prod', '');
        $accessToken = get_option('_mp_access_token_prod', '');

        if (!is_null($this->payment)) {
            $this->sandbox = $payment->isTestUser();
            if ($this->payment->getOption('checkout_credential_prod', '') == 'no' || empty($this->payment->getOption('checkout_credential_prod', ''))) {
                $publicKey = get_option('_mp_public_key_test', '');
                $accessToken = get_option('_mp_access_token_test', '');
            }
        }

        if (is_null($this->payment) && empty($publicKey) && empty($accessToken)) {
            $publicKey = get_option('_mp_public_key_test', '');
            $accessToken = get_option('_mp_access_token_test', '');
        }

        $this->publicKey = $publicKey;
        $this->accessToken = $accessToken;
        $this->clientId = get_option('_mp_client_id');
        $this->clientSecret = get_option('_mp_client_secret');
    }

    /**
     * @return bool|string
     */
    public function validateCredentialsType()
    {
        $basicIsEnabled = self::basicIsEnabled();
        if (!$this->tokenIsValid() && ($this->payment instanceof WC_WooMercadoPago_BasicGateway || $basicIsEnabled == 'yes')) {
            if (!$this->clientIsValid()) {
                return self::TYPE_ACCESS_TOKEN;
            }
            return self::TYPE_ACCESS_CLIENT;
        }

        return self::TYPE_ACCESS_TOKEN;
    }

    /**
     * @return bool
     */
    public function clientIsValid()
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function tokenIsValid()
    {
        if (empty($this->publicKey) || empty($this->accessToken)) {
            return false;
        }

        if (strpos($this->publicKey, 'APP_USR') === false && strpos($this->publicKey, 'TEST') === false) {
            return false;
        }
        if (strpos($this->accessToken, 'APP_USR') === false && strpos($this->accessToken, 'TEST') === false) {
            return false;
        }

        return true;
    }

    /**
     * Set no Credentials
     */
    public static function setNoCredentials()
    {
        update_option('_test_user_v1', '', true);
        update_option('_site_id_v1', '', true);
        update_option('_collector_id_v1', '', true);
        update_option('_all_payment_methods_v0', array(), true);
        update_option('_all_payment_methods_ticket', '[]', true);
        update_option('_can_do_currency_conversion_v1', false, true);
    }

    /**
     * @param $access_token
     * @return bool
     * @throws WC_WooMercadoPago_Exception
     */
    public static function access_token_is_valid($access_token)
    {
        $mp_v1 = WC_WooMercadoPago_Module::getMpInstanceSingleton();
        if (empty($mp_v1)) {
            return false;
        }
        $get_request = $mp_v1->get('/users/me?access_token=' . $access_token);
        if ($get_request['status'] > 202) {
            $log = WC_WooMercadoPago_Log::init_mercado_pago_log('WC_WooMercadoPago_Credentials');
            $log->write_log('API valid_access_token error:', $get_request['response']['message']);
            return false;
        }

        if (isset($get_request['response']['site_id'])) {
            update_option('_site_id_v1', $get_request['response']['site_id'], true);
            update_option('_test_user_v1', in_array('test_user', $get_request['response']['tags']), true);
        }

        return true;
    }

    /**
     * @param $public_key
     * @return bool
     * @throws WC_WooMercadoPago_Exception
     */
    public static function public_key_is_valid($public_key)
    {
        $mp_v1 = WC_WooMercadoPago_Module::getMpInstanceSingleton();
        if (empty($mp_v1)) {
            return false;
        }
        $request = array(
            'uri' => '/v1/card_tokens',
            'data' => null,
            'params' => array(
                'public_key' => $public_key
            ),
            'authenticate' => false 
        );
        $get_request = $mp_v1->post($request);
        if ($get_request['status'] > 202) {
            $log = WC_WooMercadoPago_Log::init_mercado_pago_log('WC_WooMercadoPago_Credentials');
            $log->write_log('API public_key_is_valid error: ', $get_request['response']['message']);
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public static function validate_credentials_v1()
    {
        $credentials = new self();
        $basicIsEnabled = 'no';
        if (!$credentials->tokenIsValid()) {
            $basicIsEnabled = self::basicIsEnabled();
            if ($basicIsEnabled != 'yes') {
                self::setNoCredentials();
                return false;
            }
        }

        try {
            $mp_v1 = WC_WooMercadoPago_Module::getMpInstanceSingleton();
            if ($mp_v1 instanceof MP == false) {
                self::setNoCredentials();
                return false;
            }
            $access_token = $mp_v1->get_access_token();
            $get_request = $mp_v1->get('/users/me?access_token=' . $access_token);
            if (isset($get_request['response']['site_id']) && (!empty($credentials->publicKey) || $basicIsEnabled == 'yes')) {

                update_option('_test_user_v1', in_array('test_user', $get_request['response']['tags']), true);
                update_option('_site_id_v1', $get_request['response']['site_id'], true);
                update_option('_collector_id_v1', $get_request['response']['id'], true);

                $payments_response = self::getPaymentResponse($mp_v1, $access_token);
                self::updatePaymentMethods($mp_v1, $access_token, $payments_response);
                self::updateTicketMethod($mp_v1, $access_token, $payments_response);

                $currency_ratio = WC_WooMercadoPago_Module::get_conversion_rate(
                    WC_WooMercadoPago_Module::$country_configs[$get_request['response']['site_id']]['currency']
                );

                if ($currency_ratio > 0) {
                    update_option('_can_do_currency_conversion_v1', true, true);
                } else {
                    update_option('_can_do_currency_conversion_v1', false, true);
                }
                return true;
            }
        } catch (WC_WooMercadoPago_Exception $e) {
            $log = WC_WooMercadoPago_Log::init_mercado_pago_log('WC_WooMercadoPago_Credentials');
            $log->write_log('validate_credentials_v1', 'Exception ERROR');
        }

        self::setNoCredentials();
        return false;
    }

    /**
     * @param $mpInstance
     * @param $accessToken
     * @return null
     */
    public static function getPaymentResponse($mpInstance, $accessToken)
    {
        $seller = explode('-', $accessToken);
        $payments = $mpInstance->get('/users/' . end($seller) . '/accepted_payment_methods?marketplace=NONE');
        if (isset($payments['response'])) {
            return $payments['response'];
        }

        return null;
    }

    /**
     * @param $mpInstance
     * @param null $accessToken
     * @param null $paymentsResponse
     */
    public static function updatePaymentMethods($mpInstance, $accessToken = null, $paymentsResponse = null)
    {
        if (empty($accessToken) || empty($mpInstance)) {
            return;
        }

        if (empty($paymentsResponse)) {
            $paymentsResponse = self::getPaymentResponse($mpInstance, $accessToken);
        }

        if (empty($paymentsResponse) || (isset($paymentsResponse['status']) && $paymentsResponse['status'] != 200 &&
                $paymentsResponse['status'] != 201)) {
            return;
        }

        $arr = array();
        $cho = array();
        $excluded = array('consumer_credits', 'paypal');

        foreach ($paymentsResponse as $payment) {
            if (in_array($payment['id'], $excluded)) {
                continue;
            }

            $arr[] = $payment['id'];

            $cho[] = array(
                "id" => $payment['id'],
                "name" => $payment['name'],
                "type" => $payment['payment_type_id'],
                "image" => $payment['secure_thumbnail'],
                "config" => "ex_payments_" . $payment['id'],
            );
        }

        update_option('_all_payment_methods_v0', implode(',', $arr), true);
        update_option('_checkout_payments_methods', $cho, true);
    }

    /**
     * @param $mpInstance
     * @param $accessToken
     * @param null $paymentsResponse
     */
    public static function updateTicketMethod($mpInstance, $accessToken, $paymentsResponse = null)
    {
        if (empty($accessToken) || empty($mpInstance)) {
            return;
        }

        if (empty($paymentsResponse)) {
            $paymentsResponse = self::getPaymentResponse($mpInstance, $accessToken);
        }

        if (empty($paymentsResponse) || (isset($paymentsResponse['status']) && $paymentsResponse['status'] != 200 &&
                $paymentsResponse['status'] != 201)) {
            return;
        }

        $payment_methods_ticket = array();
        $excluded = array('consumer_credits', 'paypal', 'pse');

        foreach ($paymentsResponse as $payment) {
            if (!in_array($payment['id'], $excluded) &&
                $payment['payment_type_id'] != 'account_money' &&
                $payment['payment_type_id'] != 'credit_card' &&
                $payment['payment_type_id'] != 'debit_card' &&
                $payment['payment_type_id'] != 'prepaid_card'
            ) {
                $payment_methods_ticket[] = array(
                    "id" => $payment['id'],
                    "name" => $payment['name'],
                    "secure_thumbnail" => $payment['secure_thumbnail'],
                );
            }
        }

        update_option('_all_payment_methods_ticket', $payment_methods_ticket, true);
    }

    /**
     * @return string
     */
    public static function basicIsEnabled()
    {
        $basicIsEnabled = 'no';
        $basicSettings = get_option('woocommerce_woo-mercado-pago-basic_settings', '');
        if (isset($basicSettings['enabled'])) {
            $basicIsEnabled = $basicSettings['enabled'];
        }

        return $basicIsEnabled;
    }
}
