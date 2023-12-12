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
    const ID = 'woo-mercado-pago-ticket';

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

        $this->id    = self::ID;
        $this->icon  = $this->getCheckoutIcon();
        $this->title = $this->mercadopago->store->getGatewayTitle($this, $this->adminTranslations['gateway_title']);

        $this->init_settings();
        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['method_title'];
        $this->method_description = $this->description;
        $this->discount           = $this->getActionableValue('gateway_discount', 0);
        $this->commission         = $this->getActionableValue('commission', 0);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->gateway->registerGatewayTitle($this);
        $this->mercadopago->gateway->registerThankYouPage($this->id, [$this, 'renderThankYouPage']);

        $this->mercadopago->currency->handleCurrencyNotices($this);
        $this->mercadopago->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);

        $this->mercadopago->order->registerAdminOrderTotalsAfterTotal([$this, 'registerCommissionAndDiscountOnAdminOrder']);
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
        if($this->addMissingCredentialsNoticeAsFormField()){
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
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $currentUser     = $this->mercadopago->currentUser->getCurrentUser();
        $loggedUserEmail = ($currentUser->ID != 0) ? $currentUser->user_email : null;
        $address         = $this->mercadopago->currentUser->getCurrentUserMeta('billing_address_1', true);
        $address2        = $this->mercadopago->currentUser->getCurrentUserMeta('billing_address_2', true);
        $address        .= (!empty($address2) ? ' - ' . $address2 : '');
        $country         = $this->mercadopago->currentUser->getCurrentUserMeta('billing_country', true);
        $address        .= (!empty($country) ? ' - ' . $country : '');

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/checkouts/ticket-checkout.php',
            [
                'test_mode'                        => $this->mercadopago->store->isTestMode(),
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
                'site_id'                          => $this->mercadopago->seller->getSiteId(),
                'payer_email'                      => esc_js($loggedUserEmail),
                'currency_ratio'                   => $this->mercadopago->currency->getRatio($this),
                'woocommerce_currency'             => get_woocommerce_currency(),
                'account_currency'                 => $this->mercadopago->country->getCountryConfigs(),
                'febraban'                         => $this->getFebrabanInfo($currentUser, $address),
            ]
        );
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

            $checkout = Form::sanitizeFromData($_POST['mercadopago_ticket']);

                                                                                                                                                                                                                                                                                                        if ( !empty($checkout['paymentMethodId'])) {
                $this->validateRulesForSiteId($checkout);
                $this->transaction = new TicketTransaction($this, $order, $checkout);
                $response          = $this->transaction->createPayment();

                if (is_array($response) && array_key_exists('status', $response)) {
                    $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, [$response['id']]);

                    if ($response['status'] === 'pending' && (
                        $response['status_detail'] === 'pending_waiting_payment' ||
                        $response['status_detail'] ===  'pending_waiting_transfer'
                    )) {
                        $this->mercadopago->woocommerce->cart->empty_cart();

                        if ($this->mercadopago->options->getGatewayOption($this, 'stock_reduce_mode', 'no') === 'yes') {
                            wc_reduce_stock_levels($order_id);
                        }

                        $this->mercadopago->order->setTicketMetadata($order, $response);
                        $this->mercadopago->order->addOrderNote($order, $this->storeTranslations['customer_not_paid']);

                        if ($response['payment_type_id'] !== 'bank_transfer') {
                            $description = sprintf(
                                "Mercado Pago: %s <a target='_blank' href='%s'>%s</a>",
                                $this->storeTranslations['congrats_title'],
                                $response['transaction_details']['external_resource_url'],
                                $this->storeTranslations['congrats_subtitle']
                            );

                            $this->mercadopago->order->addOrderNote($order, $description, 1);
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
                        (array) $response
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
        $paymentMethods = $this->mercadopago->seller->getCheckoutTicketPaymentMethods();

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
                'value'     => $this->mercadopago->options->getGatewayOption($this, $paymentMethod['id'], 'yes'),
                'label'     => array_key_exists('payment_places', $paymentMethod)
                    ? $paymentMethod['name'] . ' (' .$this->buildPaycashPaymentString(). ')'
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
        $getPaymentMethodsTicket = $this->mercadopago->seller->getCheckoutTicketPaymentMethods();

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
    private function getCheckoutIcon(): string
    {
        $siteId   = strtoupper($this->mercadopago->seller->getSiteId());
        $iconName = ($siteId === 'MLB') ? 'icon-ticket-mlb' : 'icon-ticket';

        return $this->mercadopago->gateway->getGatewayIcon($iconName);
    }

    /**
     * Get payment methods
     *
     * @return array
     */
    private function getPaymentMethods(): array
    {
        $activePaymentMethods = [];
        $ticketPaymentMethods = $this->mercadopago->seller->getCheckoutTicketPaymentMethods();

        if (! empty($ticketPaymentMethods)) {
            foreach ($ticketPaymentMethods as $ticketPaymentMethod) {
                if (!isset($this->settings[$ticketPaymentMethod['id']]) ||
                    'yes' === $this->settings[$ticketPaymentMethod['id']]
                ) {
                    $activePaymentMethods[] = $ticketPaymentMethod;
                }
            }
        }

        sort($activePaymentMethods);

        return $this->mercadopago->paymentMethods->treatTicketPaymentMethods($activePaymentMethods);
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
                'city'      => esc_js($this->mercadopago->currentUser->getCurrentUserMeta('billing_city', true)),
                'state'     => esc_js($this->mercadopago->currentUser->getCurrentUserMeta('billing_state', true)),
                'zipcode'   => esc_js($this->mercadopago->currentUser->getCurrentUserMeta('billing_postcode', true)),
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

    public function validateRulesForSiteId($checkout) {

        if ( // Rules for ticket MLB
            ($checkout['site_id'] === 'MLB' && (empty($checkout['docNumber']) || !isset($checkout['docNumber']) ))
        ) {
            return $this->processReturnFail(
                new \Exception('Document is required on ' . __METHOD__),
                $this->mercadopago->storeTranslations->commonMessages['cho_form_error'],
                self::LOG_SOURCE
            );
        }

        if ( // Rules for efective MLU
            ($checkout['site_id'] === 'MLU' && (
                (empty($checkout['docNumber']) || !isset($checkout['docNumber']))
                || (empty($checkout['docType']) || !isset($checkout['docType']))
                ))
        ) {
            return $this->processReturnFail(
                new \Exception('Document is required on ' . __METHOD__),
                $this->mercadopago->storeTranslations->commonMessages['cho_form_error'],
                self::LOG_SOURCE
            );
        }
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

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/order/ticket-order-received.php',
            [
                'print_ticket_label'  => $this->storeTranslations['print_ticket_label'],
                'print_ticket_link'   => $this->storeTranslations['print_ticket_link'],
                'transaction_details' => $transactionDetails,
            ]
        );
    }

    /**
     * Register commission and discount on admin order totals
     *
     * @param int $orderId
     *
     * @return void
     */
    public function registerCommissionAndDiscountOnAdminOrder(int $orderId): void
    {
        parent::registerCommissionAndDiscount($this, $orderId);
    }
}
