<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Transactions\PseTransaction;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;

if (!defined('ABSPATH')) {
    exit;
}

class PseGateway extends AbstractGateway
{
    /**
     * ID
     *
     * @const
     */
    public const ID = 'woo-mercado-pago-pse';

    /**
     * @const
     */
    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Pse_Gateway';

    /**
     * @const
     */
    public const LOG_SOURCE = 'MercadoPago_PseGateway';

    /**
     * PseGateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->pseGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->pseCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pse');
        $this->iconAdmin = $this->icon;
        $this->title     = $this->mercadopago->storeConfig->getGatewayTitle($this, $this->adminTranslations['gateway_title']);

        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['method_title'];
        $this->method_description = $this->description;
        $this->discount           = $this->getActionableValue('gateway_discount', 0);
        $this->commission         = $this->getActionableValue('commission', 0);

        $this->mercadopago->hooks->gateway->registerUpdateOptions($this);
        $this->mercadopago->hooks->gateway->registerGatewayTitle($this);

        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);
        $this->mercadopago->hooks->cart->registerCartCalculateFees([$this, 'registerDiscountAndCommissionFeesOnCart']);

        $this->mercadopago->helpers->currency->handleCurrencyNotices($this);
    }

    public function getCheckoutName(): string
    {
        return 'checkout-pse';
    }

    public function formFieldsMainSection(): array
    {
        return [
            'currency_conversion' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['currency_conversion_title'],
                'subtitle'     => $this->adminTranslations['currency_conversion_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['currency_conversion_enabled'],
                    'disabled' => $this->adminTranslations['currency_conversion_disabled'],
                ],
            ],
            'advanced_configuration_title' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_title_title'],
                'class' => 'mp-subtitle-body',
            ],
            'advanced_configuration_description' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_description_title'],
                'class' => 'mp-small-text',
            ],
            'stock_reduce_mode' => [
                'title'        => $this->adminTranslations['stock_reduce_title'],
                'type'         => 'mp_toggle_switch',
                'default'      => 'no',
                'subtitle'     => $this->adminTranslations['stock_reduce_subtitle'],
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['stock_reduce_enabled'],
                    'disabled' => $this->adminTranslations['stock_reduce_disabled'],
                ],
            ],
        ];
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
            'wc_mercadopago_pse_page',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/pse/mp-pse-page')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_pse_elements',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/pse/mp-pse-elements')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_pse_checkout',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/pse/mp-pse-checkout'),
            [
                'financial_placeholder' => $this->storeTranslations ['financial_placeholder'],
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
            'public/checkouts/pse-checkout.php',
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
        $currentUser     = $this->mercadopago->helpers->currentUser->getCurrentUser();
        $loggedUserEmail = ($currentUser->ID != 0) ? $currentUser->user_email : null;
        $amountAndCurrencyRatio = $this->getAmountAndCurrency();
        return [
            'checkout_blocks_row_image_src'    => $this->icon,
            'test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
            'test_mode_description'            => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'               => $this->links['docs_integration_test'],
            'input_document_label'             => $this->storeTranslations['input_document_label'],
            'input_document_helper_empty'      => $this->storeTranslations['input_document_helper_empty'],
            'input_document_helper_invalid'    => $this->storeTranslations['input_document_helper_invalid'],
            'input_document_helper_wrong'      => $this->storeTranslations['input_document_helper_wrong'],
            'pse_text_label'                   => $this->storeTranslations['pse_text_label'],
            'input_table_button'               => $this->storeTranslations['input_table_button'],
            'site_id'                          => $this->mercadopago->sellerConfig->getSiteId(),
            'payer_email'                      => esc_js($loggedUserEmail),
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'woocommerce_currency'             => get_woocommerce_currency(),
            'account_currency'                 => $this->mercadopago->helpers->country->getCountryConfigs(),
            'financial_institutions'           => json_encode($this->getFinancialInstitutions()),
            'person_type_label'                => $this->storeTranslations['person_type_label'],
            'financial_institutions_label'     => $this->storeTranslations['financial_institutions_label'],
            'financial_institutions_helper'    => $this->storeTranslations['financial_institutions_helper'],
            'financial_placeholder'            => $this->storeTranslations['financial_placeholder'],
            'amount'                           => $amountAndCurrencyRatio['amount'],
            'currency_ratio'                   => $amountAndCurrencyRatio['currencyRatio'],
            'message_error_amount'             => $this->storeTranslations['message_error_amount'],
        ];
    }

    public function proccessPaymentInternal($order): array
    {
        if (isset($_POST['mercadopago_pse'])) {
            $checkout = Form::sanitizedPostData('mercadopago_pse');
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
        } else {
            $checkout = $this->processBlocksCheckoutData('mercadopago_pse', Form::sanitizedPostData());
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
        }

        if (!$this->isCheckoutValid($checkout)) {
            return $this->processReturnFail(
                new InvalidCheckoutDataException(),
                'cho_form_error',
                self::LOG_SOURCE,
                $checkout,
                true
            );
        }

        $this->transaction = new PseTransaction($this, $order, $checkout);

        $response = $this->transaction->createPayment();

        if (is_array($response) && array_key_exists('status', $response)) {
            $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, ['id' => $response['id']]);
            $this->handleWithRejectPayment($response);
            if (
                $response['status'] === 'pending' &&
                in_array($response['status_detail'], ['pending_waiting_payment', 'pending_waiting_transfer'])
            ) {
                $this->mercadopago->woocommerce->cart->empty_cart();

                if ($this->mercadopago->hooks->options->getGatewayOption($this, 'stock_reduce_mode', 'no') === 'yes') {
                    wc_reduce_stock_levels($order->get_id());
                }
                $this->mercadopago->hooks->order->addOrderNote($order, $this->storeTranslations['customer_not_paid']);
                return [
                    'result'   => 'success',
                    'redirect' => $response['transaction_details']['external_resource_url'],
                ];
            }
            return $this->processReturnFail(
                new ResponseStatusException('exception : Invalid status or status_detail on ' . __METHOD__),
                'cho_form_error',
                self::LOG_SOURCE,
                $response
            );
        }

        throw new InvalidCheckoutDataException('exception : Unable to process payment on ' . __METHOD__);
    }

    /**
     * Get payment methods
     *
     * @return array
     */
    private function getFinancialInstitutions(): array
    {
        $psePaymentMethods = $this->mercadopago->sellerConfig->getCheckoutPsePaymentMethods();
        return $psePaymentMethods[0]['financial_institutions'];
    }

    /**
     * Verify if the gateway is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $mercadopago;
        return $mercadopago->helpers->country->getPluginDefaultCountry() === Country::COUNTRY_CODE_MCO;
    }

    private function isCheckoutValid(array $checkout): bool
    {
        return !Arrays::anyEmpty($checkout, [
                'site_id',
                'doc_number',
                'doc_type',
                'person_type',
                'bank',
            ])
            && $checkout['site_id'] === 'MCO'
            && in_array($checkout['person_type'], ['individual', 'association']);
    }
}
