<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Composition;

use MercadoPago\Woocommerce\Api\Core\CoreApiClient;
use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\WordPressHttpClient;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use Closure;
use wpdb;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\DenyAllSmartPixNotificationAuthenticator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\RealSmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationHandler;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationIdentifiers;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixEffectOwnership;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationBody;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\SmartPixNotificationHttpClient;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationLinkage;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixNotificationReceiver;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixReconciliation;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Composes the Smart Pix Core P&P notification handler.
 *
 * Every collaborator uses the notification source: the real Orders client and query read
 * the Order fresh with the store's current-mode credentials, and the applicator reserves
 * effects in the options-backed ownership store. The plugin composition shares the native
 * effects scope with the merchant save guard, so an applied cancellation is not dispatched back.
 * The plugin composition sends telemetry to Core Monitor; create() without telemetry emits nothing.
 * All components share one telemetry instance, which never changes the response or the order.
 * An applied terminal state is also counted as first observed by the notification, unless a
 * poll or page render already counted it.
 * A linked notification answered with 500 also schedules the WP-Cron reconciliation, which
 * reads and applies through the same query and guarded applicator.
 * Only notification handlers call this; the authenticator is the deny-all default because
 * the raw-body entry is not used by the Core flow.
 */
final class SmartPixCoreNotificationHandlerFactory
{
    /** Builds from the plugin container; call only after the plugin has loaded. */
    public static function fromPlugin(): SmartPixCoreNotificationHandler
    {
        global $mercadopago, $wpdb;

        $metricContext = new SmartPixMetricContext();
        return self::create(
            new WordPressHttpClient(Requester::BASEURL_MP),
            $mercadopago->sellerConfig,
            $mercadopago->storeConfig,
            $mercadopago->orderMetadata,
            $wpdb,
            SmartPixTelemetry::fromPlugin($mercadopago, $metricContext),
            $mercadopago->smartPixNativeEffects,
            [$mercadopago->orderStatus, 'addSmartPixStatusNotes']
        );
    }

    public static function create(
        HttpClientInterface $httpClient,
        Seller $seller,
        Store $store,
        OrderMetadata $metadata,
        wpdb $database,
        ?SmartPixTelemetry $telemetry = null,
        ?SmartPixNativeEffects $nativeEffects = null,
        ?callable $recordNotes = null,
        ?Closure $terminalObserved = null
    ): SmartPixCoreNotificationHandler {
        $telemetry = $telemetry ?? new SmartPixTelemetry();
        $source = SmartPixNotificationReceiver::SOURCE;
        $orderClient = new RealSmartPixOrderClient($httpClient, $seller, $store, $telemetry, $source);
        $applicator = self::applicator($seller, $metadata, $database, $telemetry, $nativeEffects, $recordNotes);
        $reconciliation = self::createReconciliation(
            $httpClient,
            $seller,
            $store,
            $metadata,
            $database,
            $telemetry,
            $nativeEffects,
            $recordNotes
        );

        $receiver = new SmartPixNotificationReceiver(
            new SmartPixNotificationBody(),
            new DenyAllSmartPixNotificationAuthenticator(),
            new SmartPixCoreNotificationIdentifiers(),
            new SmartPixNotificationLinkage(
                $metadata,
                $seller,
                new SmartPixStatusLock($database, SmartPixNotificationLinkage::REVIEW_LOCK_NAME_PREFIX)
            ),
            new SmartPixOrderQuery($orderClient, $metadata, $seller, $telemetry, $source),
            $applicator,
            $telemetry,
            $terminalObserved,
            static function (int $orderId) use ($reconciliation): void {
                $reconciliation->schedule($orderId);
            }
        );

        return new SmartPixCoreNotificationHandler(
            new NotificationsClient(new CoreApiClient(new SmartPixNotificationHttpClient($httpClient, $telemetry))),
            $receiver
        );
    }

    /** WP-Cron reconciliation with the same Orders read and guarded applicator; call after plugin load. */
    public static function reconciliationFromPlugin(): SmartPixReconciliation
    {
        global $mercadopago, $wpdb;

        $metricContext = new SmartPixMetricContext();
        return self::createReconciliation(
            new WordPressHttpClient(Requester::BASEURL_MP),
            $mercadopago->sellerConfig,
            $mercadopago->storeConfig,
            $mercadopago->orderMetadata,
            $wpdb,
            SmartPixTelemetry::fromPlugin($mercadopago, $metricContext),
            $mercadopago->smartPixNativeEffects,
            [$mercadopago->orderStatus, 'addSmartPixStatusNotes']
        );
    }

    public static function createReconciliation(
        HttpClientInterface $httpClient,
        Seller $seller,
        Store $store,
        OrderMetadata $metadata,
        wpdb $database,
        ?SmartPixTelemetry $telemetry = null,
        ?SmartPixNativeEffects $nativeEffects = null,
        ?callable $recordNotes = null
    ): SmartPixReconciliation {
        $telemetry = $telemetry ?? new SmartPixTelemetry();
        $orderClient = new RealSmartPixOrderClient($httpClient, $seller, $store, $telemetry);

        return new SmartPixReconciliation(
            new SmartPixOrderQuery($orderClient, $metadata, $seller, $telemetry),
            self::applicator($seller, $metadata, $database, $telemetry, $nativeEffects, $recordNotes),
            $metadata,
            $telemetry
        );
    }

    private static function applicator(
        Seller $seller,
        OrderMetadata $metadata,
        wpdb $database,
        SmartPixTelemetry $telemetry,
        ?SmartPixNativeEffects $nativeEffects,
        ?callable $recordNotes
    ): SmartPixStateApplicator {
        return new SmartPixStateApplicator(
            $metadata,
            new SmartPixStateReducer(),
            $seller,
            new SmartPixEffectOwnership($database),
            $telemetry,
            $nativeEffects,
            $recordNotes
        );
    }
}
