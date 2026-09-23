<?php

namespace MercadoPago\Woocommerce\Funnel;

use Exception;
use MercadoPago\PP\Sdk\Common\Constants;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Gateways;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;

class Funnel
{
    // Generic message reported for the seller-contact step, instead of the real
    // exception, since the SDK exception can echo the request body (which carries
    // the seller's email) verbatim — see runWithTreatment().
    private const SELLER_CONTACT_ERROR_MESSAGE = 'Error updating seller contact information';

    private Sdk $sdk;

    private Store $store;

    private Seller $seller;

    private Country $country;

    private Gateways $gateways;

    private Datadog $datadog;

    /**
     * Funnel constructor
     *
     * @param Store $store
     * @param Seller $seller
     * @param Country $country
     * @param Gateways $gateways
     */
    public function __construct(Store $store, Seller $seller, Country $country, Gateways $gateways)
    {
        $this->sdk      = new Sdk();
        $this->store    = $store;
        $this->seller   = $seller;
        $this->country  = $country;
        $this->gateways = $gateways;
        $this->datadog  = Datadog::getInstance();
    }

    /**
     * Create seller funnel
     */
    public function create(?\Closure $after = null): void
    {
        if (!$this->canCreate()) {
            return;
        }

        $this->runWithTreatment(function () use ($after) {
            $createSellerFunnelBase = $this->sdk->getCreateSellerFunnelBaseInstance();
            $createSellerFunnelBase->platform_id = MP_PLATFORM_ID;
            $createSellerFunnelBase->shop_url = site_url();
            $createSellerFunnelBase->platform_version = $this->getWoocommerceVersion();
            $createSellerFunnelBase->plugin_version = MP_VERSION;
            $createSellerFunnelBase->site_id = $this->resolveSiteId();
            $response = $createSellerFunnelBase->save();
            $this->store->setInstallationId($response->id);
            $this->store->setInstallationKey($response->cpp_token);

            if (isset($after)) {
                $after();
            }
        });
    }

    public function created(): bool
    {
        return !empty($this->store->getInstallationId())
            && !empty($this->store->getInstallationKey());
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
        ], $after, self::SELLER_CONTACT_ERROR_MESSAGE);
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
        $this->update(['is_disabled' => false], $after);
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
    private function update(array $attrs, ?\Closure $after = null, ?string $sanitizedError = null): void
    {
        if (!$this->created()) {
            return;
        }

        $attrs = array_merge($attrs, [
            'id' => $this->store->getInstallationId(),
            'cpp_token' => $this->store->getInstallationKey(),
        ]);

        $this->runWithTreatment(function () use ($attrs, $after) {
            $updateSellerFunnelBase = $this->getUpdateSellerFunnelBaseInstance();

            foreach ($attrs as $attr => $value) {
                $updateSellerFunnelBase->$attr = $value;
            }

            $updateSellerFunnelBase->update();

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

    private function canCreate(): bool
    {
        return !$this->created()
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

    private function getUpdateSellerFunnelBaseInstance(): UpdateSellerFunnelBase
    {
        return $this->sdk->getEntityInstance(UpdateSellerFunnelBase::class, Constants::BASEURL_MP);
    }

    /**
     * @param \Closure $callback
     * @param string $sanitizedError When given, reported in place of the SDK
     *                               exception, which can echo the request body
     *                               verbatim (e.g. on a JSON encode failure) and
     *                               leak PII into the plugin log and Datadog
     */
    private function runWithTreatment(\Closure $callback, ?string $sanitizedError = null): void
    {
        try {
            $callback();

            $this->sendSuccessEvent();
        } catch (Exception $ex) {
            $logDetail = $sanitizedError ?? (string) $ex;
            $eventMessage = $sanitizedError ?? $ex->getMessage();

            $GLOBALS['mercadopago']->logs->file->error(sprintf("Error on %s\n%s", __METHOD__, $logDetail), __CLASS__);
            $this->sendErrorEvent($eventMessage);
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
