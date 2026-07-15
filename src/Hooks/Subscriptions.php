<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers as WCHelpers;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Helpers\WebhookUrl;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers and handles WooCommerce Subscriptions hook callbacks for the
 * Mercado Pago Custom gateway (Automatic Payments v2).
 *
 * Responsibility boundary:
 *   - This class owns every callback triggered by a WCS hook (`add_action`).
 *   - Payment operations that enter through WooCommerce's `process_payment()`
 *     (CIT, payment-method change) are handled in CustomGateway, not here.
 *
 * Two WCS hooks are registered via {@see registerHooks()}:
 *   - `woocommerce_scheduled_subscription_payment_*` → {@see processSubscriptionRenewal()}
 *   - `woocommerce_subscription_cancelled_*`         → {@see onSubscriptionCancelled()}
 *
 * Admin notices for MIT errors are queued via the public-static helpers on
 * this class and displayed via {@see displayAdminNotices()}.
 *
 * @package MercadoPago\Woocommerce\Hooks
 */
class Subscriptions
{
    public const OPTION_NOTICES = 'woocommerce_mercadopago_subscriptions_admin_notices';

    private const GATEWAY_ID = 'woo-mercado-pago-custom';

    private AutomaticPaymentsClient $apClient;

    private SubscriptionsHelper $subscriptionsHelper;

    private Store $store;

    private Logs $logs;

    private WCHelpers $helpers;

    private OrderMetadata $orderMetadata;

    /**
     * @param AutomaticPaymentsClient $apClient
     * @param SubscriptionsHelper     $subscriptionsHelper
     * @param Store                   $store
     * @param Logs                    $logs
     * @param WCHelpers               $helpers
     * @param OrderMetadata           $orderMetadata
     */
    public function __construct(
        AutomaticPaymentsClient $apClient,
        SubscriptionsHelper $subscriptionsHelper,
        Store $store,
        Logs $logs,
        WCHelpers $helpers,
        OrderMetadata $orderMetadata
    ) {
        $this->apClient            = $apClient;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->store               = $store;
        $this->logs                = $logs;
        $this->helpers             = $helpers;
        $this->orderMetadata       = $orderMetadata;

        if (is_admin()) {
            add_action('admin_notices', [$this, 'displayAdminNotices']);
        }

        if (SubscriptionsHelper::isWcsActive()) {
            $this->registerHooks();
        }
    }

    /**
     * Registers all WCS hook callbacks for this gateway.
     *
     * Centralises `add_action` calls so the constructor stays readable and
     * the full list of WCS integration points is visible in one place.
     */
    private function registerHooks(): void
    {
        add_action(
            'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID,
            [$this, 'processSubscriptionRenewal'],
            10,
            2
        );

        add_action(
            'woocommerce_subscription_cancelled_' . self::GATEWAY_ID,
            [$this, 'onSubscriptionCancelled'],
            10,
            1
        );
    }

    /**
     * Handles the WCS scheduled renewal (Fluxo B — MIT).
     *
     * Flow:
     *   1. Resolve the parent subscription and its `_mp_subscription_id`.
     *   2. If missing → CRITICAL log + orphan admin notice + fail renewal.
     *   3. Build MIT payload, call `mit()`, inspect result.
     *   4. Approved → call `payment_complete()`.
     *   5. 401/403 → fail renewal + credential-revoked admin notice.
     *   6. Transport/SDK exception → log + fail renewal; WCS handles retry.
     *   7. Any other non-success → fail renewal; WCS handles retry.
     *
     * @param float    $amount       Renewal amount (injected by WCS).
     * @param WC_Order $renewalOrder WCS-created renewal order.
     */
    public function processSubscriptionRenewal(float $amount, WC_Order $renewalOrder): void
    {
        $subscriptions = function_exists('wcs_get_subscriptions_for_renewal_order')
            ? wcs_get_subscriptions_for_renewal_order($renewalOrder)
            : [];

        $subscription   = reset($subscriptions) ?: null;
        $subscriptionId = $subscription
            ? (string) $this->subscriptionsHelper->getSubscriptionMeta($subscription, '_mp_subscription_id', '')
            : '';

        if ($subscriptionId === '') {
            $this->logs->file->error(
                'op=mit subscription_id_missing renewal_order_id=' . $renewalOrder->get_id(),
                AutomaticPaymentsClient::LOG_SOURCE
            );
            $renewalOrder->update_status(
                'failed',
                __('Mercado Pago: subscription data incomplete — no subscription ID found.', 'woocommerce-mercadopago')
            );
            self::queueOrphanNotice((int) $renewalOrder->get_id());
            return;
        }

        $accessToken = $this->subscriptionsHelper->resolveAccessToken($this->store);
        if ($accessToken === '') {
            $this->logs->file->error(
                'op=mit step=abort reason=missing_access_token renewal_order_id=' . $renewalOrder->get_id(),
                AutomaticPaymentsClient::LOG_SOURCE
            );
            $renewalOrder->update_status(
                'failed',
                __('Mercado Pago: subscription access token not configured.', 'woocommerce-mercadopago')
            );
            return;
        }

        $seed           = $this->subscriptionsHelper->buildMitSeed($renewalOrder);
        $idempotencyKey = $this->subscriptionsHelper->generateIdempotencyKey($seed);

        try {
            $payload = $this->buildMitPayload($subscription, $renewalOrder, $subscriptionId, $amount);
        } catch (\Exception $e) {
            $this->logs->file->error(
                'op=mit step=ratio_fetch_failed renewal_order_id=' . $renewalOrder->get_id()
                    . ' error=' . str_replace(["\r", "\n"], ' ', $e->getMessage()),
                AutomaticPaymentsClient::LOG_SOURCE
            );
            try {
                Datadog::getInstance()->sendEvent('MP_CUSTOM_MIT_ERROR', '1', 'ratio_fetch_failed');
            } catch (\Throwable $ignored) {
                // metric failure must never block renewal error handling
            }
            $renewalOrder->update_status(
                'failed',
                __('Mercado Pago: renewal failed — could not determine conversion rate. WCS will retry.', 'woocommerce-mercadopago')
            );
            return;
        }

        try {
            $result = $this->apClient->mit($accessToken, $payload, $idempotencyKey);
        } catch (\Exception $e) {
            $this->logs->file->error(
                'op=mit transport_error renewal_order_id=' . $renewalOrder->get_id()
                    . ' error=' . str_replace(["\r", "\n"], ' ', $e->getMessage()),
                AutomaticPaymentsClient::LOG_SOURCE
            );
            $renewalOrder->update_status(
                'failed',
                __('Mercado Pago: renewal failed due to a connectivity error. WCS will retry.', 'woocommerce-mercadopago')
            );
            return;
        }

        if ($result['credential_revoked']) {
            $renewalOrder->update_status(
                'failed',
                __('Mercado Pago: subscription credentials invalid or revoked.', 'woocommerce-mercadopago')
            );
            self::queueCredentialRevokedNotice();
            return;
        }

        $paymentStatus = $result['data']['payment']['status'] ?? '';
        $paymentId     = (string) ($result['data']['payment']['id'] ?? '');

        if ($result['status'] >= 200 && $result['status'] < 300) {
            if ($paymentStatus === 'approved') {
                // Must precede payment_complete: RefundHandler and the Sync button read
                // _Mercado_Pago_Payment_IDs synchronously, before any webhook arrives.
                // setCustomMetadata handles missing fields gracefully and calls $renewalOrder->save().
                $this->orderMetadata->setCustomMetadata($renewalOrder, $result['data']['payment'] ?? []);
                // Overwrite _Mercado_Pago_Payment_IDs unconditionally: each renewal is a new charge.
                // setCustomMetadata only writes when the key is empty, so stale entries from a
                // previous retry would not be replaced without this explicit overwrite.
                if ($paymentId !== '') {
                    $renewalOrder->update_meta_data('_Mercado_Pago_Payment_IDs', $paymentId);
                }
                $renewalOrder->payment_complete($paymentId);
            } elseif (in_array($paymentStatus, ['in_process', 'pending'], true)) {
                // Payment is async (e.g. bank transfer); webhook will complete it.
                // Leave order on-hold to avoid triggering WCS dunning prematurely.
                $renewalOrder->update_status(
                    'on-hold',
                    __('Mercado Pago: awaiting payment confirmation.', 'woocommerce-mercadopago')
                );
            } else {
                $renewalOrder->update_status(
                    'failed',
                    sprintf('Mercado Pago: renewal payment status=%s', $paymentStatus)
                );
            }
            return;
        }

        // Non-2xx: fail renewal; WCS schedules retry.
        // Error payloads carry no data.payment.status, so log the HTTP status and
        // the AP v2 error code for diagnostic value instead of an empty status.
        $renewalOrder->update_status(
            'failed',
            sprintf(
                'Mercado Pago: renewal failed — http_status=%d error=%s',
                $result['status'],
                $result['data']['error'] ?? ''
            )
        );
    }

    /**
     * Handles the WCS subscription cancelled event (Fluxo 4 — AP cancel).
     *
     * - 204 → success.
     * - 404 → silent ok (subscription already gone in AP).
     * - Transport/SDK exception → log + continue; WCS cancellation is NOT blocked.
     * - Any other HTTP error → log + continue; WCS cancellation is NOT blocked.
     * - Metadata (`_mp_subscription_id`, `_mp_active_card_*`) is NOT removed.
     *
     * @param mixed $subscription WC_Subscription (only present when WCS is active).
     */
    public function onSubscriptionCancelled($subscription): void
    {
        // WCS fires this hook during payment-method changes (status is still active/on-hold).
        // Only proceed when the subscription is truly cancelled.
        if (!method_exists($subscription, 'get_status') || $subscription->get_status() !== 'cancelled') {
            return;
        }

        $subscriptionId = (string) $this->subscriptionsHelper->getSubscriptionMeta(
            $subscription,
            '_mp_subscription_id',
            ''
        );

        if ($subscriptionId === '') {
            $this->logs->file->warning(
                'op=subscription-cancel subscription_id_missing',
                AutomaticPaymentsClient::LOG_SOURCE
            );
            return;
        }

        $accessToken = $this->subscriptionsHelper->resolveAccessToken($this->store);
        if ($accessToken === '') {
            $this->logs->file->warning(
                'op=subscription-cancel step=abort reason=missing_access_token subscription_id=' . $subscriptionId,
                AutomaticPaymentsClient::LOG_SOURCE
            );
            // WCS cancellation proceeds; AP call is skipped.
            return;
        }

        try {
            $result = $this->apClient->deleteSubscription($accessToken, $subscriptionId);
        } catch (\Exception $e) {
            $this->logs->file->error(
                'op=subscription-cancel transport_error subscription_id=' . $subscriptionId
                    . ' error=' . str_replace(["\r", "\n"], ' ', $e->getMessage()),
                AutomaticPaymentsClient::LOG_SOURCE
            );
            // WCS cancellation is NOT blocked by transport failures.
            return;
        }

        if ($result['success'] || $result['not_found']) {
            return;
        }

        if ($result['status'] === 401 || $result['status'] === 403) {
            self::queueCredentialRevokedNotice();
        }
    }

    /**
     * Renders any queued subscription admin notices in the WP admin panel.
     */
    public function displayAdminNotices(): void
    {
        $notices = (array) get_option(self::OPTION_NOTICES, []);

        if (!empty($notices['credential_revoked'])) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(__(
                    'Your Pre-approval credentials are invalid or lost scope. Subscription renewals are failing. Go to Settings > Recurring Payments to reconfigure.',
                    'woocommerce-mercadopago'
                ))
            );
        }

        $orphanCount = count((array) ($notices['orphan_orders'] ?? []));
        if ($orphanCount > 0) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: %d: number of subscriptions with incomplete data */
                    __('We found %d subscriptions with incomplete data. Renewals for these subscriptions will not work. Contact support.', 'woocommerce-mercadopago'),
                    $orphanCount
                ))
            );
        }
    }

    /**
     * Clears the credential-revoked admin notice.
     *
     * Called by CustomGateway when the admin saves valid Pre-approval credentials.
     */
    public static function clearCredentialRevokedNotice(): void
    {
        $notices = (array) get_option(self::OPTION_NOTICES, []);
        if (isset($notices['credential_revoked'])) {
            unset($notices['credential_revoked']);
            update_option(self::OPTION_NOTICES, $notices, false);
        }
    }

    /**
     * Queues a credential-revoked admin notice (persistent until admin reconfigures token).
     */
    public static function queueCredentialRevokedNotice(): void
    {
        $notices                       = (array) get_option(self::OPTION_NOTICES, []);
        $notices['credential_revoked'] = true;
        update_option(self::OPTION_NOTICES, $notices, false);
    }

    /**
     * Registers a renewal order as orphaned (subscription ID missing).
     *
     * Uses a Set keyed by renewal order ID so WCS automatic retries on the
     * same order do not inflate the count — each unique orphan is counted once.
     *
     * @param int $renewalOrderId ID of the renewal order that detected the orphan.
     */
    public static function queueOrphanNotice(int $renewalOrderId): void
    {
        $notices                       = (array) get_option(self::OPTION_NOTICES, []);
        $orphanOrders                  = (array) ($notices['orphan_orders'] ?? []);
        $orphanOrders[$renewalOrderId] = true;
        $notices['orphan_orders']      = $orphanOrders;
        update_option(self::OPTION_NOTICES, $notices, false);
    }

    /**
     * Builds the MIT request payload from the WCS subscription and renewal order.
     *
     * No `token`, `payer`, `device`, `additional_info`, or `three_d_secure_mode`
     * — those were persisted by Core P&P during the CIT. MIT is stateless from
     * the plugin side.
     *
     * @param mixed    $subscription   WC_Subscription with `_mp_subscription_id` meta.
     * @param WC_Order $renewalOrder   WCS renewal order.
     * @param string   $subscriptionId AP subscription identifier.
     *
     * @return array<string, mixed>
     */
    private function buildMitPayload($subscription, WC_Order $renewalOrder, string $subscriptionId, float $amount): array
    {
        $mpCurrency      = $this->helpers->country->getCountryConfigs()['currency'];
        $storeCurrency   = $renewalOrder->get_currency();
        $convertedAmount = $amount;

        if ($mpCurrency !== $storeCurrency) {
            $ratio           = $this->helpers->currency->getCurrentRatio();
            $convertedAmount = Numbers::calculateByCurrency($mpCurrency, $amount, $ratio);
            // Persist ratio so RefundHandler can apply the same conversion on refunds,
            // consistent with AbstractGateway::process_payment for CIT.
            $renewalOrder->update_meta_data('_currency_ratio', $ratio);
        }

        $payload = [
            'subscription' => [
                'id'          => $subscriptionId,
                'external_id' => 'WC-SUB-' . $subscription->get_id(),
            ],
            'transaction'  => [
                'amount'               => (float) Numbers::formatByCurrency($mpCurrency, $convertedAmount),
                'currency'             => $mpCurrency,
                'external_reference'   => get_option('_mp_store_identificator', 'WC-') . $renewalOrder->get_id(),
                'description'          => $this->buildOrderDescription($renewalOrder),
                'statement_descriptor' => $this->store->getStoreName('Mercado Pago'),
            ],
        ];

        // Always build: the custom-domain branch does not need WC(); only the fallback
        // resolver depends on it (returns '' when WC() is unavailable in WP-Cron).
        $wc              = WC();
        $notificationUrl = WebhookUrl::build(
            $this->store->getCustomDomain(),
            $this->store->getCustomDomainOptions(),
            fn() => $wc ? $wc->api_request_url(CustomGateway::WEBHOOK_API_NAME) : '',
            get_site_url(),
            CustomGateway::WEBHOOK_API_NAME
        );

        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        } else {
            // Without notification_url the Core P&P cannot deliver the renewal webhook,
            // leaving an async (pending/in_process) renewal stuck on-hold silently.
            $this->logs->file->warning(
                'op=mit notification_url_missing renewal_order_id=' . $renewalOrder->get_id()
                    . ' reason=' . ($wc ? 'no_public_url' : 'wc_not_initialized'),
                AutomaticPaymentsClient::LOG_SOURCE
            );
        }

        return $payload;
    }

    /**
     * Builds a concise description from renewal order line items.
     *
     * @param WC_Order $order
     * @return string
     */
    private function buildOrderDescription(WC_Order $order): string
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name();
        }
        return implode(', ', $items) ?: __('Subscription renewal', 'woocommerce-mercadopago');
    }
}
