<?php

namespace MercadoPago\Woocommerce\Endpoints;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class CheckoutCustom
{
    /**
     * @var Endpoints
     */
    public $endpoints;

    /**
     * @var Logs
     */
    public $logs;

    /**
     * @var Requester
     */
    public $requester;

    /**
     * @var Session
     */
    public $session;

    /**
     * @var Seller
     */
    public $seller;

      /**
     * @var array
     */
    private $threeDsTranslations;

    /**
     *
     */
    public function __construct(
        Endpoints $endpoints,
        Logs $logs,
        Requester $requester,
        Session $session,
        Seller $seller,
        StoreTranslations $storeTranslations
    ) {
        $this->endpoints           = $endpoints;
        $this->logs                = $logs;
        $this->requester           = $requester;
        $this->session             = $session;
        $this->seller              = $seller;
        $this->threeDsTranslations = $storeTranslations->threeDsTranslations;

        $this->registerFrontendEndpoints();
    }

    /**
     *
     */
    public function registerFrontendEndpoints()
    {
        $this->registerCustomCheckoutEndpoints();
    }

    /**
     *
     */
    public function registerCustomCheckoutEndpoints()
    {
        $this->endpoints->registerWCAjaxEndpoint('mp_get_3ds_from_session', [$this, 'mercadopagoGet3DSFromSession']);
        $this->endpoints->registerWCAjaxEndpoint('mp_redirect_after_3ds_challenge', [$this, 'mercadopagoRedirectAfter3DSChallenge']);
    }

    /**
     * Get 3DS information from Session
     *
     * @return void
     */
    public function mercadopagoGet3DSFromSession(): void
    {
        try {
            wp_send_json_success([
                'result' => 'success',
                'data'   => [
                    '3ds_url'  => $this->session->getSession('mp_3ds_url'),
                    '3ds_creq' => $this->session->getSession('mp_3ds_creq'),
                ],
            ]);
        } catch (\Exception $e) {
            $this->logs->file->error('3DS session error: ' . $e->getMessage(), __CLASS__);
            wp_send_json_error([
                'result' => 'failure',
                'data' => [
                    'error' => 'Couldn\'t find 3DS info on current session',
                ],
            ]);
        }
    }

    /**
     * Get 3DS information from Session
     *
     * @return void
     */
    public function mercadopagoRedirectAfter3DSChallenge(): void
    {
        $orderId   = $this->session->getSession('mp_order_id');
        $paymentId = $this->session->getSession('mp_payment_id');

        $order = wc_get_order($orderId);

        try {
            $this->session->deleteSession('mp_3ds_url');
            $this->session->deleteSession('mp_3ds_creq');
            $this->session->deleteSession('mp_order_id');
            $this->session->deleteSession('mp_payment_id');

            $headers  = ['Authorization: Bearer ' . $this->seller->getCredentialsAccessToken()];
            $payment  = $this->requester->get("/v1/payments/$paymentId", $headers)->getData();

            if ($payment['status'] === 'approved' || $payment['status'] === 'pending') {
                wp_send_json_success([
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_order_received_url(),
                ]);
            } else {
                wp_send_json_error([
                    'result' => 'failure',
                    'redirect' => '',
                    'data' => [
                        'error' => $this->threeDsTranslations['message_3ds_declined'],
                    ],
                ]);
            }
        } catch (\Exception $e) {
            $this->logs->file->error('3DS session error: ' . $e->getMessage(), __CLASS__);

            wp_send_json_error([
                'result' => 'failed',
                'redirect' => $order->get_checkout_payment_url(true),
            ]);
        }
    }
}
