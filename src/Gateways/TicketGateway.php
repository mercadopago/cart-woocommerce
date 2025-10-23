<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Transactions\TicketTransaction;

if (!defined('ABSPATH')) {
    exit;
}

class TicketGateway extends AbstractGateway
{
    protected const CHECKOUT_EXPIRATION_DATE_OPTION = 'date_expiration';
    protected const CHECKOUT_EXPIRATION_DATE_DEFAULT = '3';

    /**
     * ID
     *
     * @const
     */
    public const ID = 'woo-mercado-pago-ticket';

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
     * @throws Exception
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

    public function getCheckoutName(): string
    {
        return 'checkout-ticket';
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
            'type_payments'   => $this->generateExPaymentsFields(),
            static::CHECKOUT_EXPIRATION_DATE_OPTION => [
                'title'       => $this->adminTranslations['date_expiration_title'],
                'type'        => 'number',
                'description' => $this->adminTranslations['date_expiration_description'],
                'default'     => static::CHECKOUT_EXPIRATION_DATE_DEFAULT,
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
            'wc_mercadopago_ticket_page',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/ticket/mp-ticket-page')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_ticket_elements',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/ticket/mp-ticket-elements')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_ticket_checkout',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/ticket/mp-ticket-checkout'),
            [
                'site_id' => $this->countryConfigs['site_id'],
                'error_messages' => $this->getPaymentFieldsErrorMessages(),
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
     *
     * @codeCoverageIgnore
     */
    public function getPaymentFieldsParams(): array
    {
        $amountAndCurrencyRatio = $this->getAmountAndCurrency();
        return [
            'test_mode'                               => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                         => $this->storeTranslations['test_mode_title'],
            'test_mode_description'                   => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'                     => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'                      => $this->links['docs_integration_test'],
            'input_document_label'                    => $this->storeTranslations['input_document_label'],
            'input_document_helper_empty'             => $this->storeTranslations['input_document_helper_empty'],
            'input_document_helper_invalid'           => $this->storeTranslations['input_document_helper_invalid'],
            'input_document_helper_wrong'             => $this->storeTranslations['input_document_helper_wrong'],
            'ticket_text_label'                       => $this->storeTranslations['ticket_text_label'],
            'input_table_button'                      => $this->storeTranslations['input_table_button'],
            'input_helper_label'                      => $this->storeTranslations['input_helper_label'],
            'terms_and_conditions_description'        => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'          => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'           => $this->links['mercadopago_terms_and_conditions'],
            'payment_methods'                         => $this->getPaymentMethods(),
            'mlb_states'                              => $this->getMLBStates(),
            'site_id'                                 => $this->mercadopago->sellerConfig->getSiteId(),
            'amount'                                  => $amountAndCurrencyRatio['amount'],
            'currency_ratio'                          => $amountAndCurrencyRatio['currencyRatio'],
            'message_error_amount'                    => $this->storeTranslations['message_error_amount'],
            'icon'                                    => $this->iconAdmin,
            'billing_data_title'                      => $this->storeTranslations['billing_data_title'],
            'billing_data_checkbox_label'             => $this->storeTranslations['billing_data_checkbox_label'],
            'billing_data_postalcode_label'           => $this->storeTranslations['billing_data_postalcode_label'],
            'billing_data_postalcode_placeholder'     => $this->storeTranslations['billing_data_postalcode_placeholder'],
            'billing_data_postalcode_error_empty'     => $this->storeTranslations['billing_data_postalcode_error_empty'],
            'billing_data_postalcode_error_partial'   => $this->storeTranslations['billing_data_postalcode_error_partial'],
            'billing_data_postalcode_error_invalid'   => $this->storeTranslations['billing_data_postalcode_error_invalid'],
            'billing_data_state_label'                => $this->storeTranslations['billing_data_state_label'],
            'billing_data_state_placeholder'          => $this->storeTranslations['billing_data_state_placeholder'],
            'billing_data_state_error_unselected'     => $this->storeTranslations['billing_data_state_error_unselected'],
            'billing_data_city_label'                 => $this->storeTranslations['billing_data_city_label'],
            'billing_data_city_placeholder'           => $this->storeTranslations['billing_data_city_placeholder'],
            'billing_data_city_error_empty'           => $this->storeTranslations['billing_data_city_error_empty'],
            'billing_data_city_error_invalid'         => $this->storeTranslations['billing_data_city_error_invalid'],
            'billing_data_neighborhood_label'         => $this->storeTranslations['billing_data_neighborhood_label'],
            'billing_data_neighborhood_placeholder'   => $this->storeTranslations['billing_data_neighborhood_placeholder'],
            'billing_data_neighborhood_error_empty'   => $this->storeTranslations['billing_data_neighborhood_error_empty'],
            'billing_data_neighborhood_error_invalid' => $this->storeTranslations['billing_data_neighborhood_error_invalid'],
            'billing_data_address_label'              => $this->storeTranslations['billing_data_address_label'],
            'billing_data_address_placeholder'        => $this->storeTranslations['billing_data_address_placeholder'],
            'billing_data_address_error_empty'        => $this->storeTranslations['billing_data_address_error_empty'],
            'billing_data_address_error_invalid'      => $this->storeTranslations['billing_data_address_error_invalid'],
            'billing_data_address_comp_label'         => $this->storeTranslations['billing_data_address_comp_label'],
            'billing_data_address_comp_placeholder'   => $this->storeTranslations['billing_data_address_comp_placeholder'],
            'billing_data_number_label'               => $this->storeTranslations['billing_data_number_label'],
            'billing_data_number_placeholder'         => $this->storeTranslations['billing_data_number_placeholder'],
            'billing_data_number_toggle_label'        => $this->storeTranslations['billing_data_number_toggle_label'],
            'billing_data_number_error_empty'         => $this->storeTranslations['billing_data_number_error_empty'],
            'billing_data_number_error_invalid'       => $this->storeTranslations['billing_data_number_error_invalid'],
        ];
    }

    /**
     * Get Payment Fields error messages
     *
     * @return array
     */
    public function getPaymentFieldsErrorMessages(): array
    {
        return [
            'postalcode_error_empty'     => $this->storeTranslations['billing_data_postalcode_error_empty'],
            'postalcode_error_partial'   => $this->storeTranslations['billing_data_postalcode_error_partial'],
            'postalcode_error_invalid'   => $this->storeTranslations['billing_data_postalcode_error_invalid'],
            'state_error_unselected'     => $this->storeTranslations['billing_data_state_error_unselected'],
            'city_error_empty'           => $this->storeTranslations['billing_data_city_error_empty'],
            'city_error_invalid'         => $this->storeTranslations['billing_data_city_error_invalid'],
            'neighborhood_error_empty'   => $this->storeTranslations['billing_data_neighborhood_error_empty'],
            'neighborhood_error_invalid' => $this->storeTranslations['billing_data_neighborhood_error_invalid'],
            'address_error_empty'        => $this->storeTranslations['billing_data_address_error_empty'],
            'address_error_invalid'      => $this->storeTranslations['billing_data_address_error_invalid'],
            'number_error_empty'         => $this->storeTranslations['billing_data_number_error_empty'],
            'number_error_invalid'       => $this->storeTranslations['billing_data_number_error_invalid'],
        ];
    }

    public function proccessPaymentInternal($order): array
    {
        $checkout = $this->getCheckoutMercadopagoTicket($order);

        if (
            !Arrays::anyEmpty($checkout, [
                'amount',
                'payment_method_id',
            ])
        ) {
            $invalidCheckoutError = $this->validateCheckout($checkout);

            if ($invalidCheckoutError) {
                return $invalidCheckoutError;
            }

            $this->transaction = new TicketTransaction($this, $order, $checkout);
            $response          = $this->transaction->createPayment();

            if (is_array($response) && array_key_exists('status', $response)) {
                return $this->verifyTicketPaymentResponse($response, $order, $order->get_id());
            }
        }
        throw new InvalidCheckoutDataException('exception : Unable to process payment on ' . __METHOD__);
    }

    /**
     * Get checkout mercadopago ticket
     *
     * @param $order
     *
     * @return array
     */
    private function getCheckoutMercadopagoTicket($order): array
    {
        if (isset($_POST['mercadopago_ticket'])) {
            $checkout = Form::sanitizedPostData('mercadopago_ticket');
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
        } else {
            $checkout = $this->processBlocksCheckoutData('mercadopago_ticket', Form::sanitizedPostData());
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
        }

        return $checkout;
    }

    /**
     * Verify and returns response for ticket payment
     *
     * @param $response
     * @param $order
     * @param $order_id
     *
     * @return array
     * @throws RejectedPaymentException
     */
    private function verifyTicketPaymentResponse($response, $order, $order_id): array
    {
        $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, ['id' => $response['id']]);

        $this->handleWithRejectPayment($response);

        if (
            $response['status'] === 'pending' &&
            in_array($response['status_detail'], ['pending_waiting_payment', 'pending_waiting_transfer'])
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
            new ResponseStatusException('exception : Invalid status or status_detail on ' . __METHOD__),
            'buyer_default',
            self::LOG_SOURCE,
            $response
        );
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
     * @param bool $adminVersion
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
     * Validate POST data and return the errors found.
     * Returns null if there is no errors.
     */
    private function validateCheckout($checkout): ?array
    {
        // Rules for ticket MLB
        if ($checkout['site_id'] === 'MLB' && empty($checkout['doc_number'])) {
            return $this->processReturnFail(
                new Exception('Document is required on ' . __METHOD__),
                'cho_form_error',
                self::LOG_SOURCE
            );
        }

        // Rules for effective MLU
        if ($checkout['site_id'] === 'MLU' && Arrays::anyEmpty($checkout, ['doc_number', 'doc_type'])) {
            return $this->processReturnFail(
                new Exception('Document is required on ' . __METHOD__),
                'cho_form_error',
                self::LOG_SOURCE
            );
        }

        return null;
    }

    public function getMLBStates(): array
    {
        return [
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espirito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MS' => 'Mato Grosso do Sul',
            'MT' => 'Mato Grosso',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins',
        ];
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

    /**
     * Verify if the gateway is available by checking if there are any payment methods registered
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $mercadopago;

        $paymentMethods = $mercadopago->sellerConfig->getCheckoutTicketPaymentMethods();
        $isAvailable = !empty($paymentMethods);
        return $isAvailable;
    }

    /**
     * Gets expiration date option
     */
    public function getCheckoutExpirationDate(): string
    {
        return $this->get_option(static::CHECKOUT_EXPIRATION_DATE_OPTION, static::CHECKOUT_EXPIRATION_DATE_DEFAULT);
    }
}
