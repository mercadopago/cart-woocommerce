<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Transactions\PixTransaction;

if (!defined('ABSPATH')) {
    exit;
}

class PixGateway extends AbstractGateway
{
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
     * PixGateway constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->pixGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->pixCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pix-blue');
        $this->iconAdmin = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pix-blue-admin');
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

        if (
            !empty($this->mercadopago->storeConfig->getCheckoutCountry()) &&
            !empty($this->mercadopago->sellerConfig->getCredentialsPublicKey()) &&
            !empty($this->mercadopago->sellerConfig->getCredentialsAccessToken())
        ) {
            $paymentMethodPix = $this->mercadopago->sellerConfig->getCheckoutPixPaymentMethods();

            if (empty($paymentMethodPix)) {
                $this->form_fields = array_merge($this->form_fields, $this->sellerWithoutPixFields());
                return;
            }

            $this->form_fields = array_merge($this->form_fields, $this->sellerWithPixFields());
            $this->form_fields = array_merge(
                $this->form_fields,
                [
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
                ]
            );
        }
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
        return [
            'test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
            'test_mode_description'            => $this->storeTranslations['test_mode_description'],
            'pix_template_title'               => $this->storeTranslations['pix_template_title'],
            'pix_template_subtitle'            => $this->storeTranslations['pix_template_subtitle'],
            'pix_template_alt'                 => $this->storeTranslations['pix_template_alt'],
            'pix_template_src'                 => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/pix/pix', '.png', true),
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
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
        $order    = wc_get_order($order_id);
        try {
            parent::process_payment($order_id);
            $checkout = Form::sanitizeFromData($_POST);

            if (isset($_POST['wc-woo-mercado-pago-pix-new-payment-method'])) {
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
            } else {
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
            }

            if (!filter_var($order->get_billing_email(), FILTER_VALIDATE_EMAIL)) {
                return $this->processReturnFail(
                    new \Exception('Email not valid on ' . __METHOD__),
                    $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'],
                    self::LOG_SOURCE,
                    (array) $order
                );
            }

            $this->transaction = new PixTransaction($this, $order, $checkout);
            $response          = $this->transaction->createPayment();

            if (is_array($response) && array_key_exists('status', $response)) {
                $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, [$response['id']]);

                $this->handleWithRejectPayment($response);

                if (
                    $response['status'] === 'pending' && (
                        $response['status_detail'] === 'pending_waiting_payment' ||
                        $response['status_detail'] === 'pending_waiting_transfer'
                    )
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
            }
            throw new ResponseStatusException('exception : Unable to process payment on ' . __METHOD__);
        } catch (\Exception $e) {
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
     * Verify if the gateway is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $mercadopago;

        $siteId  = $mercadopago->sellerConfig->getSiteId();
        $country = $mercadopago->helpers->country->getWoocommerceDefaultCountry();

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
    public function sellerWithPixFields(): array
    {
        return [
            'header' => [
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
                    'enabled'  => $this->adminTranslations['enabled_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['enabled_descriptions_disabled'],
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
            'gateway_discount' => $this->getDiscountField(),
            'commission'       => $this->getCommissionField(),
        ];
    }

    /**
     * Mount fields to show the seller how to activate Pix
     *
     * @return array
     */
    public function sellerWithoutPixFields(): array
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
        $orderId = Form::sanitizeTextFromGet('id');
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
                $this->mercadopago->helpers->url->getPluginFileUrl('assets/css/public/mp-pix-image', '.css')
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

    /**
     * Render thank you page
     *
     * @param $order_id
     */
    public function renderThankYouPage($order_id): void
    {
        $order = wc_get_order($order_id);

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
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/css/public/mp-pix-thankyou', '.css')
        );

        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/order/pix-order-received.php',
            [
                'img_pix'             => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/pix/pix', '.png', true),
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
}
