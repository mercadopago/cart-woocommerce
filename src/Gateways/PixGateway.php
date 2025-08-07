<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Transactions\PixTransaction;
use MercadoPago\Woocommerce\Hooks\OrderMeta;

if (!defined('ABSPATH')) {
    exit;
}

class PixGateway extends AbstractGateway
{
    /**
     * @var OrderMeta
     */
    public $orderMeta;

    /**
     * @const
     */
    public const ID = 'woo-mercado-pago-pix';

    /**
     * @const
     */
    public const CHECKOUT_NAME = 'checkout-pix';

    /**
     * @const
     */
    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Pix_Gateway';

    /**
     * @const
     */
    public const LOG_SOURCE = 'MercadoPago_PixGateway';

    /**
     * @const
     */
    public const PIX_IMAGE_ENDPOINT = 'mp_pix_image';

    /**
     * @const
     */
    public const PIX_PAYMENT_STATUS_ENDPOINT = 'mp_pix_payment_status';

    /**
     * @const
     */
    public const PIX_STATUS_NONCE = 'mp_pix_polling_nonce';

    /**
     * PixGateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->pixGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->pixCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pix');
        $this->iconAdmin = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pix-admin');
        $this->title     = $this->mercadopago->storeConfig->getGatewayTitle($this, $this->adminTranslations['gateway_title']);

        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];
        $this->discount           = $this->getActionableValue('gateway_discount', 0);
        $this->commission         = $this->getActionableValue('commission', 0);
        $this->expirationDate     = (int) $this->mercadopago->storeConfig->getCheckoutDateExpirationPix($this, '1');

        $this->mercadopago->hooks->gateway->registerUpdateOptions($this);
        $this->mercadopago->hooks->gateway->registerGatewayTitle($this);
        $this->mercadopago->hooks->gateway->registerThankYouPage($this->id, [$this, 'renderThankYouPage']);

        $this->mercadopago->hooks->order->registerEmailBeforeOrderTable([$this, 'renderOrderReceivedTemplate']);
        $this->mercadopago->hooks->order->registerOrderDetailsAfterOrderTable([$this, 'renderOrderReceivedTemplate']);

        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);
        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::PIX_IMAGE_ENDPOINT, [$this, 'generatePixImage']);
        $this->mercadopago->hooks->endpoints->registerWCAjaxEndpoint(self::PIX_PAYMENT_STATUS_ENDPOINT, [$this, 'checkPixPaymentStatus']);
        $this->mercadopago->hooks->cart->registerCartCalculateFees([$this, 'registerDiscountAndCommissionFeesOnCart']);

        $this->mercadopago->helpers->currency->handleCurrencyNotices($this);

        $this->orderMeta = new OrderMeta();
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

    public function formFields(): array
    {
        return $this->sellerHavePix()
            ? parent::formFields()
            : $this->sellerWithoutPixFields();
    }

    public function formFieldsMainSection(): array
    {
        return $this->sellerWithPixFields();
    }

    public function sellerHavePix(): bool
    {
        return !empty($this->mercadopago->sellerConfig->getCheckoutPixPaymentMethods());
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/checkouts/pix-checkout.php',
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
        $amountAndCurrencyRatio = $this->getAmountAndCurrency();
        return [
            'test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
            'test_mode_description'            => $this->storeTranslations['test_mode_description'],
            'pix_template_title'               => $this->storeTranslations['pix_template_title'],
            'pix_template_subtitle'            => $this->storeTranslations['pix_template_subtitle'],
            'pix_template_alt'                 => $this->storeTranslations['pix_template_alt'],
            'pix_template_src'                 => $this->mercadopago->helpers->url->getImageAsset('checkouts/pix/pix'),
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'amount'                           => $amountAndCurrencyRatio['amount'],
            'message_error_amount'             => $this->storeTranslations['message_error_amount'],
            'icon'                             => $this->icon,
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

            $checkout = Form::sanitizedPostData();

            if (isset($_POST['wc-woo-mercado-pago-pix-new-payment-method'])) {
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
            } else {
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
            }

            if (!filter_var($order->get_billing_email(), FILTER_VALIDATE_EMAIL)) {
                return $this->processReturnFail(
                    new Exception('Email not valid on ' . __METHOD__),
                    $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'],
                    self::LOG_SOURCE,
                    (array) $order
                );
            }

            $this->transaction = new PixTransaction($this, $order, $checkout);
            $response          = $this->transaction->createPayment();

            if (is_array($response) && array_key_exists('status', $response)) {
                return $this->verifyPixPaymentResponse($response, $order);
            }

            throw new ResponseStatusException('exception : Unable to process payment on ' . __METHOD__);
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
     * Verify and returns response for pix payment
     *
     * @param $response
     * @param $order
     *
     * @return array
     * @throws RejectedPaymentException
     * @throws ResponseStatusException
     */
    private function verifyPixPaymentResponse($response, $order): array
    {
        $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, ['id' => $response['id']]);

        $this->handleWithRejectPayment($response);

        if (
            $response['status'] === 'pending' &&
            in_array($response['status_detail'], ['pending_waiting_payment', 'pending_waiting_transfer'])
        ) {
            $this->mercadopago->helpers->cart->emptyCart();
            $this->mercadopago->hooks->order->setPixMetadata($this, $order, $response);
            $this->mercadopago->hooks->order->addOrderNote($order, $this->storeTranslations['customer_not_paid']);

            $urlReceived = $order->get_checkout_order_received_url();

            $description = "
                <div style='text-align: justify;'>
                <p>{$this->storeTranslations['congrats_title']}</p>
                <small>{$this->storeTranslations['congrats_subtitle']}</small>
                </div>
            ";

            $this->mercadopago->hooks->order->addOrderNote($order, $description, 1);

            return [
                'result'   => 'success',
                'redirect' => $urlReceived,
            ];
        }
        throw new ResponseStatusException('exception : Unable to process payment on ' . __METHOD__);
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

        if ($siteId === 'MLB' || ($siteId === '' && $country === 'BR')) {
            return true;
        }

        return false;
    }

    /**
     * Mount fields for seller configure Pix
     *
     * @return array
     */
    private function sellerWithPixFields(): array
    {
        return [
            'expiration_date' => [
                'type'        => 'select',
                'title'       => $this->adminTranslations['expiration_date_title'],
                'description' => $this->adminTranslations['expiration_date_description'],
                'default'     => '30 minutes',
                'options'     => [
                    '15 minutes' => $this->adminTranslations['expiration_date_options_15_minutes'],
                    '30 minutes' => $this->adminTranslations['expiration_date_options_30_minutes'],
                    '60 minutes' => $this->adminTranslations['expiration_date_options_60_minutes'],
                    '12 hours'   => $this->adminTranslations['expiration_date_options_12_hours'],
                    '24 hours'   => $this->adminTranslations['expiration_date_options_24_hours'],
                    '2 days'     => $this->adminTranslations['expiration_date_options_2_days'],
                    '3 days'     => $this->adminTranslations['expiration_date_options_3_days'],
                    '4 days'     => $this->adminTranslations['expiration_date_options_4_days'],
                    '5 days'     => $this->adminTranslations['expiration_date_options_5_days'],
                    '6 days'     => $this->adminTranslations['expiration_date_options_6_days'],
                    '7 days'     => $this->adminTranslations['expiration_date_options_7_days'],
                ]
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
            'card_info_helper' => [
                'type'  => 'title',
                'value' => '',
            ],
            'card_info' => [
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => $this->adminTranslations['card_info_title'],
                    'subtitle'    => $this->adminTranslations['card_info_subtitle'],
                    'button_text' => $this->adminTranslations['card_info_button_text'],
                    'button_url'  => $this->links['mercadopago_pix'],
                    'icon'        => 'mp-icon-badge-info',
                    'color_card'  => 'mp-alert-color-success',
                    'size_card'   => 'mp-card-body-size',
                    'target'      => '_blank',
                ]
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
     * Mount fields to show the seller how to activate Pix
     *
     * @return array
     */
    private function sellerWithoutPixFields(): array
    {
        if ($this->mercadopago->helpers->url->getCurrentSection() == $this->id) {
            $this->mercadopago->helpers->notices->adminNoticeMissPix();
        }

        $stepsContent = $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/settings/steps.php',
            [
                'title'             => $this->adminTranslations['steps_title'],
                'step_one_text'     => $this->adminTranslations['steps_step_one_text'],
                'step_two_text'     => $this->adminTranslations['steps_step_two_text'],
                'step_three_text'   => $this->adminTranslations['steps_step_three_text'],
                'observation_one'   => $this->adminTranslations['steps_observation_one'],
                'observation_two'   => $this->adminTranslations['steps_observation_two'],
                'button_about_pix'  => $this->adminTranslations['steps_button_about_pix'],
                'observation_three' => $this->adminTranslations['steps_observation_three'],
                'link_title_one'    => $this->adminTranslations['steps_link_title_one'],
                'link_url_one'      => $this->links['mercadopago_pix'],
                'link_url_two'      => $this->links['mercadopago_support'],
            ]
        );

        return [
            'header' => [
                'type'        => 'mp_config_title',
                'title'       => $this->adminTranslations['header_title'],
                'description' => $this->adminTranslations['header_description'],
            ],
            'steps_content' => [
                'title' => $stepsContent,
                'type'  => 'title',
                'class' => 'mp_title_checkout',
            ],
        ];
    }

    /**
     * Generate pix image with gd_extension and fallback
     *
     * @return void
     */
    public function generatePixImage(): void
    {
        $orderId = Form::sanitizedGetData('id');
        if (!$orderId) {
            $this->mercadopago->helpers->images->getErrorImage();
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            $this->mercadopago->helpers->images->getErrorImage();
        }

        $qrCodeBase64 = $this->mercadopago->orderMetadata->getPixQrBase64Meta($order);
        if (is_array($qrCodeBase64)) {
            $qrCodeBase64 = array_pop($qrCodeBase64);
        }

        if (!$qrCodeBase64) {
            $this->mercadopago->helpers->images->getErrorImage();
        }

        $this->mercadopago->helpers->images->getBase64Image($qrCodeBase64);
    }

    /**
     * Get pix order received template
     *
     * @param $order
     *
     * @return void
     */
    public function renderOrderReceivedTemplate($order): void
    {
        $pixOn   = (array) $this->mercadopago->orderMetadata->getPixOnMeta($order);
        $pixOn   = (bool) array_pop($pixOn);

        if ($pixOn && $order->get_status() === 'pending') {
            $qrCode = $this->mercadopago->orderMetadata->getPixQrCodeMeta($order);
            $qrCodeBase64 = $this->mercadopago->orderMetadata->getPixQrBase64Meta($order);
            $expirationDate = $this->mercadopago->orderMetadata->getPixExpirationDateData($order);

            if (is_array($qrCode)) {
                $qrCode = array_pop($qrCode);
            }

            if (is_array($qrCodeBase64)) {
                $qrCodeBase64 = array_pop($qrCodeBase64);
            }

            if (is_array($expirationDate)) {
                $expirationDate = array_pop($expirationDate);
            }

            $siteUrl       = $this->mercadopago->hooks->options->get('siteurl');
            $imageEndpoint = self::PIX_IMAGE_ENDPOINT;

            $qrCodeImage = !in_array('gd', get_loaded_extensions(), true)
                ? "data:image/jpeg;base64,$qrCodeBase64"
                : "$siteUrl?wc-api=$imageEndpoint&id={$order->get_id()}";

            $this->mercadopago->hooks->scripts->registerStoreStyle(
                'mp_pix_image',
                $this->mercadopago->helpers->url->getCssAsset('public/mp-pix-image')
            );

            $this->mercadopago->hooks->template->getWoocommerceTemplate(
                'public/order/pix-order-received-image.php',
                [
                    'qr_code'              => $qrCode,
                    'expiration_date'      => $expirationDate,
                    'expiration_date_text' => $this->storeTranslations['expiration_date_text'],
                    'qr_code_image'        => $qrCodeImage,
                ]
            );
        }
    }

    public function registerApprovedPaymentStyles(): void
    {
        $this->mercadopago->hooks->scripts->registerStoreStyle(
            'mp_pix_appproved',
            $this->mercadopago->helpers->url->getCssAsset('public/mp-pix-approved')
        );
    }

    /**
     * Render thank you page
     *
     * @param $order_id
     */
    public function renderThankYouPage($order_id): void
    {
        $order = wc_get_order($order_id);

        $isPixPaymentApproved = $this->orderMeta->get($order, 'pix_payment_approved');

        // to avoid pix steps exibition on page reloads
        if ($isPixPaymentApproved) {
            $this->renderPixPaymentApprovedTemplate();
            return;
        }

        $transactionAmount = (float) $this->mercadopago->orderMetadata->getTransactionAmountMeta($order);
        $transactionAmount = Numbers::format($transactionAmount);

        $defaultValue      = $this->storeTranslations['expiration_30_minutes'];
        $expirationOption  = $this->mercadopago->storeConfig->getCheckoutDateExpirationPix($this, $defaultValue);

        $qrCode       = $this->mercadopago->orderMetadata->getPixQrCodeMeta($order);
        $qrCodeBase64 = $this->mercadopago->orderMetadata->getPixQrBase64Meta($order);

        if (empty($qrCodeBase64) && empty($qrCode)) {
            return;
        }

        $this->mercadopago->hooks->scripts->registerStoreStyle(
            'mp_pix_thankyou',
            $this->mercadopago->helpers->url->getCssAsset('public/mp-pix-thankyou')
        );

        $this->registerPixPoolingScript($order);

        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/order/pix-order-received.php',
            [
                'img_pix'             => $this->mercadopago->helpers->url->getImageAsset('checkouts/pix/pix'),
                'amount'              => Numbers::formatWithCurrencySymbol($this->countryConfigs['currency_symbol'], $transactionAmount),
                'qr_base64'           => $qrCodeBase64,
                'title_purchase_pix'  => $this->storeTranslations['title_purchase_pix'],
                'title_how_to_pay'    => $this->storeTranslations['title_how_to_pay'],
                'step_one'            => $this->storeTranslations['step_one'],
                'step_two'            => $this->storeTranslations['step_two'],
                'step_three'          => $this->storeTranslations['step_three'],
                'step_four'           => $this->storeTranslations['step_four'],
                'text_amount'         => $this->storeTranslations['text_amount'],
                'text_scan_qr'        => $this->storeTranslations['text_scan_qr'],
                'text_time_qr_one'    => $this->storeTranslations['expiration_date_text'],
                'qr_date_expiration'  => $expirationOption,
                'text_description_qr' => $this->storeTranslations['text_description_qr'],
                'qr_code'             => $qrCode,
                'text_button'         => $this->storeTranslations['text_button'],
            ]
        );
    }

    /**
     * Register pooling script
     * @param WC_Order $order
     */
    public function registerPixPoolingScript($order): void
    {
        if ($order->get_status() === 'pending') {
            $scriptUrl = $this->mercadopago->helpers->url->getJsAsset('checkouts/pix/mp-pix-pooling');
            $this->mercadopago->hooks->scripts->registerStoreScript(
                'wc_mercadopago_pix_pooling',
                $scriptUrl,
                [
                    'order_id' => $order->get_id(),
                    'ajax_url' => \WC_AJAX::get_endpoint(self::PIX_PAYMENT_STATUS_ENDPOINT),
                    'nonce' => wp_create_nonce(self::PIX_STATUS_NONCE)
                ]
            );
        }
    }

    /**
     * Render PIX payment approved template
     *
     * @param WC_Order $order
     * @param array $paymentData
     *
     * @return void
     */
    public function renderPixPaymentApprovedTemplate(): void
    {
        $this->registerApprovedPaymentStyles();
        // to do add texts to array
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/order/pix-payment-approved.php',
            [
                'approved_template_title' => $this->storeTranslations['approved_template_title'],
                'approved_template_description' => $this->storeTranslations['approved_template_description'],
            ]
        );
    }

    /**
     * Check PIX payment status via AJAX
     *
     * @return void
     */
    public function checkPixPaymentStatus(): void
    {
        try {
            $this->validateNonce();
            $order = $this->getOrderFromRequest();
            $paymentId = $this->getPaymentIdFromOrder($order);
            $paymentData = $this->fetchPaymentData($paymentId);
            $status = $paymentData['status'] ?? null;

            $this->handlePaymentStatus($status, $paymentData, $order);
        } catch (Exception $e) {
            throw new ResponseStatusException('Unable to check pix payment status: ' . $e->getMessage());
        }
    }

    /**
     * Validate nonce for security
     *
     * @return void
     * @throws Exception
     */
    private function validateNonce(): void
    {
        $nonce = Form::sanitizedPostData('nonce');
        if (!wp_verify_nonce($nonce, self::PIX_STATUS_NONCE)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
            throw new Exception('Invalid nonce');
        }
    }

    /**
     * Get order from request data
     *
     * @return \WC_Order
     * @throws Exception
     */
    private function getOrderFromRequest(): \WC_Order
    {
        $orderId = Form::sanitizedPostData('order_id');
        $order = wc_get_order($orderId);

        if (!$order) {
            wp_send_json_error(['message' => 'Order not found'], 404);
            throw new Exception('Order not found');
        }

        return $order;
    }

    /**
     * Get payment ID from order metadata
     *
     * @param \WC_Order $order
     * @return string
     * @throws Exception
     */
    private function getPaymentIdFromOrder(\WC_Order $order): string
    {
        $paymentIds = $this->mercadopago->orderMetadata->getPaymentsIdMeta($order);

        if (empty($paymentIds)) {
            wp_send_json_error(['message' => 'No payment ID found'], 404);
            throw new Exception('No payment ID found');
        }

        return explode(',', $paymentIds)[0];
    }

    /**
     * Fetch payment data from MercadoPago API
     *
     * @param string $paymentId
     * @return array
     * @throws Exception
     */
    private function fetchPaymentData(string $paymentId): array
    {
        $headers = ['Authorization: Bearer ' . $this->mercadopago->sellerConfig->getCredentialsAccessToken()];
        $response = $this->mercadopago->helpers->requester->get("/v1/payments/$paymentId", $headers);

        if ($response->getStatus() !== 200) {
            wp_send_json_error(['message' => 'Failed to get payment status'], 500);
            throw new Exception('Failed to get payment status');
        }

        $paymentData = $response->getData();

        if (is_object($paymentData)) {
            $paymentData = (array) $paymentData;
        } elseif (!is_array($paymentData)) {
            $paymentData = [];
        }

        return $paymentData;
    }

    /**
     * Handle different payment statuses
     *
     * @param string|null $status
     * @param array $paymentData
     * @param \WC_Order $order
     * @return void
     */
    private function handlePaymentStatus(?string $status, array $paymentData, \WC_Order $order): void
    {
        switch ($status) {
            case 'approved':
                $this->handleApprovedPayment($order, $paymentData);
                break;
            case 'pending':
                $this->handlePendingPayment();
                break;
            default:
                $this->handleOtherStatus($status);
                break;
        }
    }

    /**
     * Handle approved payment status
     *
     * @param \WC_Order $order
     * @param array $paymentData
     * @return void
     */
    private function handleApprovedPayment(\WC_Order $order, array $paymentData): void
    {
        $paymentAlreadyProcessed = $this->orderMeta->get($order, 'pix_payment_approved');

        if ($paymentAlreadyProcessed) {
            wp_send_json_success([
                'status' => 'approved',
                'message' => 'Payment has already been processed',
            ]);
            return;
        }

        $this->mercadopago->orderStatus->processStatus('approved', $paymentData, $order, self::WEBHOOK_API_NAME);
        $this->orderMeta->update($order, 'pix_payment_approved', true);

        wp_send_json_success([
            'status' => 'approved',
            'message' => 'Payment successfully approved',
        ]);
    }

    /**
     * Handle pending payment status
     *
     * @return void
     */
    private function handlePendingPayment(): void
    {
        wp_send_json_success([
            'status' => 'pending',
            'message' => 'Payment pending'
        ]);
    }

    /**
     * Handle other payment statuses
     *
     * @param string|null $status
     * @return void
     */
    private function handleOtherStatus(?string $status): void
    {
        wp_send_json_success([
            'status' => $status,
            'message' => 'Payment status: ' . $status
        ]);
    }
}
