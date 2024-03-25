<?php

namespace MercadoPago\Woocommerce\Gateways;

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
    public const CHECKOUT_NAME = 'checkout-pse';

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
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->pseGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->pseCheckout;

        $this->id    = self::ID;
        $this->icon  = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pse');
        $this->iconAdmin = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pse');
        $this->title = $this->mercadopago->storeConfig->getGatewayTitle($this, $this->adminTranslations['gateway_title']);

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

    /**
     * Get checkout name
     *
     * @return string
     */
    public function getCheckoutName(): string
    {
        return self::CHECKOUT_NAME;
    }

    /**
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
    {
        if ($this->addMissingCredentialsNoticeAsFormField()) {
            return;
        }
        parent::init_form_fields();

        $this->form_fields = array_merge($this->form_fields, [
            'config_header' => [
                'type'        => 'mp_config_title',
                'title'       => $this->adminTranslations['header_title'],
                'description' => $this->adminTranslations['header_description'],
            ],
            'card_homolog_validate' => $this->getHomologValidateNoticeOrHidden(),
            'card_settings'  => [
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => $this->adminTranslations['card_settings_title'],
                    'subtitle'    => $this->adminTranslations['card_settings_subtitle'],
                    'button_text' => $this->adminTranslations['card_settings_button_text'],
                    'button_url'  => $this->links['admin_settings_page'],
                    'icon'        => 'mp-icon-badge-info',
                    'color_card'  => 'mp-alert-color-success',
                    'size_card'   => 'mp-card-body-size',
                    'target'      => '_self',
                ],
            ],
            'enabled' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['enabled_title'],
                'subtitle'     => $this->adminTranslations['enabled_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['enabled_enabled'],
                    'disabled' => $this->adminTranslations['enabled_disabled'],
                ],
            ],
            'title' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['title_title'],
                'description' => $this->adminTranslations['title_description'],
                'default'     => $this->adminTranslations['title_default'],
                'desc_tip'    => $this->adminTranslations['title_desc_tip'],
                'class'       => 'limit-title-max-length',
            ],
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
            'gateway_discount' => $this->getDiscountField(),
            'commission'       => $this->getCommissionField(),
            'split_section' => [
                'type'  => 'title',
                'title' => "",
            ],
            'support_link' => [
                'type'  => 'mp_support_link',
                'bold_text'    => $this->adminTranslations['support_link_bold_text'],
                'text_before_link'    => $this->adminTranslations['support_link_text_before_link'],
                'text_with_link' => $this->adminTranslations['support_link_text_with_link'],
                'text_after_link'    => $this->adminTranslations['support_link_text_after_link'],
                'support_link'    => $this->links['docs_support_faq'],
            ],
        ]);
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
            'wc_mercadopago_pse_page',
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/checkouts/pse/mp-pse-page', '.js')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_pse_elements',
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/checkouts/pse/mp-pse-elements', '.js')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_pse_checkout',
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/checkouts/pse/mp-pse-checkout', '.js'),
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

        return ['test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
            'test_mode_description'            => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'               => $this->links['docs_integration_test'],
            'input_document_label'             => $this->storeTranslations['input_document_label'],
            'input_document_helper'            => $this->storeTranslations['input_document_helper'],
            'pse_text_label'                   => $this->storeTranslations['pse_text_label'],
            'input_table_button'               => $this->storeTranslations['input_table_button'],
            'amount'                           => $this->getAmount(),
            'site_id'                          => $this->mercadopago->sellerConfig->getSiteId(),
            'payer_email'                      => esc_js($loggedUserEmail),
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'currency_ratio'                   => $this->mercadopago->helpers->currency->getRatio($this),
            'woocommerce_currency'             => get_woocommerce_currency(),
            'account_currency'                 => $this->mercadopago->helpers->country->getCountryConfigs(),
            'financial_institutions'           => json_encode($this->getFinancialInstitutions()),
            'person_type_label'                => $this->storeTranslations['person_type_label'],
            'financial_institutions_label'     => $this->storeTranslations['financial_institutions_label'],
            'financial_institutions_helper'    => $this->storeTranslations['financial_institutions_helper'],
            'financial_placeholder'            => $this->storeTranslations['financial_placeholder'],
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
        $order    = wc_get_order($order_id);
        try {
            parent::process_payment($order_id);

            $checkout = Form::sanitizeFromData($_POST['mercadopago_pse']);

            if (isset($_POST['mercadopago_pse'])) {
                $checkout = Form::sanitizeFromData($_POST['mercadopago_pse']);
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
            } else {
                // Blocks data arrives in a different way
                $checkout = $this->processBlocksCheckoutData('mercadopago_pse', Form::sanitizeFromData($_POST));
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
            }
            $this->validateRulesPse($checkout);
            $this->transaction = new PseTransaction($this, $order, $checkout);

            $response = $this->transaction->createPayment();

            if (is_array($response) && array_key_exists('status', $response)) {
                $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, [$response['id']]);
                $this->handleWithRejectPayment($response);
                if (
                    $response['status'] === 'pending' &&
                    (
                        $response['status_detail'] === 'pending_waiting_payment' ||
                        $response['status_detail'] ===  'pending_waiting_transfer' )
                ) {
                    $this->mercadopago->woocommerce->cart->empty_cart();

                    if ($this->mercadopago->hooks->options->getGatewayOption($this, 'stock_reduce_mode', 'no') === 'yes') {
                            wc_reduce_stock_levels($order_id);
                    }
                    $this->mercadopago->hooks->order->addOrderNote($order, $this->storeTranslations['customer_not_paid']);
                    return [
                            'result'   => 'success',
                            'redirect' => $response['transaction_details']['external_resource_url'],
                        ];
                }
                return $this->processReturnFail(
                    new ResponseStatusException('exception : Invalid status or status_detail on ' . __METHOD__),
                    $this->mercadopago->storeTranslations->commonMessages['cho_form_error'],
                    self::LOG_SOURCE,
                    (array)$response
                );
            }
            throw new InvalidCheckoutDataException('exception : Unable to process payment on ' . __METHOD__);
        } catch (\Exception $e) {
            return $this->processReturnFail(
                $e,
                $e->getMessage(),
                self::LOG_SOURCE,
                (array)$order,
                true
            );
        }
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

        $siteId  = $mercadopago->sellerConfig->getSiteId();
        $country = $mercadopago->helpers->country->getWoocommerceDefaultCountry();

        if ($siteId === 'MCO' || ($siteId === '' && $country === 'CO')) {
            return true;
        }

        return false;
    }

    public function validateRulesPse($checkout)
    {
        // Rules for pse MCO
        if (
            ($checkout['site_id'] === 'MCO' && (
                (empty($checkout['doc_number']) || !isset($checkout['doc_number']))
                || (empty($checkout['doc_type']) || !isset($checkout['doc_type']))
                || (empty($checkout['person_type']) || !isset($checkout['person_type']))
                || (empty($checkout['bank']) || !isset($checkout['bank']))
                || (strcmp($checkout['person_type'], 'individual') != 0  && strcmp($checkout['person_type'], 'association') != 0 )
            ))
        ) {
            return $this->processReturnFail(
                new \Exception('Unable to process payment on ' . __METHOD__),
                $this->mercadopago->storeTranslations->commonMessages['cho_form_error'],
                self::LOG_SOURCE,
                (array)$checkout,
                true
            );
        }
    }
}
