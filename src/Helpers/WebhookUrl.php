<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookUrl
{
    /**
     * Builds the webhook notification URL including the `source_news` parameter
     * required by NotificationFactory to route the callback to the correct handler.
     * Shared by the standard checkout (CIT) and the subscription renewal (MIT).
     * Returns '' when no valid public URL can be built (e.g. localhost).
     *
     * @param callable $apiRequestUrlResolver Lazily returns WC api_request_url(gatewayClass);
     *                                        only invoked in the fallback branch, so the
     *                                        custom-domain branch works even when WC() is null.
     */
    public static function build(
        string $customDomain,
        string $customDomainOptions,
        callable $apiRequestUrlResolver,
        string $siteUrl,
        string $gatewayClass
    ): string {
        if (
            !empty($customDomain)
            && !Strings::contains($customDomain, 'localhost')
            && Url::isValid($customDomain)
        ) {
            return $customDomainOptions === 'yes'
                ? $customDomain . '?wc-api=' . $gatewayClass . '&source_news=' . NotificationType::getNotificationType($gatewayClass)
                : $customDomain;
        }

        if (empty($customDomain) && !Strings::contains($siteUrl, 'localhost')) {
            $apiRequestUrl = (string) $apiRequestUrlResolver();
            // Without a base URL (e.g. WC() unavailable in WP-Cron) we cannot build a
            // routable URL — return empty so the caller can log/skip instead of sending a broken one.
            if ($apiRequestUrl === '') {
                return '';
            }
            $join = preg_match('#/wc-api/#', $apiRequestUrl) ? '?' : '&';
            return $apiRequestUrl . $join . 'source_news=' . NotificationType::getNotificationType($gatewayClass);
        }

        return '';
    }
}
