<?php

namespace MercadoPago\Woocommerce\Funnel;

use MercadoPago\Woocommerce\Api\Core\FunnelClient;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Gateways;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;

class Funnel
{
    // Generic message reported for the seller-contact step, instead of the real
    // exception, since an upstream error can echo the request body (which carries
    // the seller's email) verbatim — see runWithTreatment().
    private const SELLER_CONTACT_ERROR_MESSAGE = 'Error updating seller contact information';

    private const CREATION_ERROR_MESSAGE = 'Error creating onboarding installation';

    private FunnelClient $client;

    private Store $store;

    private Seller $seller;

    private Country $country;

    private Gateways $gateways;

    private Datadog $datadog;

    private CreationLock $creationLock;

    /**
     * Funnel constructor
     *
     * @param FunnelClient $client
     * @param Store $store
     * @param Seller $seller
     * @param Country $country
     * @param Gateways $gateways
     */
    public function __construct(
        FunnelClient $client,
        Store $store,
        Seller $seller,
        Country $country,
        Gateways $gateways,
        ?CreationLock $creationLock = null
    ) {
        $this->client   = $client;
        $this->store    = $store;
        $this->seller   = $seller;
        $this->country  = $country;
        $this->gateways = $gateways;
        $this->datadog  = Datadog::getInstance();
        $this->creationLock = $creationLock ?? new CreationLock();
    }

    /**
     * Create seller funnel
     */
    public function create(?\Closure $after = null): void
    {
        $this->runWithTreatment(function () use ($after) {
            $step = 'eligibility';
            try {
                return $this->createWithLease($after, $step);
            } catch (\Throwable $error) {
                // Upstream and SQL messages can carry the CPP token: report only the failed step,
                // without chaining the original exception. "persist" or later means Core accepted it.
                throw new \RuntimeException(sprintf('%s (step: %s)', self::CREATION_ERROR_MESSAGE, $step));
            }
        });
    }

    /**
     * @param string $step Updated with the step in progress, for the sanitized failure report
     *
     * @return bool|null false when another request owns or already finished the creation
     */
    private function createWithLease(?\Closure $after, string &$step): ?bool
    {
        if (!$this->canCreate()) {
            return false;
        }
        $step = 'lease';
        $owner = $this->creationLock->acquire();
        if ($owner === null) {
            return false;
        }
        try {
            $step = 'eligibility';
            if (!$this->canCreate()) {
                return false;
            }
            $step = 'lease';
            if (!$this->creationLock->owns($owner)) {
                throw new \RuntimeException('Onboarding creation lease is no longer owned.');
            }
            $step = 'pending';
            // Recorded before the POST: once Core accepts it, a failure before the callback
            // must leave the first seller contact owed to the recovery path.
            $this->store->setSellerContactPending(true);
            if (!$this->store->getSellerContactPending()) {
                throw new \RuntimeException('Unable to record the pending seller contact.');
            }
            $step = 'core';
            $registration = $this->client->create([
                'platform_id' => MP_PLATFORM_ID,
                'shop_url' => site_url(),
                'platform_version' => $this->getWoocommerceVersion(),
                'plugin_version' => MP_VERSION,
                'site_id' => $this->resolveSiteId(),
            ]);
            $step = 'persist';
            $this->store->setInstallationCredentials($registration, $owner);

            $step = 'callback';
            if (isset($after)) {
                $after();
            }

            return null;
        } finally {
            $failedStep = $step;
            $step = 'release';
            $this->creationLock->release($owner);
            $step = $failedStep;
        }
    }

    public function created(): bool
    {
        try {
            $identity = $this->store->getInstallationCredentials();
            return $identity['id'] !== '' && $identity['key'] !== '';
        } catch (\Throwable $error) {
            // create()/update() report the failure inside the best-effort boundary.
            return false;
        }
    }

    /**
     * Send the store's contact email and country so Product can identify sellers
     * who installed the plugin but never added credentials — this step is chained
     * from the $after callback of create(), not from updateStepCredentials(), since
     * that step never runs for that audience.
     */
    public function updateStepSellerContact(?\Closure $after = null): void
    {
        $this->update([
            'email'   => $this->getStoreEmail(),
            'country' => Country::getWoocommerceDefaultCountry(),
        ], function () use ($after) {
            $this->store->setSellerContactPending(false);
            if (isset($after)) {
                $after();
            }
        }, self::SELLER_CONTACT_ERROR_MESSAGE);
    }

    /**
     * True when this store's registration was created but its first seller contact was never
     * confirmed. Normal reactivations of older or completed installations return false.
     */
    public function sellerContactPending(): bool
    {
        return $this->store->getSellerContactPending();
    }

    public function updateStepCredentials(?\Closure $after = null): void
    {
        $this->update([
            'is_added_production_credential' => !empty($this->seller->getCredentialsAccessTokenProd()),
            'is_added_test_credential'       => !empty($this->seller->getCredentialsAccessTokenTest()),
            'plugin_mode'                    => $this->getPluginMode(),
            'cust_id'                        => $this->seller->getCustIdFromAT(),
            'site_id'                        => $this->resolveSiteId(),
        ], $after);
    }

    /**
     * @return void
     */
    public function updateStepPaymentMethods(?bool $isSubscriptionEnabled = null, ?\Closure $after = null): void
    {
        $attrs = ['accepted_payments' => $this->gateways->getEnabledPaymentGateways()];

        if ($isSubscriptionEnabled !== null) {
            $attrs['is_subscription_enabled'] = $isSubscriptionEnabled;
        }

        $this->update($attrs, $after);
    }

    public function updateStepPluginMode(?\Closure $after = null): void
    {
        $this->update(['plugin_mode' => $this->getPluginMode()], $after);
    }

    public function updateStepUninstall(?\Closure $after = null): void
    {
        $this->update(['is_deleted' => true], $after);
    }

    public function updateStepDisable(?\Closure $after = null): void
    {
        $this->update(['is_disabled' => true], $after);
    }

    public function updateStepActivate(?\Closure $after = null): void
    {
        // Repair interrupted compatibility mirrors before completing reactivation.
        $this->update(['is_disabled' => false], $after, 'Error activating onboarding installation', true);
    }

    public function updateStepPluginVersion(?\Closure $after = null): void
    {
        $this->update(['plugin_version' => MP_VERSION], $after);
    }

    /**
     * Update seller funnel using the given attributes
     *
     * @param array $attrs Funnel attribute values map
     * @param \Closure $after Function to run after funnel updated, inside treatment
     * @param string $sanitizedError Message to report instead of the exception —
     *                               required for steps that carry PII (see
     *                               runWithTreatment())
     */
    private function update(
        array $attrs,
        ?\Closure $after = null,
        ?string $sanitizedError = null,
        bool $synchronizeLegacy = false
    ): void {
        $this->runWithTreatment(function () use ($attrs, $after, $synchronizeLegacy) {
            $identity = $this->store->getInstallationCredentials();
            if ($identity['id'] === '' || $identity['key'] === '') {
                return false;
            }
            if ($synchronizeLegacy) {
                $this->store->synchronizeInstallationCredentials($identity);
            }
            $attrs['id'] = $identity['id'];
            $this->client->update($attrs, $identity['key']);

            if (isset($after)) {
                $after();
            }
        }, $sanitizedError);
    }

    /**
     * Read the store's contact email for the onboarding funnel.
     *
     * admin_email is the one address WordPress guarantees to exist. The installer's
     * own address would fit the goal better, but it is unreachable from here — see
     * traps.md. Kept isolated so swapping the source is a one-line change.
     */
    private function getStoreEmail(): string
    {
        return get_option('admin_email', '');
    }

    /**
     * Allowlisted conversion — never the raw country code (CWE-99). A country outside the
     * seven site ids resolves to '', normalized to null so the funnel column keeps one
     * representation of "unknown". Shared by both steps that send it — see traps.md.
     */
    private function resolveSiteId(): ?string
    {
        $persistedSiteId = $this->seller->getSiteId();

        // getPluginDefaultCountry() trusts the persisted site id without validating it, and
        // siteIdToCountry() answers AR for anything it does not know — so an unsupported stored
        // value would travel as MLA. Guarded here only: that helper also decides which payment
        // methods a store is offered, which this step has no business changing. See traps.md.
        $country = ($persistedSiteId === '' || Country::isValidSiteId($persistedSiteId))
            ? $this->country->getPluginDefaultCountry()
            : Country::getWoocommerceDefaultCountry();

        $siteId = $this->country::countryToSiteId($country);

        return $siteId !== '' ? $siteId : null;
    }

    /** @phpstan-impure Reads shared state that another PHP worker can change. */
    private function canCreate(): bool
    {
        $identity = $this->store->getInstallationCredentials();

        return ($identity['id'] === '' || $identity['key'] === '')
            && empty($this->seller->getCredentialsAccessTokenProd())
            && empty($this->gateways->getEnabledPaymentGateways());
    }

    private function getPluginMode(): string
    {
        return $this->store->isProductionMode() ? 'Prod' : 'Test';
    }

    private function getWoocommerceVersion(): string
    {
        return $GLOBALS['woocommerce']->version ?? "";
    }

    /**
     * @param \Closure $callback
     * @param string $sanitizedError When given, reported in place of the upstream
     *                               exception, which can echo the request body
     *                               verbatim (e.g. on a JSON encode failure) and
     *                               leak PII into the plugin log and Datadog
     */
    private function runWithTreatment(\Closure $callback, ?string $sanitizedError = null): void
    {
        try {
            if ($callback() !== false) {
                $this->sendSuccessEvent();
            }
        } catch (\Throwable $ex) {
            $logDetail = $sanitizedError ?? (string) $ex;
            $eventMessage = $sanitizedError ?? $ex->getMessage();

            try {
                $GLOBALS['mercadopago']->logs->file->error(sprintf("Error on %s\n%s", __METHOD__, $logDetail), __CLASS__);
            } catch (\Throwable $loggingError) {
                // Diagnostics must not turn a funnel failure into a failed page load.
            }
            try {
                $this->sendErrorEvent($eventMessage);
            } catch (\Throwable $metricsError) {
                // The installation remains usable if telemetry is unavailable.
            }
        }
    }

    private function sendSuccessEvent(): void
    {
        $this->datadog->sendEvent('funnel', 'success');
    }

    private function sendErrorEvent(string $message): void
    {
        $this->datadog->sendEvent('funnel', 'error', $message);
    }
}
