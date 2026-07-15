<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SubscriptionsHelper
 *
 * Utilities for the WooCommerce Subscriptions integration with Mercado Pago
 * Automatic Payments (Pre-approval). Centralizes subscription order detection,
 * subscription metadata read/write, deterministic idempotency key generation
 * and the error → buyer-message mapping.
 *
 * @package MercadoPago\Woocommerce\Helpers
 */
class SubscriptionsHelper
{
    /**
     * Lookup key (in StoreTranslations::$subscriptionsErrorMessages)
     * for the catch-all generic message.
     */
    private const I18N_KEY_GENERIC = 'generic';

    /**
     * Lookup key for the HTTP 5xx / unavailable fallback message.
     */
    private const I18N_KEY_UNAVAILABLE = 'http_unavailable';

    /**
     * @var StoreTranslations
     */
    private $storeTranslations;

    /**
     * SubscriptionsHelper constructor.
     *
     * @param StoreTranslations $storeTranslations
     */
    public function __construct(StoreTranslations $storeTranslations)
    {
        $this->storeTranslations = $storeTranslations;
    }

    /**
     * Returns true if the given order contains at least one WooCommerce
     * Subscriptions product.
     *
     * Defensive: returns false (no exception) when WCS is not active —
     * callers can safely branch on the result regardless of whether the
     * Subscriptions plugin is installed.
     *
     * @param mixed $order Expected to be a \WC_Order instance when WC is loaded.
     */
    public function isSubscriptionOrder($order): bool
    {
        if (!is_object($order) || !($order instanceof \WC_Order)) {
            return false;
        }

        if (!\function_exists('wcs_order_contains_subscription')) {
            return false;
        }

        return (bool) \wcs_order_contains_subscription($order, 'any');
    }

    /**
     * Returns true when WooCommerce Subscriptions is installed and active.
     *
     * Single source of truth for the WCS-active check used across the plugin.
     * Gateway classes and hook handlers delegate to this method so the
     * detection logic never gets duplicated.
     */
    public static function isWcsActive(): bool
    {
        return class_exists('WC_Subscriptions')
            && function_exists('wcs_order_contains_subscription');
    }

    /**
     * Returns the active Pre-approval access token for the given store mode.
     *
     * Reads the token stored under the Custom gateway settings option so the
     * value is always in sync with what the admin saved in the Recurring
     * Payments section. Production mode is resolved via {@see Store}.
     *
     * Both CustomGateway (CIT, PM change) and Hooks/Subscriptions (MIT, cancel)
     * delegate here so the option key and mode logic live in one place.
     *
     * @param Store $store Used to determine prod vs sandbox mode.
     */
    public function resolveAccessToken(Store $store): string
    {
        $settings = get_option('woocommerce_woo-mercado-pago-custom_settings', []);
        $key      = $store->isProductionMode()
            ? 'subscriptions_access_token_prod'
            : 'subscriptions_access_token_test';
        return trim((string) ($settings[$key] ?? ''));
    }

    /**
     * Returns the Pre-approval Public Key for the active environment.
     *
     * @param Store $store Used to determine prod vs sandbox mode.
     */
    public function resolvePublicKey(Store $store): string
    {
        $settings = get_option('woocommerce_woo-mercado-pago-custom_settings', []);
        $key      = $store->isProductionMode()
            ? 'subscriptions_public_key_prod'
            : 'subscriptions_public_key_test';
        return trim((string) ($settings[$key] ?? ''));
    }

    /**
     * Reads a meta value from a WC_Subscription (or WC_Order acting as one).
     *
     * @param \WC_Subscription|\WC_Order $subscription
     * @param mixed                      $default
     * @return mixed
     */
    public function getSubscriptionMeta($subscription, string $key, $default = null)
    {
        if (!is_object($subscription) || !method_exists($subscription, 'get_meta')) {
            return $default;
        }

        $value = $subscription->get_meta($key, true);

        if ($value === '' || $value === null) {
            return $default;
        }

        return $value;
    }

    /**
     * Writes a meta value to a WC_Subscription and persists immediately.
     *
     * Silently no-ops if the argument is not a WC object (defensive guard
     * against tests / unexpected call sites without WooCommerce loaded).
     *
     * @param \WC_Subscription|\WC_Order $subscription
     * @param mixed                      $value
     */
    public function setSubscriptionMeta($subscription, string $key, $value): void
    {
        if (!is_object($subscription) || !method_exists($subscription, 'update_meta_data')) {
            return;
        }

        $subscription->update_meta_data($key, $value);
        $subscription->save();
    }

    /**
     * Builds the deterministic seed for the CIT (1st payment) operation.
     *
     * Formula: "cit:{order_id}:{order_date_created_timestamp}:{substr(token,0,16)}"
     *
     * The token prefix distinguishes retries with different cards on the same order:
     * without it, the backend's 24h idempotency window would return a cached 409
     * when the buyer retries with a different card. Mirrors the pattern used by
     * buildAddPaymentMethodSeed() per spec §4.1.1.
     *
     * Falls back to timestamp `0` when the order has no creation date yet
     * (e.g. transient/unsaved order). Token defaults to empty string when absent.
     *
     * @param \WC_Order $order Parent WC order created at checkout.
     * @param string    $token Single-use card token from the MP SDK.
     */
    public function buildCitSeed($order, string $token = ''): string
    {
        return 'cit:' . $order->get_id() . ':' . $this->orderTimestamp($order) . ':' . substr($token, 0, 16);
    }

    /**
     * Builds the deterministic seed for the MIT (renewal) operation.
     *
     * Formula (spec §4.1.1): "mit:{renewal_order_id}:{renewal_order_date_created_timestamp}"
     *
     * Falls back to timestamp `0` when the renewal order has no creation
     * date yet — stays deterministic for any given renewal.
     *
     * @param \WC_Order $renewalOrder Renewal order scheduled by WCS.
     */
    public function buildMitSeed($renewalOrder): string
    {
        return 'mit:' . $renewalOrder->get_id() . ':' . $this->orderTimestamp($renewalOrder);
    }

    /**
     * Builds the deterministic seed for the add-payment-method operation (Fluxo 3a).
     *
     * Formula (spec §4.1.1): "pm-add:{subscription_id}:{substr(token, 0, 16)}"
     *
     * The token is single-use but acts as a stable discriminator for retries
     * within the same change-payment-method attempt.
     */
    public function buildAddPaymentMethodSeed(string $subscriptionId, string $token): string
    {
        return 'pm-add:' . $subscriptionId . ':' . substr($token, 0, 16);
    }

    /**
     * Generates a deterministic UUID-shaped key from a seed string.
     *
     * WARNING: the output is NOT random — the same seed ALWAYS yields the same
     * key. This is intentional: network retries must reuse the Core P&P cached
     * response (24h idempotency window) to prevent double-charges. Using a
     * random generator such as `wp_generate_uuid4()` here would break that
     * guarantee. (spec §4.1.1)
     *
     * The result follows UUID v4 format (xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx)
     * but is derived from SHA-1, not from random bytes.
     *
     * Callers SHOULD use the operation-specific `build*Seed()` helpers above to
     * construct the seed — manually composing the seed string is error-prone
     * and a wrong formula causes 409 `IdempotencyKeyReused` bugs.
     */
    public function generateIdempotencyKey(string $seed): string
    {
        // SHA-1 produces 40 hex chars; we keep 32 to fit a UUID layout.
        $hash = substr(sha1($seed), 0, 32);

        // Force version 4 (position 13) and variant bits (position 17 in [8,9,a,b]).
        $hash[12] = '4';
        $hash[16] = ['8', '9', 'a', 'b'][hexdec($hash[16]) & 0x3];

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    /**
     * Maps a Core P&P error response to a buyer-facing message.
     *
     * Resolution order:
     *   1. Stable code (CPP_TAAP_*) — most specific
     *   2. Symbolic error name (e.g. "PaymentRejected")
     *   3. HTTP status fallback (5xx → unavailable; otherwise generic)
     *   4. Generic catch-all
     *
     * Returns an empty string for silent-success cases (PaymentMethodNotFound,
     * LastPaymentMethod, AlreadyDefault, CannotRemoveDefault) — callers should
     * log internally but show nothing to the buyer.
     *
     * Messages are read from `StoreTranslations::$subscriptionsErrorMessages`,
     * which wraps each entry in `__('...', 'woocommerce-mercadopago')` at
     * definition time.
     *
     * Source: technical spec §4.7.
     */
    public function mapApiErrorToUserMessage(?int $httpStatus, ?string $error, ?string $code = null): string
    {
        $messages = $this->errorMessages();

        if ($code !== null && array_key_exists($code, $messages)) {
            return (string) $messages[$code];
        }

        if ($error !== null && array_key_exists($error, $messages)) {
            return (string) $messages[$error];
        }

        if ($httpStatus !== null && $httpStatus >= 500) {
            return (string) ($messages[self::I18N_KEY_UNAVAILABLE] ?? '');
        }

        return (string) ($messages[self::I18N_KEY_GENERIC] ?? '');
    }

    /**
     * Reads the creation timestamp of an order, falling back to 0 when absent.
     *
     * `WC_Order::get_date_created()` returns `WC_DateTime|null` — null appears
     * on unsaved orders. We cast null to 0 so the seed remains a deterministic
     * string instead of triggering a fatal on `null->getTimestamp()`.
     *
     * @param \WC_Order $order
     */
    private function orderTimestamp($order): int
    {
        $dateCreated = $order->get_date_created();

        return $dateCreated !== null ? $dateCreated->getTimestamp() : 0;
    }

    /**
     * Returns the subscriptions error-messages map from StoreTranslations.
     *
     * Returns an empty array if the property has not been initialized
     * (e.g. unit tests skipping the full translations bootstrap).
     */
    private function errorMessages(): array
    {
        if (!isset($this->storeTranslations->subscriptionsErrorMessages)) {
            return [];
        }

        return (array) $this->storeTranslations->subscriptionsErrorMessages;
    }
}
