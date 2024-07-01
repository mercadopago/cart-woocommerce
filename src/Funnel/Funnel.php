<?php

namespace MercadoPago\Woocommerce\Funnel;

use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Gateways;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;

class Funnel
{
    /**
     * @var Sdk
     */
    private $sdk;

    /**
     * @var Store
     */
    private $store;

    /**
     * @var Seller
     */
    private $seller;

    /**
     * @var Country
     */
    private $country;

    /**
     * @var Gateways
     */
    private $gateways;

    /**
     * @var Datadog
     */
    private $datadog;

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

    public function getInstallationId(): void
    {
        if ($this->validateStartFunnel()) {
            $this->runWithTreatment(function () {
                $createSellerFunnelBase = $this->sdk->getCreateSellerFunnelBaseInstance();
                $createSellerFunnelBase->platform_id = MP_PLATFORM_ID;
                $createSellerFunnelBase->shop_url = site_url();
                $createSellerFunnelBase->platform_version = $this->getWoocommerceVersion();
                $createSellerFunnelBase->plugin_version = MP_VERSION;
                $response = $createSellerFunnelBase->save();
                $this->store->setInstallationId($response->id);
                $this->store->setInstallationKey($response->cpp_token);
                $this->store->setExecuteActivate('no');
            });
        }
    }

    public function updateStepCredentials(): void
    {
        if ($this->isInstallationId()) {
            $this->runWithTreatment(function () {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->is_added_production_credential = !empty($this->seller->getCredentialsAccessTokenProd());
                $updateSellerFunnelBase->is_added_test_credential = !empty($this->seller->getCredentialsAccessTokenTest());
                $updateSellerFunnelBase->plugin_mode = $this->getPluginMode();
                $updateSellerFunnelBase->cust_id = $this->seller->getCustIdFromAT();
                $updateSellerFunnelBase->site_id = $this->country->countryToSiteId($this->country->getPluginDefaultCountry());
                $updateSellerFunnelBase->update();
            });
        }
    }

    /**
     * @param string $paymentMethod
     *
     * @return void
     */
    public function updateStepPaymentMethods(): void
    {
        if ($this->isInstallationId()) {
            $this->runWithTreatment(function () {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->accepted_payments = $this->gateways->getEnabledPaymentGateways();
                $updateSellerFunnelBase->update();
            });
        }
    }

    public function updateStepPluginMode(): void
    {
        if ($this->isInstallationId()) {
            $this->runWithTreatment(function () {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->plugin_mode = $this->getPluginMode();
                $updateSellerFunnelBase->update();
            });
        }
    }

    public function updateStepUninstall(): void
    {
        if ($this->isInstallationId()) {
            $this->runWithTreatment(function () {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->is_deleted = true;
                $updateSellerFunnelBase->update();
            });
        }
    }

    public function updateStepDisable(): void
    {
        if ($this->isInstallationId()) {
            $this->runWithTreatment(function () {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->is_disabled = true;
                $updateSellerFunnelBase->update();
            });
        }
    }

    public function updateStepActivate(): void
    {
        if ($this->isInstallationId()) {
            $this->runWithTreatment(function () {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->is_disabled = false;
                $updateSellerFunnelBase->update();
                $this->store->setExecuteActivate('no');
            });
        }
    }

    public function isInstallationId(): bool
    {
        return !empty($this->store->getInstallationId())
        && !empty($this->store->getInstallationKey());
    }

    private function validateStartFunnel(): bool
    {
        return empty($this->seller->getCredentialsAccessTokenProd()) &&
            !$this->isInstallationId() &&
            empty($this->gateways->getEnabledPaymentGateways());
    }

    private function getPluginMode(): string
    {
        return $this->store->isProductionMode() ? 'Prod' : 'Test';
    }

    private function getWoocommerceVersion(): string
    {
        return $GLOBALS['woocommerce']->version ? $GLOBALS['woocommerce']->version : "";
    }

    private function runWithTreatment($callback)
    {
        try {
            $callback();

            $this->sendSuccessEvent();
        } catch (\Exception $ex) {
            $this->sendErrorEvent($ex->getMessage());
        }
    }

    private function sendSuccessEvent()
    {
        $this->datadog->sendEvent('funnel', 'success');
    }

    private function sendErrorEvent(string $message)
    {
        $this->datadog->sendEvent('funnel', 'error', $message);
    }
}
