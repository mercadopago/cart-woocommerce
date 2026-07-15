<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Transactions\CustomTransaction;
use MercadoPago\Woocommerce\Transactions\SupertokenTransaction;
use MercadoPago\Woocommerce\Transactions\WalletButtonTransaction;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\PP\Sdk\Exceptions\ApiException;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Helpers\WebhookUrl;

if (!defined('ABSPATH')) {
    exit;
}

class CustomGateway extends AbstractGateway
{
    public const ID = 'woo-mercado-pago-custom';

    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Custom_Gateway';

    public const LOG_SOURCE = 'MercadoPago_CustomGateway';

    protected const WALLET_BUTTON_ENABLED_OPTION = 'wallet_button';

    protected const WALLET_BUTTON_ENABLED_DEFAULT = 'yes';

    /**
     * @const
     */
    protected const CARD_FLAGS_BY_COUNTRY = [
        'MLA' => [
            'visa',
            'master',
            'amex',
            'naranjax',
            'maestro',
            'cabal',
        ],
        'MLB' => [
            'master',
            'visa',
            'elo',
            'amex',
            'hypercard',
        ],
        'MLM' => [
            'visa',
            'master',
            'amex',
        ],
        'MLC' => [
            'visa',
            'master',
            'amex',
            'redcompra'
        ],
        'MCO' => [
            'visa',
            'master',
            'codensa',
            'amex',
            'diners',
        ],
        'MLU' => [
            'visa',
            'master',
            'oca',
            'amex',
            'lider',
            'diners',
        ],
        'MPE' => [
            'visa',
            'master',
            'amex',
            'diners',
        ],
    ];

    /**
     * CustomGateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        if (!$this->mercadopago->booted()) {
            return;
        }

        $this->adminTranslations = $this->mercadopago->adminTranslations->customGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->customCheckout;

        $this->id = self::ID;
        $this->icon = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-custom');
        $this->iconAdmin = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-custom-admin');

        $gatewayTitle = $this->mercadopago->sellerConfig->getSiteId() === 'MLB' ? $this->adminTranslations['gateway_title_MLB'] : $this->adminTranslations['gateway_title_ALL'];
        $this->title = $this->mercadopago->storeConfig->getGatewayTitle($this, $gatewayTitle);

        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description = $this->adminTranslations['gateway_description'];
        $this->method_title = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];
        $this->discount = (float) $this->getActionableValue('gateway_discount', 0);
        $this->commission = (float) $this->getActionableValue('commission', 0);

        $this->mercadopago->hooks->gateway->registerUpdateOptions($this);
        $this->mercadopago->hooks->gateway->registerGatewayTitle($this);
        $this->mercadopago->hooks->gateway->registerThankYouPage($this->id, [$this, 'renderInstallmentsRateDetails']);

        $this->mercadopago->hooks->order->registerOrderDetailsAfterOrderTable([$this, 'renderInstallmentsRateDetails']);
        $this->mercadopago->hooks->order->registerAdminOrderTotalsAfterTotal([$this, 'registerInstallmentsFeeOnAdminOrder']);

        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);

        // Subscription settings validation must always run — the toggle and token
        // fields exist in the admin regardless of WCS being installed.
        add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, [$this, 'validateSubscriptionsBeforeSave']);

        if (SubscriptionsHelper::isWcsActive()) {
            add_action('admin_notices', [$this, 'displaySubscriptionsValidationNotice']);
            add_action('admin_notices', [$this, 'displayCardChangeInconsistencyNotice']);
            add_action('before_woocommerce_pay', [$this, 'restoreChangePaymentError'], 1);
            add_action('before_woocommerce_pay', [$this, 'restoreChangePaymentSuccess'], 1);
            add_action('wp_enqueue_scripts', [$this, 'overridePublicKeyForSubscriptionCheckout'], 20);
            add_action('wp_enqueue_scripts', [$this, 'dequeueSuperTokenForSubscriptionCheckout'], 20);
        }
        $this->mercadopago->hooks->checkout->registerReceipt($this->id, [$this, 'renderOrderForm']);

        $this->mercadopago->hooks->cart->registerCartCalculateFees([$this, 'registerDiscountAndCommissionFeesOnCart']);

        $this->mercadopago->helpers->currency->handleCurrencyNotices($this);
        $this->paymentMethodName = self::ID;

        $this->initWcsSupports();
    }

    /**
     * Returns true when the current page is a subscription payment context.
     *
     * Covers three entry points (evaluated in priority order):
     *   1. Payment-method change (My Account → Change payment) — WCS reuses the
     *      order-pay endpoint with the subscription ID, so change_payment_method
     *      must be detected first; applying wcs_order_contains_subscription() to
     *      a WC_Subscription returns false and would skip the override otherwise.
     *   2. Order Pay — decision is based on the order being paid, not the cart.
     *      'any' covers renewal orders that failed and land on Order Pay for retry.
     *   3. Standard checkout — decision based on the cart.
     *
     * Shared by overridePublicKeyForSubscriptionCheckout() and
     * dequeueSuperTokenForSubscriptionCheckout() to keep both methods in sync.
     */
    private function isSubscriptionPaymentContext(): bool
    {
        if ($this->mercadopago->helpers->url->validateGetVar('change_payment_method')) {
            return true;
        }

        $orderId = (int) get_query_var('order-pay');
        if ($orderId > 0) {
            $order = wc_get_order($orderId);
            return $order
                && function_exists('wcs_order_contains_subscription')
                && \wcs_order_contains_subscription($order, 'any');
        }

        return class_exists('WC_Subscriptions_Cart')
            && \WC_Subscriptions_Cart::cart_contains_subscription();
    }

    /**
     * Overrides the MP SDK public key in the checkout JS params so the card
     * token is generated with the Pre-approval credential instead of the
     * standard one. Delegates context detection to isSubscriptionPaymentContext().
     *
     * Runs at wp_enqueue_scripts priority 20 (after script registration at
     * priority 10), when WC()->cart is already loaded from session.
     */
    public function overridePublicKeyForSubscriptionCheckout(): void
    {
        if (!$this->isSubscriptionPaymentContext()) {
            return;
        }

        $key = $this->mercadopago->subscriptionsHelper->resolvePublicKey($this->mercadopago->storeConfig);
        if ($key === '') {
            return;
        }

        $escaped = esc_js($key);
        wp_add_inline_script(
            'wc_mercadopago_custom_checkout',
            "if (window.wc_mercadopago_custom_checkout_params) {
    window.wc_mercadopago_custom_checkout_params.public_key = '{$escaped}';
}
if (window.wc_mercadopago_checkout_session_data_register_params) {
    window.wc_mercadopago_checkout_session_data_register_params.public_key = '{$escaped}';
}
if (typeof MPCheckoutSessionDataRegister !== 'undefined') {
    MPCheckoutSessionDataRegister.PUBLIC_KEY = '{$escaped}';
}
window.mpSdkInstance = null;",
            'after'
        );
    }

    /**
     * Super Token (account money / saved cards via MP login) is incompatible with
     * subscriptions — recurring charges run through the Pre-approval flow, not the
     * Super Token payment methods. Dequeues Super Token scripts on any subscription
     * payment context. Delegates context detection to isSubscriptionPaymentContext().
     *
     * Runs at wp_enqueue_scripts priority 20 (after the scripts are enqueued at
     * priority 10), when WC()->cart is already loaded from session.
     */
    public function dequeueSuperTokenForSubscriptionCheckout(): void
    {
        if (!$this->isSubscriptionPaymentContext()) {
            return;
        }

        foreach ($this->getSuperTokenScripts() as $script) {
            wp_dequeue_script($script['handle']);
            wp_deregister_script($script['handle']);
        }
    }

    /**
     * Conditionally register WooCommerce Subscriptions support flags on the
     * gateway. Intentionally excludes `gateway_scheduled_payments` (Mercado
     * Pago manages renewal scheduling server-side via the Pre-approval API).
     *
     * When the subscriptions_enabled toggle is off, no flags are registered and
     * WCS automatically hides the gateway for subscription products in all
     * checkout types (Classic and Blocks).
     *
     * @return void
     */
    private function initWcsSupports(): void
    {
        if (
            SubscriptionsHelper::isWcsActive()
            && $this->get_option('subscriptions_enabled', 'no') === 'yes'
            && $this->mercadopago->subscriptionsHelper->resolveAccessToken($this->mercadopago->storeConfig) !== ''
        ) {
            $this->supports = array_merge($this->supports, [
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_payment_method_change_customer',
                'subscription_payment_method_change_admin',
                'subscription_amount_changes',
                'subscription_date_changes',
                'multiple_subscriptions',
            ]);
        }
    }

    /**
     * Process payment with branching for WooCommerce Subscriptions scenarios.
     *
     * Branch order is significant (scenario labels match the inline comments below):
     *  - Payment-method change (Scenario 3) — total is $0 and the order still
     *    contains a subscription product, so this MUST be checked first
     *    (otherwise the next branch would incorrectly route it to the
     *    initial-payment flow).
     *  - Initial payment for a new subscription (Scenario 1) — order contains a
     *    subscription product and we are not changing the method.
     *  - Standard order (Scenario 4) — delegate to the parent (existing checkout behavior).
     *
     * When WCS is not installed, both guard clauses short-circuit and the
     * call falls through to `parent::process_payment()`, preserving the
     * exact prior behavior (zero regression).
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id): array
    {
        if (!SubscriptionsHelper::isWcsActive()) {
            return parent::process_payment($order_id);
        }

        // Scenario 3 — Payment-method change: must be checked first because
        // the order total is $0 during a method-change request; without this guard the next
        // branch would misroute it as a new-subscription initial payment.
        if (
            class_exists('WC_Subscriptions_Change_Payment_Gateway')
            && !empty(\WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment)
        ) {
            return $this->process_subscription_payment_method_change(wc_get_order($order_id));
        }

        // Scenario 1 — Initial payment for a new subscription.
        // WCS is guaranteed active here (early return above handles the absent case).
        $order = wc_get_order($order_id);
        if (wcs_order_contains_subscription($order)) {
            try {
                return $this->process_subscription_initial_payment($order);
            } catch (\Exception $e) {
                // \Error (e.g. TypeError) propagates to WooCommerce's top-level error boundary —
                // same behaviour as AbstractGateway::process_payment() for the regular checkout.
                return $this->processReturnFail($e, $e->getMessage(), self::LOG_SOURCE, (array) $order, true);
            }
        }

        // Scenario 4 — Standard order; delegate to parent (existing checkout behavior).
        return parent::process_payment($order_id);
    }

    /**
     * Handle the customer-initiated payment-method change for an existing
     * subscription.
     *
     * Orchestrates the add-then-remove sequence via AP v2:
     *   1. POST /subscriptions/{id}/payment-methods with set_as_default: true
     *   2. DELETE /subscriptions/{id}/payment-methods (only when a previous card differs from the new one)
     *
     * 422 handling:
     *   - LastPaymentMethod   → silent success (old card was already gone)
     *   - CannotRemoveDefault → partial success: error log + admin notice; new card is already default
     *
     * @param \WC_Order|false $order
     * @return array
     */
    private function process_subscription_payment_method_change($order): array
    {
        if (!$order || !function_exists('wcs_get_subscription')) {
            return $this->paymentMethodChangeFailure(
                __('Could not load the subscription to change the payment method.', 'woocommerce-mercadopago')
            );
        }

        $subscription = wcs_get_subscription($order->get_id()) ?: $order;

        $subscriptionId = (string) $this->mercadopago->subscriptionsHelper
            ->getSubscriptionMeta($subscription, '_mp_subscription_id', '');
        if ($subscriptionId === '') {
            $this->mercadopago->automaticPaymentsClient->log(
                'error',
                'op=change-payment-method error=missing_subscription_id',
                ['wc_order_id' => $order->get_id()]
            );
            return $this->paymentMethodChangeFailure(
                __('Subscription is not linked to Mercado Pago yet.', 'woocommerce-mercadopago')
            );
        }

        $checkout = $this->getCheckoutFormData($order);
        $token    = isset($checkout['token']) ? (string) $checkout['token'] : '';
        if ($token === '') {
            return $this->paymentMethodChangeFailure(
                __('Please enter the new card data to continue.', 'woocommerce-mercadopago')
            );
        }

        $accessToken = $this->mercadopago->subscriptionsHelper->resolveAccessToken($this->mercadopago->storeConfig);
        if ($accessToken === '') {
            $this->mercadopago->automaticPaymentsClient->log(
                'error',
                'op=change-payment-method error=missing_access_token'
            );
            return $this->paymentMethodChangeFailure(
                __('Recurring Payments credential is missing. Contact the store administrator.', 'woocommerce-mercadopago')
            );
        }

        $oldCardId = (string) $this->mercadopago->subscriptionsHelper
            ->getSubscriptionMeta($subscription, '_mp_active_card_id', '');

        $idemKey = $this->mercadopago->subscriptionsHelper->generateIdempotencyKey(
            $this->mercadopago->subscriptionsHelper->buildAddPaymentMethodSeed($subscriptionId, $token)
        );

        try {
            $addResult = $this->mercadopago->automaticPaymentsClient->addPaymentMethod(
                $subscriptionId,
                $token,
                $accessToken,
                $idemKey,
                $oldCardId !== '' ? $oldCardId : null
            );
        } catch (Exception $e) {
            $this->mercadopago->automaticPaymentsClient->log(
                'error',
                'op=add-payment-method exception=' . $e->getMessage(),
                ['subscription_id' => $subscriptionId]
            );
            return $this->paymentMethodChangeFailure(
                __('We could not process the card change. Please try again in a few minutes.', 'woocommerce-mercadopago')
            );
        }

        if ($addResult['status'] >= 400) {
            $userMessage = $this->mercadopago->subscriptionsHelper->mapApiErrorToUserMessage(
                $addResult['status'],
                $addResult['data']['error'] ?? null,
                $addResult['data']['code'] ?? null
            );
            return $this->paymentMethodChangeFailure(
                $userMessage !== ''
                    ? $userMessage
                    : __('We could not add the new card. Please try a different card.', 'woocommerce-mercadopago')
            );
        }

        if (empty($addResult['new_card_id'])) {
            $this->mercadopago->automaticPaymentsClient->log('info', 'op=change-payment-method status=no-op same-card', [
                'subscription_id' => $subscriptionId,
            ]);
            set_transient(
                'mp_wcs_pm_success_' . get_current_user_id(),
                __('Payment method updated successfully.', 'woocommerce-mercadopago'),
                60
            );
            return [
                'result'   => 'success',
                'redirect' => $subscription->get_view_order_url(),
            ];
        }

        $newCardId = (string) $addResult['new_card_id'];

        if ($oldCardId !== '' && $oldCardId !== $newCardId) {
            try {
                $removeResult = $this->mercadopago->automaticPaymentsClient->removePaymentMethod(
                    $subscriptionId,
                    $oldCardId,
                    $accessToken
                );
            } catch (Exception $e) {
                $this->mercadopago->automaticPaymentsClient->log(
                    'warning',
                    'op=remove-payment-method exception=' . $e->getMessage(),
                    ['subscription_id' => $subscriptionId]
                );
                // Remove failed — old card stays orphaned in the profile.
                $this->triggerCardChangeInconsistencyNotice($subscriptionId);
                $removeResult = ['status' => 0, 'data' => [], 'error' => null];
            }

            if (($removeResult['error'] ?? null) === 'cannot_remove_default') {
                $this->triggerCardChangeInconsistencyNotice($subscriptionId);
            }
        }

        $newPaymentMethods = $addResult['data']['profile']['payment_methods'] ?? [];
        $newDefaultPm      = $this->findPaymentMethodByCardId($newPaymentMethods, $newCardId);

        $helper = $this->mercadopago->subscriptionsHelper;
        $helper->setSubscriptionMeta($subscription, '_mp_active_card_id', $newCardId);
        if (isset($newDefaultPm['last_four_digits'])) {
            $helper->setSubscriptionMeta($subscription, '_mp_active_card_last_four', (string) $newDefaultPm['last_four_digits']);
        }
        if (isset($newDefaultPm['brand'])) {
            $helper->setSubscriptionMeta($subscription, '_mp_active_card_brand', (string) $newDefaultPm['brand']);
        }

        $this->mercadopago->automaticPaymentsClient->log('info', 'op=change-payment-method status=ok', [
            'subscription_id' => $subscriptionId,
            'new_card_id'     => $newCardId,
        ]);

        set_transient(
            'mp_wcs_pm_success_' . get_current_user_id(),
            __('Payment method updated successfully.', 'woocommerce-mercadopago'),
            60
        );

        return [
            'result'   => 'success',
            'redirect' => $subscription->get_view_order_url(),
        ];
    }

    /**
     * Exibe admin notice WP quando `CannotRemoveDefault` ocorreu (AC-4).
     * Consome o transient gravado em {@see triggerCardChangeInconsistencyNotice()}.
     *
     * Usa `get_transient()` em vez de query SQL directa para compatibilidade
     * com lojas que utilizam object cache persistente (Redis/Memcached).
     *
     * O transient é GLOBAL (sem chave por-usuário) de propósito: a troca de cartão
     * é disparada pelo comprador no front-end (My Account → Change payment), mas
     * este alerta crítico precisa ser visto pelo admin na tela da assinatura — quem
     * grava e quem lê são usuários diferentes, então uma chave por get_current_user_id()
     * quebraria a notificação. Não converter para per-usuário.
     */
    public function displayCardChangeInconsistencyNotice(): void
    {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || strpos((string) $screen->id, 'shop_subscription') === false) {
            return;
        }

        $messages = get_transient('mp_subscription_card_change_inconsistencies');
        if (empty($messages) || !is_array($messages)) {
            return;
        }

        delete_transient('mp_subscription_card_change_inconsistencies');

        foreach ($messages as $message) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html((string) $message)
            );
        }
    }

    /**
     * Localiza o PM correspondente ao `card_id` recém adicionado.
     *
     * @param array<int, mixed> $paymentMethods
     * @return array<string, mixed>
     */
    private function findPaymentMethodByCardId(array $paymentMethods, string $cardId): array
    {
        foreach ($paymentMethods as $pm) {
            if (!is_array($pm)) {
                continue;
            }
            if ((string) ($pm['card_id'] ?? '') === $cardId) {
                return $pm;
            }
        }
        return [];
    }

    /**
     * Stores a critical admin notice when CannotRemoveDefault is returned by the AP v2 API,
     * meaning the new card failed to become the default — requires manual investigation.
     *
     * Global transient by design: this runs in the buyer's context (front-end
     * change-payment flow) but must surface to the admin on the subscription screen.
     * A per-user key (get_current_user_id) would break that hand-off. See
     * {@see displayCardChangeInconsistencyNotice()}.
     */
    private function triggerCardChangeInconsistencyNotice(string $subscriptionId): void
    {
        $message = sprintf(
            /* translators: %s = subscription id */
            __('Mercado Pago: critical inconsistency removing the old card on subscription %s. Please check WC > Status > Logs (channel: mercadopago-subscriptions).', 'woocommerce-mercadopago'),
            $subscriptionId
        );

        $ttl      = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;
        $existing = get_transient('mp_subscription_card_change_inconsistencies');
        $queue    = is_array($existing) ? $existing : [];
        $queue[]  = $message;
        set_transient('mp_subscription_card_change_inconsistencies', $queue, $ttl);
    }

    /**
     * Reads the change-payment error transient (set on POST) and re-queues it
     * as a WC notice so WCS displays it after the page reload triggered by the
     * MP checkout JS. Must run at priority 1 — before WCS store_pay_shortcode_messages
     * (priority 5) captures the notice queue for the ob-swap flow.
     *
     * Only acts on GET requests: during the AJAX POST the transient must be
     * preserved so it survives to the subsequent reload.
     */
    public function restoreChangePaymentError(): void
    {
        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'GET') {
            return;
        }
        $key     = 'mp_wcs_pm_error_' . get_current_user_id();
        $message = get_transient($key);
        if ($message) {
            wc_add_notice($message, 'error');
            delete_transient($key);
        }
    }

    /**
     * Reads the change-payment success transient (set on POST) and re-queues it
     * as a WC notice so WCS displays it after the page reload triggered by the
     * MP checkout JS. Mirrors restoreChangePaymentError() for the success path.
     */
    public function restoreChangePaymentSuccess(): void
    {
        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'GET') {
            return;
        }
        $key     = 'mp_wcs_pm_success_' . get_current_user_id();
        $message = get_transient($key);
        if ($message) {
            wc_add_notice($message, 'success');
            delete_transient($key);
        }
    }

    /**
     * Atalho para a resposta de falha consumida pelo WCS.
     *
     * Persists the message in a short-lived transient so it survives the page
     * reload that the MP checkout JS performs after receiving the HTML response.
     *
     * @return array{result:string, message:string}
     */
    private function paymentMethodChangeFailure(string $message): array
    {
        set_transient('mp_wcs_pm_error_' . get_current_user_id(), $message, 60);
        return [
            'result'  => 'fail',
            'message' => $message,
        ];
    }

    /**
     * Handle the first/initial payment of a brand-new subscription.
     *
     * @param \WC_Order $order
     * @return array
     */
    private function process_subscription_initial_payment($order): array
    {
        $this->mercadopago->orderMetadata->setIsProductionModeData($order, $this->mercadopago->storeConfig->getProductionMode());
        $this->mercadopago->orderMetadata->setUsedGatewayData($order, static::ID);

        if (($this->settings['currency_conversion'] ?? 'no') === 'yes') {
            $ratio = $this->mercadopago->helpers->currency->getRatio($this);
            if ($ratio > 0) {
                $this->mercadopago->orderMetadata->setCurrencyRatioData($order, $ratio);
            } else {
                $this->mercadopago->logs->file->warning(
                    'op=cit step=currency_ratio_skipped reason=invalid_ratio ratio=' . $ratio
                        . ' order_id=' . $order->get_id(),
                    self::LOG_SOURCE
                );
            }
        }

        $logSource   = self::LOG_SOURCE;
        $logger      = $this->mercadopago->logs->file;
        $client      = $this->mercadopago->automaticPaymentsClient;
        $helper      = $this->mercadopago->subscriptionsHelper;
        $translations = $this->storeTranslations;

        $subscriptions = function_exists('wcs_get_subscriptions_for_order')
            ? \wcs_get_subscriptions_for_order($order)
            : [];
        $subscription = is_array($subscriptions) && !empty($subscriptions)
            ? reset($subscriptions)
            : null;

        $orderId = (int) $order->get_id();

        if (!$subscription) {
            $logger->error("op=cit step=abort reason=no_subscription order_id={$orderId}", $logSource);
            throw new InvalidCheckoutDataException($translations['wcs_cit_failed_generic'] ?? '');
        }

        $accessToken = $this->mercadopago->subscriptionsHelper->resolveAccessToken($this->mercadopago->storeConfig);
        if ($accessToken === '') {
            $logger->error("op=cit step=abort reason=missing_access_token order_id={$orderId}", $logSource);
            throw new InvalidCheckoutDataException($translations['wcs_cit_no_credential'] ?? '');
        }

        $checkout = $this->getCheckoutFormData($order);
        $missing  = array_filter(['token', 'payment_method_id'], fn($f) => empty($checkout[$f] ?? null));
        if (!empty($missing)) {
            $missingList = implode(',', $missing);
            $logger->error("op=cit step=abort reason=missing_checkout_fields fields={$missingList} order_id={$orderId}", $logSource);
            throw new InvalidCheckoutDataException(
                $translations['wcs_cit_missing_card'] ?? '',
                0,
                null,
                ['fields' => $missingList]
            );
        }

        $payload  = $this->buildCitPayload($order, $subscription, $checkout);
        $response = $client->cit($accessToken, $order, $payload);

        $data           = (array) ($response->getData() ?? []);
        $paymentStatus  = $data['payment']['status'] ?? null;
        $paymentId      = $data['payment']['id'] ?? null;
        $subscriptionId = $data['subscription']['id'] ?? null;
        $httpStatus     = $response->getStatus();

        // Hard API failure (4xx/5xx) — subscription was likely not created.
        if ($httpStatus >= 400) {
            $apiError = $data['error'] ?? null;
            $detail   = $data['payment']['status_detail'] ?? null;
            $logger->warning(
                "op=cit step=api_error http_status={$httpStatus} order_id={$orderId}",
                $logSource
            );
            $msg = $helper->mapApiErrorToUserMessage($httpStatus, $apiError, $detail);
            return ['result' => 'failure', 'messages' => $msg];
        }

        // Persist AP metadata only for statuses that can eventually succeed.
        // Rejected payments are excluded: a retry with a new card creates a new AP
        // subscription, so persisting the ID from a failed CIT would be wrong.
        // Must happen before delegating so the 3DS flow has metadata when it completes.
        if ($subscriptionId !== null && in_array($paymentStatus, ['approved', 'pending', 'in_process'], true)) {
            $metaToSet = [
                '_mp_subscription_id'         => $subscriptionId,
                '_mp_customer_id'             => $data['customer']['id'] ?? null,
                '_mp_active_card_id'          => $data['card']['id'] ?? null,
                '_mp_active_card_last_four'   => $data['card']['last_four_digits'] ?? null,
                '_mp_active_card_brand'       => $data['card']['payment_method'] ?? ($data['card']['payment_method_id'] ?? null),
                '_mp_subscription_created_at' => gmdate('c'),
            ];
            foreach ((array) $subscriptions as $sub) {
                foreach ($metaToSet as $key => $value) {
                    $helper->setSubscriptionMeta($sub, $key, $value);
                }
            }
            // No save() needed after: setCustomMetadata persists the order internally.
            $this->mercadopago->orderMetadata->setCustomMetadata($order, $data['payment'] ?? []);
        }

        // Delegate all 2xx status handling to the shared method:
        //   approved          → redirect (payment_complete handled by MP webhook)
        //   pending_challenge → 3DS modal flow
        //   pending/in_process → redirect to order received
        //   rejected          → buyerRefusedMessages (same UX as normal checkout)
        return $this->handleResponseStatus($order, $this->normalizeCitResponse($data));
    }

    /**
     * Normalizes the AP v2 CIT response into the flat array structure
     * expected by handleResponseStatus().
     *
     * @param array $data Raw data array from AutomaticPaymentsClient::cit() response.
     * @return array
     */
    private function normalizeCitResponse(array $data): array
    {
        $payment = $data['payment'] ?? [];
        return [
            'status'        => $payment['status'] ?? null,
            'status_detail' => $payment['status_detail'] ?? null,
            'id'            => $payment['id'] ?? null,
            'three_ds_info' => $data['three_ds_info'] ?? [],
            'card'          => [
                'last_four_digits' => $data['card']['last_four_digits'] ?? ($payment['card']['last_four_digits'] ?? null),
            ],
        ];
    }

    /**
     * Builds the AP v2 CIT payload.
     *
     * @param \WC_Order        $order
     * @param \WC_Subscription $subscription
     * @param array            $checkout Sanitized POST data from getCheckoutFormData().
     * @return array
     */
    protected function buildCitPayload($order, $subscription, array $checkout): array
    {
        $storeConfig = $this->mercadopago->storeConfig;

        $document    = $checkout['doc_number'] ?? '';
        $idType      = $checkout['doc_type'] ?? '';
        $interval    = (int) (method_exists($subscription, 'get_billing_interval') ? $subscription->get_billing_interval() : 1);
        $period      = method_exists($subscription, 'get_billing_period') ? $subscription->get_billing_period() : 'month';

        $userAgent   = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 256)
            : '';
        $ip          = class_exists('\WC_Geolocation') ? \WC_Geolocation::get_ip_address() : '';

        $stateId     = '';
        $country     = $order->get_billing_country();
        $state       = $order->get_billing_state();
        if ($country && $state) {
            $stateId = $country . '-' . $state;
        }

        $currency = $this->countryConfigs['currency'];

        $items             = [];
        $transactionAmount = 0.0;

        foreach ($order->get_items() as $item) {
            $product  = $item->get_product();
            $quantity = $item->get_quantity();
            $title    = $item->get_name() . ' x ' . $quantity;
            $amount   = Numbers::calculateByCurrency(
                $currency,
                $item->get_total() + $item->get_total_tax(),
                $this->ratio
            );
            $transactionAmount += $amount;
            $items[]            = [
                'id'          => (string) $item->get_product_id(),
                'title'       => $title,
                'description' => $product ? $this->mercadopago->helpers->strings->sanitizeAndTruncateText($product->get_description()) : '',
                'picture_url' => $product ? (wp_get_attachment_url($product->get_image_id()) ?: '') : '',
                'category_id' => $this->mercadopago->storeConfig->getStoreCategory('others'),
                'unit_price'  => Numbers::formatByCurrency($currency, $amount),
                'currency_id' => $currency,
                'quantity'    => '1',
            ];
        }

        $shipAmount         = Numbers::calculateByCurrency(
            $currency,
            Numbers::format((float) $order->get_shipping_total()) + Numbers::format((float) $order->get_shipping_tax()),
            $this->ratio
        );
        $transactionAmount += $shipAmount;
        if ($shipAmount > 0) {
            $items[] = [
                'id'          => 'shipping',
                'title'       => $this->mercadopago->orderShipping->getShippingMethod($order),
                'description' => $this->mercadopago->storeTranslations->commonCheckout['shipping_title'],
                'category_id' => $this->mercadopago->storeConfig->getStoreCategory('others'),
                'unit_price'  => Numbers::formatByCurrency($currency, $shipAmount),
                'currency_id' => $currency,
                'quantity'    => '1',
            ];
        }

        foreach ($order->get_fees() as $fee) {
            $feeAmount          = Numbers::calculateByCurrency(
                $currency,
                Numbers::format((float) $fee->get_total()) + Numbers::format((float) $fee->get_total_tax()),
                $this->ratio
            );
            $transactionAmount += $feeAmount;
            if ($feeAmount > 0) {
                $items[] = [
                    'id'          => 'fee',
                    'title'       => $this->mercadopago->helpers->strings->sanitizeAndTruncateText($fee->get_name()),
                    'description' => $this->mercadopago->helpers->strings->sanitizeAndTruncateText($fee->get_name()),
                    'category_id' => $this->mercadopago->storeConfig->getStoreCategory('others'),
                    'unit_price'  => Numbers::formatByCurrency($currency, $feeAmount),
                    'currency_id' => $currency,
                    'quantity'    => '1',
                ];
            }
        }

        $payer = [
            'email'      => $order->get_billing_email(),
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
        ];
        if (!Arrays::anyEmpty($checkout, ['doc_type', 'doc_number'])) {
            $payer['identification'] = [
                'type'   => $idType,
                'number' => $document,
            ];
        }

        return [
            'token' => $checkout['token'],
            'payer' => $payer,
            'transaction' => [
                'amount'              => (float) Numbers::formatByCurrency($currency, $transactionAmount),
                'currency'            => $currency,
                'description'         => $this->buildSubscriptionDescription($order),
                'external_reference'  => get_option('_mp_store_identificator', 'WC-') . $order->get_id(),
                'installments'        => 1, // RN-08: subscriptions are incompatible with installment payments.
                'statement_descriptor' => $storeConfig->getStoreName('Mercado Pago'),
                'three_d_secure_mode' => 'optional',
            ],
            'subscription' => [
                'external_id' => 'WC-SUB-' . $subscription->get_id(),
                'frequency'   => $interval . '-' . $period,
            ],
            'device' => [
                'fingerprint' => $checkout['session_id'] ?? '',
                'ip'          => $ip,
                'user_agent'  => $userAgent,
            ],
            'additional_info' => [
                'ip_address' => $ip,
                'items'      => $items,
                'payer'      => [
                    'first_name' => $order->get_billing_first_name(),
                    'last_name'  => $order->get_billing_last_name(),
                    'email'      => $order->get_billing_email(),
                    'phone'      => ['number' => $order->get_billing_phone()],
                    'address'    => [
                        'street_name' => $order->get_billing_address_1(),
                        'city'        => $order->get_billing_city(),
                        'state'       => $order->get_billing_state(),
                        'zip_code'    => $order->get_billing_postcode(),
                        'country'     => $order->get_billing_country(),
                    ],
                ],
            ],
            'platform' => [
                'environment' => [
                    'platform_version' => defined('WC_VERSION') ? WC_VERSION : '',
                    'module_version'   => defined('MP_VERSION') ? MP_VERSION : '',
                    'runtime_version'  => PHP_VERSION,
                ],
            ],
            'sponsor_id'      => $this->countryConfigs['sponsor_id'] ?? null,
            'notification_url' => $this->buildCitNotificationUrl(),
            'point_of_interaction' => [
                'location' => [
                    'source'   => 'payer',
                    'state_id' => $stateId,
                ],
            ],
        ];
    }

    /**
     * Builds notification_url matching AbstractTransaction::getNotificationUrl().
     * Delegates to the shared WebhookUrl helper so CIT and MIT stay in sync.
     */
    protected function buildCitNotificationUrl(): string
    {
        return WebhookUrl::build(
            $this->mercadopago->storeConfig->getCustomDomain(),
            $this->mercadopago->storeConfig->getCustomDomainOptions(),
            fn() => $this->mercadopago->woocommerce->api_request_url(self::WEBHOOK_API_NAME),
            get_site_url(),
            self::WEBHOOK_API_NAME
        );
    }

    protected function buildSubscriptionDescription($order): string
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name();
            if (count($items) >= 3) {
                break;
            }
        }
        return $items
            ? implode(', ', $items)
            : __('Subscription', 'woocommerce-mercadopago') . ' WC-ORDER-' . $order->get_id();
    }

    public function getCheckoutName(): string
    {
        return 'checkout-custom';
    }

    private function getCheckoutEmailIfAvailable()
    {
        $order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        if ($order_key) {
            $order_id = wc_get_order_id_by_order_key($order_key);
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    return $order->get_billing_email();
                }
            }
        }

        $current_user = wp_get_current_user();
        if ($current_user->ID && $current_user->user_email) {
            return $current_user->user_email;
        }

        if (WC()->customer) {
            $email = WC()->customer->get_billing_email();
            if ($email) {
                return $email;
            }
        }

        return '';
    }

    public function formFieldsSubscriptionsSection(): array
    {
        if (!SubscriptionsHelper::isWcsActive()) {
            return [];
        }

        return [
            'subscriptions_enabled' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['subscriptions_enabled_title'],
                'subtitle'     => $this->adminTranslations['subscriptions_enabled_subtitle'],
                'default'      => 'no',
                'class'        => 'mp-subscriptions-group',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['subscriptions_enabled_description_enabled'],
                    'disabled' => $this->adminTranslations['subscriptions_enabled_description_disabled'],
                ],
            ],
            'subscriptions_notice' => [
                'type'        => 'mp_subscriptions_notice',
                'title'       => $this->adminTranslations['subscriptions_notice_title'],
                'description' => $this->adminTranslations['subscriptions_notice_description'],
                'class'        => 'mp-subscriptions-group',
            ],
            'subscriptions_public_key_prod' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['subscriptions_public_key_prod_title'],
                'description' => $this->adminTranslations['subscriptions_public_key_prod_description'],
                'default'     => '',
                'class'       => 'mp-subscriptions-group',
            ],
            'subscriptions_access_token_prod' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['subscriptions_access_token_prod_title'],
                'description' => $this->adminTranslations['subscriptions_access_token_prod_description'],
                'default'     => '',
                'class'       => 'mp-subscriptions-group',
            ],
            'subscriptions_public_key_test' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['subscriptions_public_key_test_title'],
                'description' => $this->adminTranslations['subscriptions_public_key_test_description'],
                'default'     => '',
                'class'       => 'mp-subscriptions-group',
            ],
            'subscriptions_access_token_test' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['subscriptions_access_token_test_title'],
                'description' => $this->adminTranslations['subscriptions_access_token_test_description'],
                'default'     => '',
            ],
        ];
    }

    /**
     * @param string $key
     * @param array  $data
     * @return string
     */
    public function generate_mp_subscriptions_notice_html(string $key, array $data): string
    {
        return sprintf(
            '<tr valign="top" id="tr_%s" class="%s">
                <td colspan="2" class="forminp">
                    <div class="mp-card-info mp-subscriptions-notice">
                        <div class="mp-alert-color-success"></div>
                        <div class="mp-card-body-payments mp-card-body-size">
                            <div class="mp-icon-badge-info"></div>
                            <div>
                                <span class="mp-text-title">%s</span>
                                <span class="mp-text-subtitle">%s</span>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>',
            esc_attr($key),
            esc_attr($data['class'] ?? ''),
            esc_html($data['title']),
            esc_html($data['description'])
        );
    }

    public function validateSubscriptionsBeforeSave(array $fields): array
    {
        $enabled   = isset($fields['subscriptions_enabled']) && $fields['subscriptions_enabled'] === 'yes';
        $tokenProd = $fields['subscriptions_access_token_prod'] ?? '';
        $tokenTest = $fields['subscriptions_access_token_test'] ?? '';

        if (!$enabled) {
            $this->mercadopago->funnel->updateStepPaymentMethods(false);
            return $fields;
        }

        if (empty($tokenProd) && empty($tokenTest)) {
            $fields['subscriptions_enabled'] = 'no';
            set_transient('mp_subscriptions_save_result_' . get_current_user_id(), [
                'valid'        => false,
                'user_message' => __('Please fill in at least one Pre-approval Access Token to activate Recurring Payments.', 'woocommerce-mercadopago'),
            ], 60);
            $this->mercadopago->funnel->updateStepPaymentMethods(false);
            return $fields;
        }

        // The token for the current environment (prod or sandbox) must be present.
        // Having only the other environment's token is not sufficient — subscriptions
        // would silently not work for the active mode.
        // The public key is validated here together with the token so that both
        // missing-field errors are reported in a single save attempt.
        $isTestMode        = $this->mercadopago->storeConfig->isTestMode();
        $relevantToken     = $isTestMode ? $tokenTest : $tokenProd;
        $publicKeyField    = $isTestMode ? 'subscriptions_public_key_test' : 'subscriptions_public_key_prod';
        $relevantPublicKey = $fields[$publicKeyField] ?? '';

        if (empty($relevantToken) || empty($relevantPublicKey)) {
            $missingMode     = $isTestMode
                ? __('Sandbox', 'woocommerce-mercadopago')
                : __('Production', 'woocommerce-mercadopago');
            $missingMessages = [];

            if (empty($relevantToken)) {
                $missingMessages[] = sprintf(
                    /* translators: %s: environment name (Production or Sandbox) */
                    __('Please fill in the %s Pre-approval Access Token to activate Recurring Payments in this environment.', 'woocommerce-mercadopago'),
                    $missingMode
                );
            }

            if (empty($relevantPublicKey)) {
                $missingMessages[] = sprintf(
                    /* translators: %s: environment name (Production or Sandbox) */
                    __('Please fill in the %s Pre-approval Public Key to activate Recurring Payments in this environment.', 'woocommerce-mercadopago'),
                    $missingMode
                );
            }

            $fields['subscriptions_enabled'] = 'no';
            set_transient('mp_subscriptions_save_result_' . get_current_user_id(), [
                'valid'        => false,
                'user_message' => implode(' | ', $missingMessages),
            ], 60);
            $this->mercadopago->funnel->updateStepPaymentMethods(false);
            return $fields;
        }

        $errors      = [];
        $resultProd  = null;
        $resultTest  = null;

        if (!empty($tokenProd)) {
            $resultProd = $this->mercadopago->subscriptionsCredentialsValidator->validate($tokenProd);
            if (!$resultProd['valid']) {
                $errors[] = __('Recurring credential', 'woocommerce-mercadopago') . ': ' . $this->translatedValidationMessage($resultProd['reason'] ?? '');
            }
        }

        if (!empty($tokenTest)) {
            $resultTest = $this->mercadopago->subscriptionsCredentialsValidator->validate($tokenTest);
            if (!$resultTest['valid']) {
                $errors[] = __('Test credential', 'woocommerce-mercadopago') . ': ' . $this->translatedValidationMessage($resultTest['reason'] ?? '');
            }
        }

        if (!empty($errors)) {
            // Tokens are kept as typed so the user can correct them without retyping.
            // Only the enabled flag is forced off — the feature stays inactive until
            // a successful validation.
            $fields['subscriptions_enabled'] = 'no';

            set_transient('mp_subscriptions_save_result_' . get_current_user_id(), [
                'valid'        => false,
                'user_message' => implode(' | ', $errors),
            ], 60);
        } else {
            // Both tokens belong to the same app — app_name and app_id are the same in either result.
            $successResult = $resultProd ?? $resultTest ?? [];

            set_transient('mp_subscriptions_save_result_' . get_current_user_id(), [
                'valid'    => true,
                'app_name' => $successResult['app_name'] ?? null,
                'app_id'   => $successResult['app_id'] ?? null,
            ], 60);

            \MercadoPago\Woocommerce\Hooks\Subscriptions::clearCredentialRevokedNotice();
        }

        $this->mercadopago->funnel->updateStepPaymentMethods(
            ($fields['subscriptions_enabled'] ?? 'no') === 'yes'
        );

        return $fields;
    }

    public function displaySubscriptionsValidationNotice(): void
    {
        // Only show on the Custom gateway settings page.
        $page    = Form::sanitizedGetData('page');
        $tab     = Form::sanitizedGetData('tab');
        $section = Form::sanitizedGetData('section');

        if ($page !== 'wc-settings' || $tab !== 'checkout' || $section !== self::ID) {
            return;
        }

        $userId = get_current_user_id();
        $result = get_transient('mp_subscriptions_save_result_' . $userId);

        if ($result === false) {
            return;
        }

        delete_transient('mp_subscriptions_save_result_' . $userId);

        if ($result['valid']) {
            $appName = !empty($result['app_name']) ? $result['app_name'] : ($result['app_id'] ?? '');
            $message = __('Pre-approval credentials validated successfully', 'woocommerce-mercadopago')
                . ($appName ? ' — ' . __('Application', 'woocommerce-mercadopago') . ': ' . $appName : '')
                . '.';
            $type    = 'success';
        } else {
            $message = $result['user_message'] ?? __('Error validating recurring payments credential.', 'woocommerce-mercadopago');
            $type    = 'error';
        }

        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($message));
    }

    /**
     * Maps a validator `reason` code to a localised, user-facing message.
     *
     * The validator is intentionally decoupled from WP i18n — it returns only
     * a typed `reason` string. Translation happens here, at the presentation layer.
     */
    private function translatedValidationMessage(string $reason): string
    {
        $map = [
            'malformed_token'       => __('Invalid Token format.', 'woocommerce-mercadopago'),
            'invalid_token'         => __('Invalid or expired Token.', 'woocommerce-mercadopago'),
            'token_app_mismatch'    => __('This Access Token does not belong to the expected application. Contact your Mercado Pago commercial consultant.', 'woocommerce-mercadopago'),
            'application_not_found' => __('Credential application not found. Please check if the token is valid.', 'woocommerce-mercadopago'),
            'service_unavailable'   => __('Could not validate the credential at this time. Please try again.', 'woocommerce-mercadopago'),
            'scope_field_missing'   => __('Could not verify application scopes. Please contact your Mercado Pago commercial consultant.', 'woocommerce-mercadopago'),
            'missing_scope'         => __('This credential does not have the required Pre-approval scope. Please request activation from your Mercado Pago commercial consultant.', 'woocommerce-mercadopago'),
            'application_inactive'  => __('This application is inactive in Mercado Pago. Contact your Mercado Pago commercial consultant.', 'woocommerce-mercadopago'),
            'application_blocked'   => __('This application is blocked in Mercado Pago. Contact your Mercado Pago commercial consultant.', 'woocommerce-mercadopago'),
            'application_disabled'  => __('This application is disabled in Mercado Pago. Contact your Mercado Pago commercial consultant.', 'woocommerce-mercadopago'),
            'unexpected_response'   => __('Unexpected response when validating the credential.', 'woocommerce-mercadopago'),
        ];

        return $map[$reason] ?? __('An error occurred while validating the credential.', 'woocommerce-mercadopago');
    }

    public function formFieldsHeaderSection(): array
    {
        return array_replace_recursive(parent::formFieldsHeaderSection(), [
            'header' => [
                'title' => $this->mercadopago->sellerConfig->getSiteId() === 'MLB' ? $this->adminTranslations['header_title_MLB'] : $this->adminTranslations['header_title_ALL'],
            ],
            'enabled' => [
                'descriptions' => [
                    'enabled' => $this->mercadopago->sellerConfig->getSiteId() === 'MLB' ? $this->adminTranslations['enabled_descriptions_enabled_MLB'] : $this->adminTranslations['enabled_descriptions_enabled_ALL'],
                    'disabled' => $this->mercadopago->sellerConfig->getSiteId() === 'MLB' ? $this->adminTranslations['enabled_descriptions_disabled_MLB'] : $this->adminTranslations['enabled_descriptions_disabled_ALL'],
                ],
            ],
            'title' => [
                'default' => $this->title,
            ],
        ]);
    }

    /**
     * Summary of formFieldsMainSection
     * @return array{advanced_configuration_description: array{class: string, title: mixed|TValue, type: string, advanced_configuration_title: array{class: string, title: mixed|TValue, type: string}, binary_mode: array, card_info_fees: array, card_info_helper: array{type: string, value: string}, currency_conversion: array}}
     */
    public function formFieldsMainSection(): array
    {
        return array_merge(
            [
                'card_info_helper' => [
                    'type' => 'title',
                    'value' => '',
                ],
                'card_info_fees' => [
                    'type' => 'mp_card_info',
                    'value' => [
                        'title' => $this->adminTranslations['card_info_fees_title'],
                        'subtitle' => $this->adminTranslations['card_info_fees_subtitle'],
                        'button_text' => $this->adminTranslations['card_info_fees_button_url'],
                        'button_url' => $this->links['mercadopago_costs'],
                        'icon' => 'mp-icon-badge-info',
                        'color_card' => 'mp-alert-color-success',
                        'size_card' => 'mp-card-body-size',
                        'target' => '_blank',
                    ],
                ],
                'currency_conversion' => [
                    'type' => 'mp_toggle_switch',
                    'title' => $this->adminTranslations['currency_conversion_title'],
                    'subtitle' => $this->adminTranslations['currency_conversion_subtitle'],
                    'default' => 'no',
                    'descriptions' => [
                        'enabled' => $this->adminTranslations['currency_conversion_descriptions_enabled'],
                        'disabled' => $this->adminTranslations['currency_conversion_descriptions_disabled'],
                    ],
                ],
                static::WALLET_BUTTON_ENABLED_OPTION => [
                    'type' => 'mp_toggle_switch',
                    'title' => $this->adminTranslations['wallet_button_title'],
                    'subtitle' => $this->adminTranslations['wallet_button_subtitle'],
                    'default' => static::WALLET_BUTTON_ENABLED_DEFAULT,
                    'after_toggle' => $this->getWalletButtonPreview(),
                    'descriptions' => [
                        'enabled' => $this->adminTranslations['wallet_button_descriptions_enabled'],
                        'disabled' => $this->adminTranslations['wallet_button_descriptions_disabled'],
                    ],
                ],
            ],
            $this->formFieldsSubscriptionsSection(),
            [
                'advanced_configuration_title' => [
                    'type' => 'title',
                    'title' => $this->adminTranslations['advanced_configuration_title'],
                    'class' => 'mp-subtitle-body',
                ],
                'advanced_configuration_description' => [
                    'type' => 'title',
                    'title' => $this->adminTranslations['advanced_configuration_subtitle'],
                    'class' => 'mp-small-text',
                ],
                'binary_mode' => [
                    'type' => 'mp_toggle_switch',
                    'title' => $this->adminTranslations['binary_mode_title'],
                    'subtitle' => $this->adminTranslations['binary_mode_subtitle'],
                    'default' => 'no',
                    'descriptions' => [
                        'enabled' => $this->adminTranslations['binary_mode_descriptions_enabled'],
                        'disabled' => $this->adminTranslations['binary_mode_descriptions_disabled'],
                    ],
                ],
            ]
        );
    }

    /**
     * Summary of registerSuperTokenStyles
     * @return void
     */
    public function registerSuperTokenStyles()
    {
        $version = MP_SUPER_TOKEN_VERSION;

        $this->mercadopago->hooks->scripts->registerCheckoutStyle(
            'wc_mercadopago_supertoken_payment_methods',
            $this->mercadopago->helpers->url->getCssAsset("checkouts/super-token/{$version}/super-token-payment-methods"),
        );

        $this->mercadopago->hooks->scripts->registerCheckoutStyle(
            'wc_mercadopago_supertoken_payment_method_details_skeleton',
            $this->mercadopago->helpers->url->getCssAsset("checkouts/super-token/{$version}/super-token-method-details-skeleton"),
        );
    }

    /**
     * Register checkout scripts
     *
     * @return void
     */
    public function registerCheckoutScripts(): void
    {
        parent::registerCheckoutScripts();

        $this->registerCustomCheckoutScripts();

        if (MP_SUPER_TOKEN_USE_BUNDLE) {
            $this->registerSuperTokenBundleFiles();
        } else {
            $this->registerSuperTokenSeparatedFiles();
        }

        $this->registerSuperTokenLocalizeParams();
    }

    /**
     * Summary of registerSuperTokenSeparatedFiles
     * @return void
     */
    private function registerSuperTokenSeparatedFiles(): void
    {
        $this->registerSuperTokenStyles();

        $this->registerSuperTokenScripts();
    }

    /**
     * Summary of registerSuperTokenBundleFiles
     * @return void
     */
    private function registerSuperTokenBundleFiles(): void
    {
        $this->registerSuperTokenBundleScripts();
    }

    /**
     * Register all super token scripts
     *
     * This method is used to register all super token scripts.
     *
     * Should not be tested because it is only used to register scripts.
     *
     * @codeCoverageIgnore
     * @return void
     */
    private function registerSuperTokenScripts()
    {
        foreach ($this->getSuperTokenScripts() as $script) {
            $this->registerCheckoutScriptDefinition($script);
        }
    }

    /**
     * Summary of registerSuperTokenBundleScripts
     * @return void
     */
    private function registerSuperTokenBundleScripts(): void
    {
        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_supertoken',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/super-token-loader')
        );
    }

    /**
     * Summary of registerSuperTokenLocalizeParams
     * @return void
     */
    private function registerSuperTokenLocalizeParams(): void
    {
        $localizeData = $this->getSuperTokenLocalizeData();
        if (empty($localizeData)) {
            return;
        }

        add_action('wp_enqueue_scripts', function () use ($localizeData) {
            wp_localize_script('wc_mercadopago_supertoken', 'wc_mercadopago_supertoken_bundle_params', $localizeData);
        });
    }

    /**
     * Summary of registerCustomCheckoutScripts
     * @return void
     */
    private function registerCustomCheckoutScripts(): void
    {
        foreach ($this->getCustomCheckoutScripts() as $script) {
            $this->registerCheckoutScriptDefinition($script);
        }
    }

    /**
     * Summary of registerCheckoutScriptDefinition
     * @param array $script
     * @return void
     */
    private function registerCheckoutScriptDefinition(array $script): void
    {
        if (isset($script['raw_url'])) {
            $scriptUrl = $script['raw_url'];
        } else {
            $scriptUrl = $this->mercadopago->helpers->url->getJsAsset($script['path']);
        }

        $deps = $script['deps'] ?? [];

        if (isset($script['localize'])) {
            $this->mercadopago->hooks->scripts->registerCheckoutScript(
                $script['handle'],
                $scriptUrl,
                $script['localize'],
                $deps
            );
            return;
        }

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            $script['handle'],
            $scriptUrl,
            [],
            $deps
        );
    }

    /**
     * Summary of getCustomCheckoutScripts
     * @return array
     */
    private function getCustomCheckoutScripts(): array
    {

        return [
            [
                'handle' => 'wc_mercadopago_security_session',
                'path' => 'session',
            ],
            [
                'handle' => 'wc_mercadopago_sdk',
                'raw_url' => $this->mercadopago->helpers->url->getMercadoPagoSdkUrl(),
            ],
            [
                'handle' => 'wc_mercadopago_custom_card_form',
                'path' => 'checkouts/custom/entities/card-form',
                'localize' => [
                    'security_code_placeholder_text_3_digits' => $this->storeTranslations['security_code_placeholder_text_3_digits'],
                ],
            ],
            [
                'handle' => 'wc_mercadopago_custom_three_ds_handler',
                'path' => 'checkouts/custom/entities/three-ds-handler',
            ],
            [
                'handle' => 'wc_mercadopago_custom_mobile_checkout_classic_observer',
                'path' => 'checkouts/custom/entities/mobile-checkout-classic-observer',
            ],
            [
                'handle' => 'wc_mercadopago_custom_event_handler',
                'path' => 'checkouts/custom/entities/event-handler',
                'deps' => ['wc_mercadopago_custom_mobile_checkout_classic_observer'],
                'localize' => [
                    'is_mobile' => Device::isMobile(),
                ],
            ],
            [
                'handle' => 'wc_mercadopago_custom_page',
                'path' => 'checkouts/custom/mp-custom-page',
                'localize' => [
                    'security_code_placeholder_text_3_digits' => $this->storeTranslations['security_code_placeholder_text_3_digits'],
                    'security_code_placeholder_text_4_digits' => $this->storeTranslations['security_code_placeholder_text_4_digits'],
                    'security_code_tooltip_text_3_digits' => $this->storeTranslations['security_code_tooltip_text_3_digits'],
                    'security_code_tooltip_text_4_digits' => $this->storeTranslations['security_code_tooltip_text_4_digits'],
                    'installments_select_placeholder_text' => $this->storeTranslations['placeholders_installments'],
                ],
            ],
            [
                'handle' => 'wc_mercadopago_custom_elements',
                'path' => 'checkouts/custom/mp-custom-elements',
            ],
            [
                'handle' => 'wc_mercadopago_custom_checkout',
                'path' => 'checkouts/custom/mp-custom-checkout',
                'localize' => [
                    'public_key' => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
                    'locale' => $this->storeTranslations['locale'],
                    'intl' => $this->countryConfigs['intl'],
                    'site_id' => $this->countryConfigs['site_id'],
                    'currency' => $this->countryConfigs['currency'],
                    'currency_code' => $this->mercadopago->helpers->currency->getCurrencyCode($this),
                    'theme' => get_stylesheet(),
                    'location' => '/checkout',
                    'plugin_version' => MP_VERSION,
                    'platform_version' => $this->mercadopago->woocommerce->version,
                    'placeholders' => [
                        'issuer' => $this->storeTranslations['placeholders_issuer'],
                        'installments' => $this->storeTranslations['placeholders_installments'],
                        'cardExpirationDate' => $this->storeTranslations['placeholders_card_expiration_date'],
                        'cardholderName' => $this->storeTranslations['placeholders_cardholder_name'],
                    ],
                    'input_title' => [
                        'installments' => $this->storeTranslations['card_installments_label'],
                    ],
                    'input_helper_message' => [
                        'cardNumber' => [
                            'invalid_type' => $this->storeTranslations['input_helper_message_invalid_type'],
                            'invalid_length' => $this->storeTranslations['input_helper_message_invalid_length'],
                            'invalid_value' => $this->storeTranslations['input_helper_message_invalid_value'],
                        ],
                        'cardholderName' => [
                            '221' => $this->storeTranslations['input_helper_message_card_holder_name_221'],
                            '316' => $this->storeTranslations['input_helper_message_card_holder_name_316'],
                        ],
                        'expirationDate' => [
                            'invalid_type' => $this->storeTranslations['input_helper_message_expiration_date_invalid_type'],
                            'invalid_length' => $this->storeTranslations['input_helper_message_expiration_date_invalid_length'],
                            'invalid_value' => $this->storeTranslations['input_helper_message_expiration_date_invalid_value'],
                        ],
                        'securityCode' => [
                            'invalid_type' => $this->storeTranslations['input_helper_message_security_code_invalid_type'],
                            'invalid_length' => $this->storeTranslations['input_helper_message_security_code_invalid_length'],
                        ],
                        'installments' => [
                            'required' => $this->storeTranslations['installments_required'],
                            'interest_free_option_text' => $this->storeTranslations['interest_free_option_text'],
                            'bank_interest_hint_text' => $this->storeTranslations['card_installments_interest_text'],
                        ],
                    ],
                    'threeDsText' => [
                        'title_loading' => $this->mercadopago->storeTranslations->threeDsTranslations['title_loading_3ds_frame'],
                        'title_loading2' => $this->mercadopago->storeTranslations->threeDsTranslations['title_loading_3ds_frame2'],
                        'text_loading' => $this->mercadopago->storeTranslations->threeDsTranslations['text_loading_3ds_frame'],
                        'title_loading_response' => $this->mercadopago->storeTranslations->threeDsTranslations['title_loading_3ds_response'],
                        'title_frame' => $this->mercadopago->storeTranslations->threeDsTranslations['title_3ds_frame'],
                        'tooltip_frame' => $this->mercadopago->storeTranslations->threeDsTranslations['tooltip_3ds_frame'],
                        'message_close' => $this->mercadopago->storeTranslations->threeDsTranslations['message_3ds_declined'],
                    ],
                    'error_messages' => [
                        'default' => $this->storeTranslations['default_error_message'],
                        'installments' => [
                            'invalid amount' => $this->storeTranslations['installments_error_invalid_amount'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Summary of getSuperTokenScripts
     * @return array<array|array{handle: string, path: string>}
     */
    private function getSuperTokenScripts(): array
    {
        $version = MP_SUPER_TOKEN_VERSION;

        return [
            [
                'handle' => 'wc_mercadopago_supertoken_error_constants',
                'path' => "checkouts/super-token/{$version}/errors/super-token-error-constants",
            ],
            [
                'handle' => 'wc_mercadopago_supertoken_error_handler',
                'path' => "checkouts/super-token/{$version}/errors/super-token-error-handler",
            ],
            [
                'handle' => 'wc_mercadopago_supertoken_debounce',
                'path' => "checkouts/super-token/{$version}/entities/debounce",
            ],
            [
                'handle' => 'wc_mercadopago_supertoken_email_listener',
                'path' => "checkouts/super-token/{$version}/entities/email-listener",
            ],
            [
                'handle' => 'wc_mercadopago_supertoken_metrics',
                'path' => "checkouts/super-token/{$version}/entities/super-token-metrics",
            ],
            [
                'handle' => 'wc_mercadopago_supertoken_trigger_handler',
                'path' => "checkouts/super-token/{$version}/entities/super-token-trigger-handler",
            ],
            [
                'handle' => 'wc_mercadopago_supertoken_payment_methods',
                'path' => "checkouts/super-token/{$version}/entities/super-token-payment-methods",
            ],
            [
                'handle' => 'wc_mercadopago_supertoken_authenticator',
                'path' => "checkouts/super-token/{$version}/entities/super-token-authenticator",
            ],
            [
                'handle' => 'wc_mercadopago_supertoken_checkout_form_validator',
                'path' => "checkouts/super-token/{$version}/validators/checkout-form-validator",
            ],
            [
                // Version-agnostic (shared across A/B variants): no {$version} in the path.
                'handle' => 'wc_mercadopago_supertoken_checkout_validation_resolver',
                'path' => 'checkouts/super-token/shared/validators/checkout-validation-resolver',
            ],
            [
                'handle' => 'wc_mercadopago_supertoken',
                'path' => "checkouts/super-token/{$version}/mp-super-token",
            ]
        ];
    }

    /**
     * Returns the full array of super token localize data (single source of truth).
     * Used by getSuperTokenLocalizeParams() for the bundle script.
     *
     * @return array<string, mixed>
     */
    private function getSuperTokenLocalizeData(): array
    {
        return [
                'plugin_version' => MP_VERSION,
                'platform_version' => $this->mercadopago->woocommerce->version,
                'site_id' => $this->countryConfigs['site_id'],
                'location' => '/checkout',
                'plugin_js_base_url' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/'),
                'theme' => get_stylesheet(),
                'cust_id' => $this->mercadopago->sellerConfig->getCustIdFromAT(),
                'current_user_email' => $this->getCheckoutEmailIfAvailable(),
                'wallet_button_enabled' => $this->getWalletButtonEnabled(),
                'yellow_wallet_path' => $this->mercadopago->helpers->url->getImageAsset('icons/icon-yellow-wallet'),
                'yellow_money_path' => $this->mercadopago->helpers->url->getImageAsset('icons/icon-yellow-money'),
                'white_card_path' => $this->mercadopago->helpers->url->getImageAsset('icons/icon-white-card'),
                'new_mp_logo_path' => $this->mercadopago->helpers->url->getImageAsset('logos/new-mp-logo'),
                'mp_logo_blue_path' => $this->mercadopago->helpers->url->getImageAsset('logos/mp-logo-blue'),
                'mp_logo_dark_path' => $this->mercadopago->helpers->url->getImageAsset('logos/mp-logo-dark'),
                'saved_cards_title' => $this->storeTranslations['saved_cards_title'],
                'saved_card_title' => $this->storeTranslations['saved_card_title'],
                'mp_methods_title' => $this->storeTranslations['mp_methods_title'],
                'account_money_balance_text' => $this->storeTranslations['account_money_balance_text'],
                'saved_payment_method_title' => $this->storeTranslations['saved_payment_method_title'],
                'payment_methods_order' => $this->mercadopago->hooks->options->getGatewayOption($this, 'payment_methods_order', 'cards_first'),
                'payment_methods_thumbnails' => $this->mercadopago->sellerConfig->getPaymentMethodsThumbnails(),
                'intl' => $this->countryConfigs['intl'],
                'currency' => $this->countryConfigs['currency'],
                'payment_methods_list_text' => $this->storeTranslations['payment_methods_list_text'],
                'payment_methods_list_alt_text' => $this->storeTranslations['payment_methods_list_alt_text'],
                'last_digits_text' => $this->storeTranslations['last_digits_text'],
                'new_card_text' => $this->storeTranslations['new_card_text'],
                'account_money_text' => $this->storeTranslations['locale'] === 'en-US'
                    ? $this->storeTranslations['account_money_text'] . ' in Mercado&nbsp;Pago'
                    : $this->storeTranslations['account_money_text'],
                'account_money_wallet_with_investment_text' => $this->storeTranslations['account_money_wallet_with_investment_text'],
                'account_money_wallet_text' => $this->storeTranslations['account_money_wallet_text'],
                'account_money_investment_text' => $this->storeTranslations['account_money_investment_text'],
                'account_money_available_text' => $this->storeTranslations['account_money_available_text'],
                'interest_free_part_one_text' => $this->storeTranslations['interest_free_part_one_text'],
                'interest_free_part_two_text' => $this->storeTranslations['interest_free_part_two_text'],
                'interest_free_option_text' => $this->storeTranslations['interest_free_option_text'],
                'security_code_input_title_text' => $this->storeTranslations['security_code_input_title_text'],
                'security_code_placeholder_text_3_digits' => $this->storeTranslations['security_code_placeholder_text_3_digits'],
                'security_code_placeholder_text_4_digits' => $this->storeTranslations['security_code_placeholder_text_4_digits'],
                'security_code_tooltip_text_3_digits' => $this->storeTranslations['security_code_tooltip_text_3_digits'],
                'security_code_tooltip_text_4_digits' => $this->storeTranslations['security_code_tooltip_text_4_digits'],
                'security_code_error_message_text' => $this->storeTranslations['security_code_error_message_text'],
                'input_title' => [
                    'installments' => $this->storeTranslations['card_installments_label'],
                ],
                'placeholders' => [
                    'issuer' => $this->storeTranslations['placeholders_issuer'],
                    'installments' => $this->storeTranslations['placeholders_installments'],
                    'cardExpirationDate' => $this->storeTranslations['placeholders_card_expiration_date'],
                ],
                'input_helper_message' => [
                    'installments' => [
                        'required' => $this->storeTranslations['installments_required'],
                        'interest_free_option_text' => $this->storeTranslations['interest_free_option_text'],
                        'bank_interest_hint_text' => $this->storeTranslations['card_installments_interest_text'],
                    ],
                    'securityCode' => [
                        'invalid_type' => $this->storeTranslations['input_helper_message_security_code_invalid_type'],
                        'invalid_length' => $this->storeTranslations['input_helper_message_security_code_invalid_length'],
                    ],
                ],
                'mercado_pago_card_name' => $this->storeTranslations['mercado_pago_card_name'],
                'mercado_pago_credit_card_name' => $this->storeTranslations['mercado_pago_credit_card_name'],
                'consumer_credits_due_date' => $this->storeTranslations['consumer_credits_due_date'],
                'months_abbreviated' => $this->storeTranslations['months_abbreviated'],
                'mlb_installment_debit_auto_text' => $this->storeTranslations['mlb_installment_debit_auto_text'],
                'interest_rate_mlb_text' => $this->storeTranslations['interest_rate_mlb_text'],
                'effective_total_cost_mlb_text' => $this->storeTranslations['effective_total_cost_mlb_text'],
                'iof_mlb_text' => $this->storeTranslations['iof_mlb_text'],
                'borrowed_amount_mlb_text' => $this->storeTranslations['borrowed_amount_mlb_text'],
                'per_month' => $this->storeTranslations['per_month'],
                'per_year' => $this->storeTranslations['per_year'],
                'cat_mlm_text' => $this->storeTranslations['cat_mlm_text'],
                'no_iva_text' => $this->storeTranslations['no_iva_text'],
                'tna_mlm_text' => $this->storeTranslations['tna_mlm_text'],
                'system_amortization_mlm_text' => $this->storeTranslations['system_amortization_mlm_text'],
                'cftea_mla_text' => $this->storeTranslations['cftea_mla_text'],
                'tna_mla_text' => $this->storeTranslations['tna_mla_text'],
                'tea_mla_text' => $this->storeTranslations['tea_mla_text'],
                'fixed_rate_text' => $this->storeTranslations['fixed_rate_text'],
                'mercadopago_privacy_policy' => str_replace(
                    '{link}',
                    $this->mercadopago->helpers->links->getPrivacyPolicyLink($this->countryConfigs['site_id']),
                    $this->storeTranslations['mercadopago_privacy_policy']
                ),
                'update_security_code_with_retry_error_text' => $this->storeTranslations['update_security_code_with_retry_error_text'],
                'update_security_code_no_retry_error_text' => $this->storeTranslations['update_security_code_no_retry_error_text'],
                'authorize_payment_method_with_retry_error_text' => $this->storeTranslations['authorize_payment_method_with_retry_error_text'],
                'authorize_payment_method_no_retry_error_text' => $this->storeTranslations['authorize_payment_method_no_retry_error_text'],
                'select_payment_method_error_text' => $this->storeTranslations['select_payment_method_error_text'],
                'platform_id' => MP_PLATFORM_ID,
                'public_key' => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
            ];
    }


    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/checkouts/custom-checkout.php',
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
            'test_mode' => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title' => $this->storeTranslations['test_mode_title'],
            'test_mode_description' => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text' => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src' => $this->links['docs_integration_test'],
            'wallet_button_enabled' => $this->getWalletButtonEnabled(),
            'wallet_button_image' => $this->mercadopago->helpers->url->getImageAsset('gateways/wallet-button/logo.svg'),
            'wallet_button_title' => $this->storeTranslations['wallet_button_title'],
            'site_id' => $this->mercadopago->sellerConfig->getSiteId() ?: $this->mercadopago->helpers->country::SITE_ID_MLA,
            'card_number_input_label' => $this->storeTranslations['card_number_input_label'],
            'card_number_input_helper' => $this->storeTranslations['card_number_input_helper'],
            'card_holder_name_input_label' => $this->storeTranslations['card_holder_name_input_label'],
            'card_holder_name_input_helper' => $this->storeTranslations['card_holder_name_input_helper'],
            'card_expiration_input_label' => $this->storeTranslations['card_expiration_input_label'],
            'card_expiration_input_helper' => $this->storeTranslations['card_expiration_input_helper'],
            'card_security_code_input_label' => $this->storeTranslations['card_security_code_input_label'],
            'card_security_code_input_helper' => $this->storeTranslations['card_security_code_input_helper'],
            'card_document_input_label' => $this->storeTranslations['card_document_input_label'],
            'card_input_document_helper_empty' => $this->storeTranslations['card_document_input_helper_empty'],
            'card_input_document_helper_invalid' => $this->storeTranslations['card_document_input_helper_invalid'],
            'card_input_document_helper_wrong' => $this->storeTranslations['card_document_input_helper_wrong'],
            'card_issuer_input_label' => $this->storeTranslations['card_issuer_input_label'],
            'card_installments_label' => $this->storeTranslations['card_installments_label'],
            'amount' => $amountAndCurrencyRatio['amount'],
            'currency_ratio' => $amountAndCurrencyRatio['currencyRatio'],
            'message_error_amount' => $this->storeTranslations['message_error_amount'],
            'security_code_tooltip_text_3_digits' => $this->storeTranslations['security_code_tooltip_text_3_digits'],
            'placeholders_cardholder_name' => $this->storeTranslations['placeholders_cardholder_name'],
            'cardFlagIconUrls' => array_map(
                fn($icon) => $this->mercadopago->helpers->url->getImageAsset("checkouts/custom/card-flags/$icon"),
                static::CARD_FLAGS_BY_COUNTRY[$this->mercadopago->sellerConfig->getSiteId()] ?? []
            ),
            'card_holder_input_helper_info' => $this->storeTranslations['card_holder_input_helper_info'],
            'mercadopago_privacy_policy' => str_replace(
                '{link}',
                $this->mercadopago->helpers->links->getPrivacyPolicyLink($this->countryConfigs['site_id']),
                $this->storeTranslations['mercadopago_privacy_policy']
            ),
            'installments_required_message' => $this->storeTranslations['installments_required'],
            'interest_free_option_text' => $this->storeTranslations['interest_free_option_text'],
            'bank_interest_hint_text' => $this->storeTranslations['card_installments_interest_text'],
        ];
    }


    public function proccessPaymentInternal($order): array
    {
        $checkout = $this->getCheckoutFormData($order);

        switch ($checkout['checkout_type']) {
            case 'wallet_button':
                $this->paymentMethodName = 'woo-mercado-pago-wallet-button';
                $this->mercadopago->logs->file->info('Preparing to render wallet button checkout', self::LOG_SOURCE);

                return [
                    'result' => 'success',
                    'redirect' => $this->mercadopago->helpers->url->setQueryVar(
                        'wallet_button',
                        'autoOpen',
                        $order->get_checkout_payment_url(true)
                    ),
                ];

            case 'super_token':
                $this->paymentMethodName = 'woo-mercado-pago-super-token';
                $this->mercadopago->logs->file->info('Preparing to get response of custom super token checkout', self::LOG_SOURCE);

                $requiredFields = ['authorized_pseudotoken', 'amount', 'payment_method_id', 'payment_type_id'];
                $missingFields = array_filter($requiredFields, fn($field) => empty($checkout[$field] ?? null));

                $isCreditCard = ($checkout['payment_type_id'] ?? '') === 'credit_card';
                if ($isCreditCard && (empty($checkout['installments']) || $checkout['installments'] <= 0)) {
                    $missingFields[] = 'installments_required_for_credit';
                }

                if (empty($missingFields)) {
                    $checkout['super_token_validation'] = $checkout['super_token_validation'] ?? false;

                    $this->transaction = new SupertokenTransaction($this, $order, $checkout);
                    $flowId = $this->transaction->getCheckoutSessionData()['_mp_flow_id'] ?? 'Unknown';
                    $checkoutCustomToken = $checkout['token'] ?? null;
                    $authorizedPseudotoken = $checkout['authorized_pseudotoken'] ?? null;

                    if ($authorizedPseudotoken !== $checkoutCustomToken) {
                        $this->datadog->sendEvent(
                            'authorized_pseudotoken_mismatch',
                            $checkoutCustomToken,
                            $authorizedPseudotoken,
                            'super_token',
                            [
                                'site_id' => $this->mercadopago->sellerConfig->getSiteId(),
                                'environment' => $this->mercadopago->storeConfig->isTestMode() ? 'homol' : 'prod',
                                'cust_id' => $this->mercadopago->sellerConfig->getCustIdFromAT(),
                                'sdk_instance_id' => $flowId,
                            ]
                        );
                    }

                    if ($checkout['super_token_validation'] === 'false') {
                        $this->datadog->sendEvent(
                            'super_token_validation_failed',
                            'true',
                            'INCOMPLETE_SUPER_TOKEN_VALIDATION',
                            'super_token',
                            [
                                'site_id' => $this->mercadopago->sellerConfig->getSiteId(),
                                'environment' => $this->mercadopago->storeConfig->isTestMode() ? 'homol' : 'prod',
                                'cust_id' => $this->mercadopago->sellerConfig->getCustIdFromAT(),
                                'sdk_instance_id' => $flowId,
                            ]
                        );
                    }

                    try {
                        $response = $this->transaction->createPayment();
                    } catch (ApiException $e) {
                        $errorCode = $this->mercadopago->helpers->errorMessages->findCodeInOriginalMessage($e->getOriginalMessage())
                            ?? $e->getErrorCode()
                            ?? $e->getMessage();

                        return $this->processReturnFail(
                            $e,
                            $errorCode,
                            self::LOG_SOURCE,
                            [],
                            true
                        );
                    }

                    $this->mercadopago->orderMetadata->setSupertokenMetadata($order, $response, $this->transaction->getInternalMetadata());
                    return $this->handleResponseStatus($order, $response);
                }

                throw new InvalidCheckoutDataException(
                    'exception : Unable to process payment on ' . __METHOD__,
                    0,
                    null,
                    [
                        'missing_fields'  => implode(',', array_values($missingFields)),
                        'payment_type_id' => $checkout['payment_type_id'] ?? 'unknown',
                    ]
                );

            default:
                $this->mercadopago->logs->file->info('Preparing to get response of custom checkout', self::LOG_SOURCE);

                $requiredFields = ['token', 'amount', 'payment_method_id', 'installments'];
                $missingFields = array_filter($requiredFields, fn($field) => empty($checkout[$field] ?? null));

                if (empty($missingFields)) {
                    $this->transaction = new CustomTransaction($this, $order, $checkout);
                    $response = $this->transaction->createPayment();

                    $this->mercadopago->orderMetadata->setCustomMetadata($order, $response);
                    return $this->handleResponseStatus($order, $response);
                }

                throw new InvalidCheckoutDataException(
                    'exception : Unable to process payment on ' . __METHOD__,
                    0,
                    null,
                    [
                        'missing_fields' => implode(',', array_values($missingFields)),
                    ]
                );
        }
    }

    /**
     * Get checkout mercadopago custom
     *
     * @param $order
     *
     * @return array
     */
    protected function getCheckoutFormData($order): array
    {
        if (isset($_POST['mercadopago_custom'])) {
            $checkout = Form::sanitizedPostData('mercadopago_custom');
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
        } else {
            $checkout = $this->processBlocksCheckoutData('mercadopago_custom', Form::sanitizedPostData());
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
        }

        return $checkout;
    }

    /**
     * Generating Wallet Button preview component
     *
     * @return string
     */
    public function getWalletButtonPreview(): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/preview.php',
            [
                'settings' => [
                    'url' => $this->getWalletButtonPreviewUrl(),
                    'description' => $this->adminTranslations['wallet_button_preview_description'],
                ],
            ]
        );
    }

    /**
     * Get wallet button preview url
     *
     * @return string
     */
    private function getWalletButtonPreviewUrl(): string
    {
        $locale = strtolower($this->storeTranslations['locale']);

        return $this->mercadopago->helpers->url->getImageAsset(
            'gateways/wallet-button/preview-' . $locale,
        );
    }

    /**
     * Render order form
     *
     * @param $orderId
     *
     * @return void
     * @throws Exception
     */
    public function renderOrderForm($orderId): void
    {
        if ($this->mercadopago->helpers->url->validateQueryVar('wallet_button')) {
            $order = wc_get_order($orderId);

            $this->transaction = new WalletButtonTransaction($this, $order);

            $preference = $this->transaction->createPreference();

            $this->mercadopago->hooks->template->getWoocommerceTemplate(
                'public/receipt/preference-modal.php',
                [
                    'public_key' => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
                    'preference_id' => $preference['id'],
                    'pay_with_mp_title' => $this->storeTranslations['wallet_button_order_receipt_title'],
                    'cancel_url' => $order->get_cancel_order_url(),
                    'cancel_url_text' => $this->storeTranslations['cancel_url_text'],
                ]
            );
        }
    }

    /**
     * Render thank you page
     *
     * @param $order_id
     */
    public function renderInstallmentsRateDetails($order_id): void
    {
        $order = wc_get_order($order_id);
        $currency = $this->countryConfigs['currency_symbol'];
        $installments = (float) $this->mercadopago->orderMetadata->getInstallmentsMeta($order);
        $installmentAmount = $this->mercadopago->orderMetadata->getTransactionDetailsMeta($order);
        $transactionAmount = Numbers::makesValueSafe($this->mercadopago->orderMetadata->getTransactionAmountMeta($order));
        $totalPaidAmount = Numbers::makesValueSafe($this->mercadopago->orderMetadata->getTotalPaidAmountMeta($order));
        $totalDiffCost = $totalPaidAmount - $transactionAmount;

        if ($totalDiffCost > 0) {
            $this->mercadopago->hooks->template->getWoocommerceTemplate(
                'public/order/custom-order-received.php',
                [
                    'title_installment_cost' => $this->storeTranslations['title_installment_cost'],
                    'title_installment_total' => $this->storeTranslations['title_installment_total'],
                    'text_installments' => $this->storeTranslations['text_installments'],
                    'total_paid_amount' => Numbers::formatWithCurrencySymbol($currency, $totalPaidAmount),
                    'transaction_amount' => Numbers::formatWithCurrencySymbol($currency, $transactionAmount),
                    'total_diff_cost' => Numbers::formatWithCurrencySymbol($currency, $totalDiffCost),
                    'installment_amount' => Numbers::formatWithCurrencySymbol($currency, $installmentAmount),
                    'installments' => Numbers::format($installments),
                ]
            );
        }
    }

    /**
     * Handle with response status
     * The order_pay page always redirect the requester, so we must stop the current execution to return a JSON.
     * See mp-custom-checkout.js to understand how to handle the return.
     *
     * @param $return
     */
    private function handlePayForOrderRequest($return)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json;');
        }

        echo wp_json_encode($return);

        if (!getenv('PHPUNIT_TEST')) {
            die();
        }
    }

    /**
     * Override processReturnFail to return JSON on the Order Pay page.
     * Without this, WooCommerce's WC_Form_Handler::pay_action() would receive the
     * fail array and redirect, causing the AJAX caller to get HTML instead of JSON.
     */
    public function processReturnFail(Exception $e, string $message, string $source, array $context = [], bool $notice = false): array
    {
        $result = parent::processReturnFail($e, $message, $source, $context, $notice);

        if ($this->isOrderPayPage()) {
            // Forward the resolved $result['message'] when the error carries a specific code — either a
            // top-level errorCode, or one embedded only in original_message (e.g. pseudotoken_payment_method_gone,
            // which arrives with a null/generic errorCode). The errorCode check comes first so original_message is
            // only scanned when needed. Otherwise fall back to the generic buyer message.
            $hasResolvableCode = $e instanceof ApiException
                && ($e->getErrorCode() !== null
                    || $this->mercadopago->helpers->errorMessages->findCodeInOriginalMessage($e->getOriginalMessage()) !== null);

            $messages = $hasResolvableCode
                ? $result['message']
                : ($this->mercadopago->storeTranslations->buyerRefusedMessages[
                    $this->getRejectedPaymentErrorKey(
                        $e instanceof RejectedPaymentException ? $e->getStatusDetail() : $this->storeTranslations['default_error_message']
                    )
                ] ?? $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default']);

            $this->handlePayForOrderRequest([
                'result'   => 'fail',
                'redirect' => false,
                'messages' => $messages,
            ]);
        }

        return $result;
    }

    /**
     * Check if there is a pay_for_order query param.
     * This indicates that the user is on the Order Pay Checkout page.
     *
     * @return bool
     */
    private function isOrderPayPage(): bool
    {
        return $this->mercadopago->helpers->url->validateGetVar('pay_for_order');
    }

    /**
     * Handle with response status
     *
     * @param $order
     * @param $response
     *
     * @return array
     */
    private function handleResponseStatus($order, array $response): array
    {
        try {
            if (array_key_exists('status', $response)) {
                switch ($response['status']) {
                    case 'approved':
                        $this->mercadopago->helpers->cart->emptyCart();

                        $urlReceived = $order->get_checkout_order_received_url();
                        $orderStatus = $this->mercadopago->orderStatus->getOrderStatusMessage('accredited');

                        $this->mercadopago->helpers->notices->storeApprovedStatusNotice($orderStatus);
                        $this->mercadopago->orderStatus->setOrderStatus($order, 'failed', 'pending');

                        $return = [
                            'result' => 'success',
                            'redirect' => $urlReceived,
                        ];

                        if ($this->isOrderPayPage()) {
                            $this->handlePayForOrderRequest($return);
                        }

                        return $return;

                    case 'pending':
                    case 'in_process':
                        if (
                            $response['status_detail'] === 'pending_challenge'
                            && !empty($response['three_ds_info']['external_resource_url'])
                            && !empty($response['three_ds_info']['creq'])
                        ) {
                            $this->mercadopago->helpers->session->setSession('mp_3ds_url', $response['three_ds_info']['external_resource_url']);
                            $this->mercadopago->helpers->session->setSession('mp_3ds_creq', $response['three_ds_info']['creq']);
                            $this->mercadopago->helpers->session->setSession('mp_order_id', $order->get_id());
                            $this->mercadopago->helpers->session->setSession('mp_payment_id', $response['id']);
                            $lastFourDigits = (empty($response['card']['last_four_digits'])) ? '****' : $response['card']['last_four_digits'];

                            $return = [
                                'result' => 'success',
                                'three_ds_flow' => true,
                                'last_four_digits' => $lastFourDigits,
                                'redirect' => false,
                                'messages' => '<script>window.mpCustomCheckoutHandler.threeDSHandler.load3DSFlow(' . $lastFourDigits . ')</script>',
                            ];

                            if ($this->isOrderPayPage()) {
                                $this->handlePayForOrderRequest($return);
                            }

                            return $return;
                        }

                        $this->mercadopago->helpers->cart->emptyCart();

                        $urlReceived = $order->get_checkout_order_received_url();

                        $return = [
                            'result' => 'success',
                            'redirect' => $urlReceived,
                        ];

                        if ($this->isOrderPayPage()) {
                            $this->handlePayForOrderRequest($return);
                        }

                        return $return;

                    case 'rejected':
                        if ($this->isOrderPayPage()) {
                            $this->handlePayForOrderRequest([
                                'result' => 'fail',
                                'messages' => $this->mercadopago->storeTranslations->buyerRefusedMessages[
                                $this->getRejectedPaymentErrorKey($response['status_detail'])
                            ] ?? $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default']
                            ]);
                            return []; // Case $_ENV['PHPUNIT_TEST'] == true
                        }

                        $this->handleWithRejectPayment($response);
                        break;
                    // Fall-through intentional - throw RejectedPaymentException for 'rejected' case.

                    default:
                        break;
                }
            }
            throw new ResponseStatusException('exception: Response status not mapped on ' . __METHOD__);
        } catch (Exception $e) {
            return $this->processReturnFail($e, $e->getMessage(), self::LOG_SOURCE, (array) $response, true);
        }
    }

    /**
     * Register installments fee on admin order totals
     *
     * @param int $orderId
     *
     * @return void
     */
    public function registerInstallmentsFeeOnAdminOrder(int $orderId): void
    {
        $order = wc_get_order($orderId);

        $currency = $this->mercadopago->helpers->currency->getCurrencySymbol();
        $usedGateway = $this->mercadopago->orderMetadata->getUsedGatewayData($order);

        if ($this::ID === $usedGateway) {
            $totalPaidAmount = Numbers::format(Numbers::makesValueSafe($this->mercadopago->orderMetadata->getTotalPaidAmountMeta($order)));
            $transactionAmount = Numbers::format(Numbers::makesValueSafe($this->mercadopago->orderMetadata->getTransactionAmountMeta($order)));
            $installmentsFeeAmount = $totalPaidAmount - $transactionAmount;

            if ($installmentsFeeAmount > 0) {
                $this->mercadopago->hooks->template->getWoocommerceTemplate(
                    'admin/order/generic-note.php',
                    [
                        'tip' => $this->mercadopago->adminTranslations->order['order_note_installments_fee_tip'],
                        'title' => $this->mercadopago->adminTranslations->order['order_note_installments_fee_title'],
                        'value' => Numbers::formatWithCurrencySymbol($currency, $installmentsFeeAmount),
                    ]
                );

                $this->mercadopago->hooks->template->getWoocommerceTemplate(
                    'admin/order/generic-note.php',
                    [
                        'tip' => $this->mercadopago->adminTranslations->order['order_note_total_paid_amount_tip'],
                        'title' => $this->mercadopago->adminTranslations->order['order_note_total_paid_amount_title'],
                        'value' => Numbers::formatWithCurrencySymbol($currency, $totalPaidAmount),
                    ]
                );
            }
        }
    }

    /**
     * Is wallet button enabled?
     * @return bool
     */
    public function getWalletButtonEnabled(): bool
    {
        return $this->getEnabled() && $this->get_option(static::WALLET_BUTTON_ENABLED_OPTION, static::WALLET_BUTTON_ENABLED_DEFAULT) === "yes";
    }
}
