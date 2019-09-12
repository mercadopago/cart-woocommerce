<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_Configs
 */
class WC_WooMercadoPago_Configs
{
    /**
     * WC_WooMercadoPago_Configs constructor.
     * @throws WC_WooMercadoPago_Exception
     */
    public function __construct()
    {
        $this->updateTokenNewVersion();
        $this->showNotices();
    }

    /**
     *  Show Notices in ADMIN
     */
    private function showNotices()
    {
        if (empty(get_option('_mp_public_key_prod')) && empty(get_option('_mp_access_token_prod'))) {
            if (!empty(get_option('_mp_client_id')) && !empty(get_option('_mp_client_secret'))) {
                add_action('admin_notices', array($this, 'noticeUpdateAccessToken'));
            }
        }

        if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off')) {
            add_action('admin_notices', array($this, 'noticeHttps'));
        }
    }

    /**
     * @throws WC_WooMercadoPago_Exception
     */
    private function updateTokenNewVersion()
    {
        if (empty(get_option('_mp_public_key_prod', '')) || empty(get_option('_mp_access_token_prod', ''))) {
            if (!empty(get_option('_mp_public_key')) && !empty(get_option('_mp_access_token'))) {
                $this->updateToken();
            }
        }

        if (empty(get_option('_site_id_v1'))) {
            WC_WooMercadoPago_Credentials::validate_credentials_v1();
        }

        $ticketMethods = get_option('_all_payment_methods_ticket', '');
        if (empty($ticketMethods)) {
            $this->updateTicketMethods();
        }

        $allPayments = get_option('_checkout_payments_methods', '');
        if (empty($allPayments)) {
            $this->updatePayments();
            return;
        }

        if (!empty($allPayments)) {
            foreach ($allPayments as $payment) {
                if (!isset($payment['name'])) {
                    $this->updatePayments();
                    break;
                }
            }
        }
    }

    /**
     * @throws WC_WooMercadoPago_Exception
     */
    private function updatePayments()
    {
        $mpInstance = WC_WooMercadoPago_Module::getMpInstanceSingleton();
        if ($mpInstance) {
            WC_WooMercadoPago_Credentials::updatePaymentMethods($mpInstance, $mpInstance->get_access_token());
        }
    }

    /**
     * @throws WC_WooMercadoPago_Exception
     */
    private function updateTicketMethods()
    {
        $mpInstance = WC_WooMercadoPago_Module::getMpInstanceSingleton();
        if ($mpInstance) {
            WC_WooMercadoPago_Credentials::updateTicketMethod($mpInstance, $mpInstance->get_access_token());
        }
    }

    /**
     *  Notice AccessToken
     */
    public function noticeUpdateAccessToken()
    {
        echo '<div class="error is-dismissible">
            <p><strong>MERCADO PAGO: </strong>'
            . __('Actualizá tus credenciales con las claves Access Token y Public Key ¡las necesitás para seguir recibiendo pagos!', 'woocommerce-mercadopado') . '
            </p>
        </div>';
    }

    /**
     * Notice HTTPS
     */
    public function noticeHttps()
    {
        $message = __('The store must have HTTPS to see the payment methods.', 'woocommerce-mercadopago');
        echo '<div class="notice notice-warning is-dismissible">  
                    <p>
                        <strong>MERCADO PAGO:</strong> ' . $message . '
                    </p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">' . __('Discard', 'woocommerce-mercadopago') . '</span>
                    </button>
              </div>';
    }

    /**
     *  UpdateToken
     */
    private function updateToken()
    {
        if (strpos(get_option('_mp_public_key'), 'TEST') === 0 && strpos(get_option('_mp_access_token'), 'TEST') === 0) {
            update_option('_mp_public_key_test', get_option('_mp_public_key'), true);
            update_option('_mp_access_token_test', get_option('_mp_access_token'), true);
            update_option('checkout_credential_production', 'no', true);
        }

        if (strpos(get_option('_mp_public_key'), 'APP_USR') === 0 && strpos(get_option('_mp_access_token'), 'APP_USR') === 0) {
            update_option('_mp_public_key_prod', get_option('_mp_public_key'), true);
            update_option('_mp_access_token_prod', get_option('_mp_access_token'), true);
            update_option('checkout_credential_production', 'yes', true);
        }
    }

    /**
     *  Country Configs
     */
    public function getCountryConfigs()
    {
        return array(
            'MCO' => array(
                'site_id' => 'MCO',
                'sponsor_id' => 208687643,
                'checkout_banner' => plugins_url('../../assets/images/MCO/standard_mco.jpg', __FILE__),
                'checkout_banner_custom' => plugins_url('../../assets/images/MCO/credit_card.png', __FILE__),
                'currency' => 'COP'
            ),
            'MLA' => array(
                'site_id' => 'MLA',
                'sponsor_id' => 208682286,
                'checkout_banner' => plugins_url('../../assets/images/MLA/standard_mla.jpg', __FILE__),
                'checkout_banner_custom' => plugins_url('../../assets/images/MLA/credit_card.png', __FILE__),
                'currency' => 'ARS'
            ),
            'MLB' => array(
                'site_id' => 'MLB',
                'sponsor_id' => 208686191,
                'checkout_banner' => plugins_url('../../assets/images/MLB/standard_mlb.jpg', __FILE__),
                'checkout_banner_custom' => plugins_url('../../assets/images/MLB/credit_card.png', __FILE__),
                'currency' => 'BRL'
            ),
            'MLC' => array(
                'site_id' => 'MLC',
                'sponsor_id' => 208690789,
                'checkout_banner' => plugins_url('../../assets/images/MLC/standard_mlc.gif', __FILE__),
                'checkout_banner_custom' => plugins_url('../../assets/images/MLC/credit_card.png', __FILE__),
                'currency' => 'CLP'
            ),
            'MLM' => array(
                'site_id' => 'MLM',
                'sponsor_id' => 208692380,
                'checkout_banner' => plugins_url('../../assets/images/MLM/standard_mlm.jpg', __FILE__),
                'checkout_banner_custom' => plugins_url('../../assets/images/MLM/credit_card.png', __FILE__),
                'currency' => 'MXN'
            ),
            'MLU' => array(
                'site_id' => 'MLU',
                'sponsor_id' => 243692679,
                'checkout_banner' => plugins_url('../../assets/images/MLU/standard_mlu.png', __FILE__),
                'checkout_banner_custom' => plugins_url('../../assets/images/MLU/credit_card.png', __FILE__),
                'currency' => 'UYU'
            ),
            'MLV' => array(
                'site_id' => 'MLV',
                'sponsor_id' => 208692735,
                'checkout_banner' => plugins_url('../../assets/images/MLV/standard_mlv.jpg', __FILE__),
                'checkout_banner_custom' => plugins_url('../../assets/images/MLV/credit_card.png', __FILE__),
                'currency' => 'VEF'
            ),
            'MPE' => array(
                'site_id' => 'MPE',
                'sponsor_id' => 216998692,
                'checkout_banner' => plugins_url('../../assets/images/MPE/standard_mpe.png', __FILE__),
                'checkout_banner_custom' => plugins_url('../../assets/images/MPE/credit_card.png', __FILE__),
                'currency' => 'PEN'
            )
        );
    }

    /**
     * @return array
     */
    public function getCategories()
    {
        return array(
            'store_categories_id' =>
                [
                    "art", "baby", "coupons", "donations", "computing", "cameras", "video games", "television",
                    "car electronics", "electronics", "automotive", "entertainment", "fashion", "games", "home",
                    "musical", "phones", "services", "learnings", "tickets", "travels", "virtual goods", "others"
                ],
            'store_categories_description' =>
                [
                    "Collectibles & Art", "Toys for Baby, Stroller, Stroller Accessories, Car Safety Seats", "Coupons",
                    "Donations", "Computers & Tablets", "Cameras & Photography", "Video Games & Consoles",
                    "LCD, LED, Smart TV, Plasmas, TVs", "Car Audio, Car Alarm Systems & Security, Car DVRs, Car Video Players, Car PC",
                    "Audio & Surveillance, Video & GPS, Others", "Parts & Accessories", "Music, Movies & Series, Books, Magazines & Comics, Board Games & Toys",
                    "Men's, Women's, Kids & baby, Handbags & Accessories, Health & Beauty, Shoes, Jewelry & Watches",
                    "Online Games & Credits", "Home appliances. Home & Garden", "Instruments & Gear",
                    "Cell Phones & Accessories", "General services", "Trainings, Conferences, Workshops",
                    "Tickets for Concerts, Sports, Arts, Theater, Family, Excursions tickets, Events & more",
                    "Plane tickets, Hotel vouchers, Travel vouchers",
                    "E-books, Music Files, Software, Digital Images,  PDF Files and any item which can be electronically stored in a file, Mobile Recharge, DTH Recharge and any Online Recharge",
                    "Other categories"
                ]
        );
    }

    /**
     * @param $methods
     * @return mixed
     */
    public function setShipping($methods)
    {
        $methods['woo-mercado-pago-me-normal'] = 'WC_MercadoEnvios_Shipping_Normal';
        $methods['woo-mercado-pago-me-express'] = 'WC_MercadoEnvios_Shipping_Express';
        return $methods;
    }

    /**
     * @param $methods
     * @return array
     */
    public function setPaymentGateway($methods = null)
    {
        global $wp;
        if (!empty($wp) && isset($wp->query_vars['wc-api'])) {
            $api_request = strtolower(wc_clean($wp->query_vars['wc-api']));
            if (!empty($api_request)) {
                $methods[] = $api_request;
                return $methods;
            }
        }

        $methods[] = 'WC_WooMercadoPago_BasicGateway';
        $methods[] = 'WC_WooMercadoPago_CustomGateway';
        $methods[] = 'WC_WooMercadoPago_TicketGateway';
        return $methods;
    }


}