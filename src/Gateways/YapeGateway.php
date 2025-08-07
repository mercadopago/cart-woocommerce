<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Transactions\YapeTransaction;

if (!defined('ABSPATH')) {
    exit;
}

class YapeGateway extends AbstractGateway
{
    public const ID = 'woo-mercado-pago-yape';

    public const CHECKOUT_NAME = 'checkout-yape';

    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Yape_Gateway';

    public const LOG_SOURCE = 'MercadoPago_YapeGateway';

    /**
     * YapeGateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->yapeGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->yapeCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-yape');
        $this->iconAdmin = $this->icon;
        $this->title     = $this->mercadopago->storeConfig->getGatewayTitle($this, $this->adminTranslations['gateway_title']);

        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];
        $this->discount           = $this->getActionableValue('gateway_discount', 0);
        $this->commission         = $this->getActionableValue('commission', 0);

        $this->mercadopago->hooks->gateway->registerUpdateOptions($this);
        $this->mercadopago->hooks->gateway->registerGatewayTitle($this);
        $this->mercadopago->hooks->gateway->registerThankYouPage($this->id, [$this, 'saveOrderPaymentsId']);

        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);

        $this->mercadopago->hooks->cart->registerCartCalculateFees([$this, 'registerDiscountAndCommissionFeesOnCart']);

        $this->mercadopago->helpers->currency->handleCurrencyNotices($this);
    }

    /**
     * Get checkout name
     *
     * @return string
     */
    public function getCheckoutName(): string
    {
        return self::CHECKOUT_NAME;
    }

    public function formFieldsMainSection(): array
    {
        return [
            'card_info_helper' => [
                'type'  => 'title',
                'value' => '',
            ],
            'currency_conversion' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['currency_conversion_title'],
                'subtitle'     => $this->adminTranslations['currency_conversion_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['currency_conversion_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['currency_conversion_descriptions_disabled'],
                ],
            ],
            'advanced_configuration_title' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_title'],
                'class' => 'mp-subtitle-body',
            ],
            'advanced_configuration_description' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_subtitle'],
                'class' => 'mp-small-text',
            ],
        ];
    }

    /**
     * Added gateway scripts
     *
     * @param string $gatewaySection
     *
     * @return void
     */
    public function payment_scripts(string $gatewaySection): void
    {
        parent::payment_scripts($gatewaySection);

        if ($this->canCheckoutLoadScriptsAndStyles()) {
            $this->registerCheckoutScripts();
        }
    }

    /**
     * Register checkout scripts
     *
     * @return void
     */
    public function registerCheckoutScripts(): void
    {
        parent::registerCheckoutScripts();

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_sdk',
            'https://sdk.mercadopago.com/js/v2'
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_yape_checkout',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/yape/mp-yape-checkout'),
            [
                'public_key' => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
            ]
        );
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/checkouts/yape-checkout.php',
            $this->getPaymentFieldsParams()
        );
    }

    /**
     * Get Payment Fields params
     *
     * @return array
     */
    public function getPaymentFieldsParams(): array
    {
        return [
            'test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
            'test_mode_description'            => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'input_field_label'                => $this->storeTranslations['yape_input_field_label'],
            'checkout_notice_message'          => $this->storeTranslations['checkout_notice_message'],
            'yape_title'                       => $this->storeTranslations['yape_title'],
            'yape_subtitle'                    => $this->storeTranslations['yape_subtitle'],
            'input_code_label'                 => $this->storeTranslations['input_code_label'],
            'footer_text'                      => $this->storeTranslations['footer_text'],
            'test_mode_link_src'               => $this->links['docs_integration_test'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'input_code_icon'                  => $this->mercadopago->helpers->url->getImageAsset('checkouts/yape/yape-tooltip-icon.svg'),
            'checkout_notice_icon_one'         => $this->mercadopago->helpers->url->getImageAsset('checkouts/yape/checkout-notice-icon'),
            'checkout_notice_icon_two'         => $this->mercadopago->helpers->url->getImageAsset('checkouts/yape/mp-transparent-icon.svg'),
            'yape_tooltip_text'                 => $this->storeTranslations['yape_tooltip_text'],
            'yape_input_code_error_message1'    => $this->storeTranslations['yape_input_code_error_message1'],
            'yape_input_code_error_message2'    => $this->storeTranslations['yape_input_code_error_message2'],
            'yape_phone_number_error_message1'  => $this->storeTranslations['yape_phone_number_error_message1'],
            'yape_phone_number_error_message2'  => $this->storeTranslations['yape_phone_number_error_message2'],
            'checkout_blocks_row_image_src'    => $this->icon,
        ];
    }

    /**
     * Process payment and create woocommerce order
     *
     * @param $order_id
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        try {
            parent::process_payment($order_id);
            $checkout = $this->getCheckoutMercadopagoYape($order);
            $this->transaction = new YapeTransaction($this, $order, $checkout);
            $response          = $this->transaction->createPayment();

            $this->mercadopago->orderMetadata->setCustomMetadata($order, $response);

            return $this->handleResponseStatus($order, $response);
        } catch (Exception $e) {
            return $this->processReturnFail(
                $e,
                $e->getMessage(),
                self::LOG_SOURCE,
                (array) $order,
                true
            );
        }
    }

    /**
     * Get checkout mercadopago yape
     *
     * @param $order
     *
     * @return array
     */
    private function getCheckoutMercadopagoYape($order): array
    {

        if (isset($_POST['mercadopago_yape'])) {
            $checkout = Form::sanitizedPostData('mercadopago_yape');
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
        } else {
            $checkout = $this->processBlocksCheckoutData('mercadopago_yape', Form::sanitizedPostData());
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
        }

        return $checkout;
    }

    /**
     * Handle with response status
     *
     * @param $order
     * @param $response
     *
     * @return array
     * @throws RejectedPaymentException
     * @throws ResponseStatusException
     */
    private function handleResponseStatus($order, $response): array
    {
        if (is_array($response) && array_key_exists('status', $response)) {
            switch ($response['status']) {
                case 'approved':
                    $this->mercadopago->helpers->cart->emptyCart();

                    $urlReceived = $order->get_checkout_order_received_url();
                    $orderStatus = $this->mercadopago->orderStatus->getOrderStatusMessage('accredited');

                    $this->mercadopago->helpers->notices->storeApprovedStatusNotice($orderStatus);
                    $this->mercadopago->orderStatus->setOrderStatus($order, 'failed', 'pending');

                    return [
                        'result'   => 'success',
                        'redirect' => $urlReceived,
                    ];
                case 'pending':
                    // no break
                case 'in_process':
                    $this->mercadopago->helpers->cart->emptyCart();

                    $urlReceived = $order->get_checkout_order_received_url();

                    return [
                        'result'   => 'success',
                        'redirect' => $urlReceived,
                    ];
                case 'rejected':
                    $this->handleWithRejectPayment($response);
                    break;
                default:
                    break;
            }
        }

        throw new ResponseStatusException('Response status not mapped on ' . __METHOD__);
    }

    /**
     * Verify if the gateway is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $mercadopago;

        $siteId  = $mercadopago->sellerConfig->getSiteId();
        $country = $mercadopago->helpers->country::getWoocommerceDefaultCountry();

        return $siteId === 'MPE' || ($siteId === '' && $country === 'PE');
    }

    public function getRejectedPaymentErrorMessage(string $statusDetail): string
    {
        return $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_yape_' . $statusDetail] ??
            $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_yape_default'];
    }
}
