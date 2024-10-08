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

    public function updateStepCredentials(?\Closure $after = null): void
    {
        $this->update([
            'is_added_production_credential' => !empty($this->seller->getCredentialsAccessTokenProd()),
            'is_added_test_credential'       => !empty($this->seller->getCredentialsAccessTokenTest()),
            'plugin_mode'                    => $this->getPluginMode(),
            'cust_id'                        => $this->seller->getCustIdFromAT(),
            'site_id'                        => $this->country->countryToSiteId($this->country->getPluginDefaultCountry()),
        ], $after);
    }

    /**
     * @return void
     */
    public function updateStepPaymentMethods(?\Closure $after = null): void
    {
        $this->update(['accepted_payments' => $this->gateways->getEnabledPaymentGateways()], $after);
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
     */
    private function update(array $attrs, ?\Closure $after = null): void
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
        });
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

    private function runWithTreatment(\Closure $callback): void
    {
        try {
            $callback();

            $this->sendSuccessEvent();
        } catch (Exception $ex) {
            $GLOBALS['mercadopago']->logs->file->error(sprintf("Error on %s\n%s", __METHOD__, $ex), __CLASS__);
            $this->sendErrorEvent($ex->getMessage());
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
