<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Transactions\TicketTransaction;

if (!defined('ABSPATH')) {
    exit;
}

class TicketGateway extends AbstractGateway
{
    /**
     * ID
     *
     * @const
     */
    public const ID = 'woo-mercado-pago-ticket';

    /**
     * @const
     */
    public const CHECKOUT_NAME = 'checkout-ticket';

    /**
     * @const
     */
    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Ticket_Gateway';

    /**
     * @const
     */
    public const LOG_SOURCE = 'MercadoPago_TicketGateway';

    /**
     * TicketGateway constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->ticketGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->ticketCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->getCheckoutIcon();
        $this->iconAdmin = $this->getCheckoutIcon(true);
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
        $this->mercadopago->hooks->gateway->registerThankYouPage($this->id, [$this, 'renderThankYouPage']);

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
            'type_payments'   => $this->generateExPaymentsFields(),
            'date_expiration' => [
                'title'       => $this->adminTranslations['date_expiration_title'],
                'type'        => 'number',
                'description' => $this->adminTranslations['date_expiration_description'],
                'default'     => MP_TICKET_DATE_EXPIRATION,
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
            'wc_mercadopago_ticket_page',
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/checkouts/ticket/mp-ticket-page', '.js')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_ticket_elements',
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/checkouts/ticket/mp-ticket-elements', '.js')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_ticket_checkout',
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/checkouts/ticket/mp-ticket-checkout', '.js'),
            [
                'site_id' => $this->countryConfigs['site_id'],
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
            'public/checkouts/ticket-checkout.php',
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
        $address         = $this->mercadopago->helpers->currentUser->getCurrentUserMeta('billing_address_1', true);
        $address2        = $this->mercadopago->helpers->currentUser->getCurrentUserMeta('billing_address_2', true);
        $address        .= (!empty($address2) ? ' - ' . $address2 : '');
        $country         = $this->mercadopago->helpers->currentUser->getCurrentUserMeta('billing_country', true);
        $address        .= (!empty($country) ? ' - ' . $country : '');

        return [
            'test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
            'test_mode_description'            => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'               => $this->links['docs_integration_test'],
            'input_document_label'             => $this->storeTranslations['input_document_label'],
            'input_document_helper'            => $this->storeTranslations['input_document_helper'],
            'ticket_text_label'                => $this->storeTranslations['ticket_text_label'],
            'input_table_button'               => $this->storeTranslations['input_table_button'],
            'input_helper_label'               => $this->storeTranslations['input_helper_label'],
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'amount'                           => $this->getAmount(),
            'payment_methods'                  => $this->getPaymentMethods(),
            'site_id'                          => $this->mercadopago->sellerConfig->getSiteId(),
            'payer_email'                      => esc_js($loggedUserEmail),
            'currency_ratio'                   => $this->mercadopago->helpers->currency->getRatio($this),
            'woocommerce_currency'             => get_woocommerce_currency(),
            'account_currency'                 => $this->mercadopago->helpers->country->getCountryConfigs(),
            'febraban'                         => $this->getFebrabanInfo($currentUser, $address),
            'fee_title'                        => $this->getFeeTitle(),
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
        $checkout = [];

        try {
            parent::process_payment($order_id);

            if (isset($_POST['mercadopago_ticket'])) {
                $checkout = Form::sanitizeFromData($_POST['mercadopago_ticket']);
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
            } else {
                // Blocks data arrives in a different way
                $checkout = $this->processBlocksCheckoutData('mercadopago_ticket', Form::sanitizeFromData($_POST));
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
            }

            if (
                !empty($checkout['amount']) &&
                !empty($checkout['payment_method_id'])
            ) {
                $siteIdRulesErrors = $this->validateRulesForSiteId($checkout);

                if ($siteIdRulesErrors !== null) {
                    return $siteIdRulesErrors;
                }

                $this->transaction = new TicketTransaction($this, $order, $checkout);
                $response          = $this->transaction->createPayment();

                if (is_array($response) && array_key_exists('status', $response)) {
                    $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, [$response['id']]);

                    if (
                        $response['status'] === 'pending' && (
                        $response['status_detail'] === 'pending_waiting_payment' ||
                        $response['status_detail'] ===  'pending_waiting_transfer'
                        )
                    ) {
                        $this->mercadopago->helpers->cart->emptyCart();

                        if ($this->mercadopago->hooks->options->getGatewayOption($this, 'stock_reduce_mode', 'no') === 'yes') {
                            wc_reduce_stock_levels($order_id);
                        }

                        $this->mercadopago->hooks->order->setTicketMetadata($order, $response);
                        $this->mercadopago->hooks->order->addOrderNote($order, $this->storeTranslations['customer_not_paid']);

                        if ($response['payment_type_id'] !== 'bank_transfer') {
                            $description = sprintf(
                                "Mercado Pago: %s <a target='_blank' href='%s'>%s</a>",
                                $this->storeTranslations['congrats_title'],
                                $response['transaction_details']['external_resource_url'],
                                $this->storeTranslations['congrats_subtitle']
                            );

                            $this->mercadopago->hooks->order->addOrderNote($order, $description, 1);
                        }

                        $urlReceived = $order->get_checkout_order_received_url();

                        return [
                            'result'   => 'success',
                            'redirect' => $urlReceived,
                        ];
                    }

                    return $this->processReturnFail(
                        new \Exception('Invalid status or status_detail on ' . __METHOD__),
                        $this->mercadopago->storeTranslations->commonMessages['cho_form_error'],
                        self::LOG_SOURCE,
                        $response
                    );
                }
            }
            throw new \Exception('Unable to process payment on ' . __METHOD__);
        } catch (\Exception $e) {
            return $this->processReturnFail(
                $e,
                $this->mercadopago->storeTranslations->commonMessages['cho_default_error'],
                self::LOG_SOURCE,
                (array) $order,
                true
            );
        }
    }

    /**
     * Mount payment_methods field
     *
     * @return array
     */
    private function generateExPaymentsFields(): array
    {
        $paymentMethods = $this->mercadopago->sellerConfig->getCheckoutTicketPaymentMethods();

        $payment_list = [
            'type'                 => 'mp_checkbox_list',
            'title'                => $this->adminTranslations['type_payments_title'],
            'description'          => $this->adminTranslations['type_payments_description'],
            'desc_tip'             => $this->adminTranslations['type_payments_desctip'],
            'payment_method_types' => [
                'ticket'           => [
                    'label'        => $this->adminTranslations['type_payments_label'],
                    'list'         => [],
                ],
            ],
        ];

        foreach ($paymentMethods as $paymentMethod) {
            $payment_list['payment_method_types']['ticket']['list'][] = [
                'id'        => $paymentMethod['id'],
                'type'      => 'checkbox',
                'field_key' => $this->get_field_key($paymentMethod['id']),
                'value'     => $this->mercadopago->hooks->options->getGatewayOption($this, $paymentMethod['id'], 'yes'),
                'label'     => array_key_exists('payment_places', $paymentMethod)
                    ? $paymentMethod['name'] . ' (' . $this->buildPaycashPaymentString() . ')'
                    : $paymentMethod['name'],
            ];
        }

        return $payment_list;
    }

    /**
     * Build Paycash Payments String
     *
     * @return string
     */
    public function buildPaycashPaymentString(): string
    {
        $getPaymentMethodsTicket = $this->mercadopago->sellerConfig->getCheckoutTicketPaymentMethods();

        foreach ($getPaymentMethodsTicket as $payment) {
            if ('paycash' === $payment['id']) {
                $payments = array_column($payment['payment_places'], 'name');
            }
        }

        $lastElement     = array_pop($payments);
        $paycashPayments = implode(', ', $payments);

        return implode($this->storeTranslations['paycash_concatenator'], [$paycashPayments, $lastElement]);
    }

    /**
     * Get Mercado Pago Icon
     *
     * @return string
     */
    private function getCheckoutIcon(bool $adminVersion = false): string
    {
        $siteId   = strtoupper($this->mercadopago->sellerConfig->getSiteId());
        $iconName = ($siteId === 'MLB') ? 'icon-ticket-mlb' : 'icon-ticket';

        return $this->mercadopago->hooks->gateway->getGatewayIcon($iconName . ($adminVersion ? '-admin' : ''));
    }

    /**
     * Get payment methods
     *
     * @return array
     */
    private function getPaymentMethods(): array
    {
        $activePaymentMethods = [];
        $ticketPaymentMethods = $this->mercadopago->sellerConfig->getCheckoutTicketPaymentMethods();

        if (!empty($ticketPaymentMethods)) {
            foreach ($ticketPaymentMethods as $ticketPaymentMethod) {
                if (
                    !isset($this->settings[$ticketPaymentMethod['id']]) ||
                    'yes' === $this->settings[$ticketPaymentMethod['id']]
                ) {
                    $activePaymentMethods[] = $ticketPaymentMethod;
                }
            }
        }

        sort($activePaymentMethods);

        return $this->mercadopago->helpers->paymentMethods->treatTicketPaymentMethods($activePaymentMethods);
    }

    /**
     * Get Febraban info
     *
     * @param \WP_User $currentUser
     * @param string $address
     *
     * @return array
     */
    public function getFebrabanInfo(\WP_User $currentUser, string $address): array
    {
        if ($currentUser->ID != 0) {
            return [
                'firstname' => esc_js($currentUser->user_firstname),
                'lastname'  => esc_js($currentUser->user_lastname),
                'address'   => esc_js($address),
                'city'      => esc_js($this->mercadopago->helpers->currentUser->getCurrentUserMeta('billing_city', true)),
                'state'     => esc_js($this->mercadopago->helpers->currentUser->getCurrentUserMeta('billing_state', true)),
                'zipcode'   => esc_js($this->mercadopago->helpers->currentUser->getCurrentUserMeta('billing_postcode', true)),
                'docNumber' => '',
                'number'    => '',
            ];
        }

        return [
            'firstname' => '',
            'lastname'  => '',
            'address'   => '',
            'city'      => '',
            'state'     => '',
            'zipcode'   => '',
            'docNumber' => '',
            'number'    => '',
        ];
    }

    /**
     * Validate POST data and return the errors found.
     * Returns null if there is no errors.
     *
     * @param $checkout
     *
     * @return ?array
     */
    public function validateRulesForSiteId($checkout)
    {
        // Rules for ticket MLB
        if ($checkout['site_id'] === 'MLB' && empty($checkout['doc_number'])) {
            return $this->processReturnFail(
                new \Exception('Document is required on ' . __METHOD__),
                $this->mercadopago->storeTranslations->commonMessages['cho_form_error'],
                self::LOG_SOURCE
            );
        }

        // Rules for effective MLU
        if ($checkout['site_id'] === 'MLU' && (empty($checkout['doc_number']) || empty($checkout['doc_type']))) {
            return $this->processReturnFail(
                new \Exception('Document is required on ' . __METHOD__),
                $this->mercadopago->storeTranslations->commonMessages['cho_form_error'],
                self::LOG_SOURCE
            );
        }

        return null;
    }

    /**
     * Render thank you page
     *
     * @param $order_id
     */
    public function renderThankYouPage($order_id): void
    {
        $order        = wc_get_order($order_id);
        $transactionDetails  =  $this->mercadopago->orderMetadata->getTicketTransactionDetailsMeta($order);

        if (empty($transactionDetails)) {
            return;
        }

        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/order/ticket-order-received.php',
            [
                'print_ticket_label'  => $this->storeTranslations['print_ticket_label'],
                'print_ticket_link'   => $this->storeTranslations['print_ticket_link'],
                'transaction_details' => $transactionDetails,
            ]
        );
    }
}
