<?php

namespace MercadoPago\Woocommerce\Funnel;

use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Gateways;
use MercadoPago\Woocommerce\Helpers\Country;

class Funnel
{
    /**
     * Defines on datadog that a event was successfull
     *
     * @const
     */
    private const EVENT_TYPE_SUCCESS = 'success';

    /**
     * Defines on datadog that a event was a failure
     *
     * @const
     */
    private const EVENT_TYPE_ERROR = 'error';

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
    }

    public function getInstallationId(): void
    {
        try {
            if ($this->validateStartFunnel()) {
                $createSellerFunnelBase = $this->sdk->getCreateSellerFunnelBaseInstance();
                $createSellerFunnelBase->platform_id = MP_PLATFORM_ID;
                $createSellerFunnelBase->shop_url = site_url();
                $createSellerFunnelBase->platform_version = $this->getWoocommerceVersion();
                $createSellerFunnelBase->plugin_version = MP_VERSION;
                $response = $createSellerFunnelBase->save();
                $this->store->setInstallationId($response->id);
                $this->store->setInstallationKey($response->cpp_token);
                $this->store->setExecuteActivate('no');

                $this->sendDatadogEvent(self::EVENT_TYPE_SUCCESS);
            }
        } catch (\Exception $e) {
            $this->sendDatadogEvent(self::EVENT_TYPE_ERROR, $e->getMessage());
        }
    }

    public function updateStepCredentials(): void
    {
        try {
            if ($this->isInstallationId()) {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->is_added_production_credential = !empty($this->seller->getCredentialsAccessTokenProd());
                $updateSellerFunnelBase->is_added_test_credential = !empty($this->seller->getCredentialsAccessTokenTest());
                $updateSellerFunnelBase->plugin_mode = $this->getPluginMode();
                $updateSellerFunnelBase->cust_id = $this->seller->getCustIdFromAT();
                $updateSellerFunnelBase->site_id = $this->country->countryToSiteId($this->country->getPluginDefaultCountry());
                $updateSellerFunnelBase->update();

                $this->sendDatadogEvent(self::EVENT_TYPE_SUCCESS);
            }
        } catch (\Exception $e) {
            $this->sendDatadogEvent(self::EVENT_TYPE_ERROR, $e->getMessage());
        }
    }

    /**
     * @param string $paymentMethod
     *
     * @return void
     */
    public function updateStepPaymentMethods(): void
    {
        try {
            if ($this->isInstallationId()) {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->accepted_payments = $this->gateways->getEnabledPaymentGateways();
                $updateSellerFunnelBase->update();

                $this->sendDatadogEvent(self::EVENT_TYPE_SUCCESS);
            }
        } catch (\Exception $e) {
            $this->sendDatadogEvent(self::EVENT_TYPE_ERROR, $e->getMessage());
        }
    }

    public function updateStepPluginMode(): void
    {
        try {
            if ($this->isInstallationId()) {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->plugin_mode = $this->getPluginMode();
                $updateSellerFunnelBase->update();

                $this->sendDatadogEvent(self::EVENT_TYPE_SUCCESS);
            }
        } catch (\Exception $e) {
            $this->sendDatadogEvent(self::EVENT_TYPE_ERROR, $e->getMessage());
        }
    }

    public function updateStepUninstall(): void
    {
        try {
            if ($this->isInstallationId()) {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->is_deleted = true;
                $updateSellerFunnelBase->update();

                $this->sendDatadogEvent(self::EVENT_TYPE_SUCCESS);
            }
        } catch (\Exception $e) {
            $this->sendDatadogEvent(self::EVENT_TYPE_ERROR, $e->getMessage());
        }
    }

    public function updateStepDisable(): void
    {
        try {
            if ($this->isInstallationId()) {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->is_disabled = true;
                $updateSellerFunnelBase->update();

                $this->sendDatadogEvent(self::EVENT_TYPE_SUCCESS);
            }
        } catch (\Exception $e) {
            $this->sendDatadogEvent(self::EVENT_TYPE_ERROR, $e->getMessage());
        }
    }

    public function updateStepActivate(): void
    {
        try {
            if ($this->isInstallationId()) {
                $updateSellerFunnelBase = $this->sdk->getUpdateSellerFunnelBaseInstance();
                $updateSellerFunnelBase->id = $this->store->getInstallationId();
                $updateSellerFunnelBase->cpp_token = $this->store->getInstallationKey();
                $updateSellerFunnelBase->is_disabled = false;
                $updateSellerFunnelBase->update();
                $this->store->setExecuteActivate('no');

                $this->sendDatadogEvent(self::EVENT_TYPE_SUCCESS);
            }
        } catch (\Exception $e) {
            $this->sendDatadogEvent(self::EVENT_TYPE_ERROR, $e->getMessage());
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

    private function sendDatadogEvent(string $event_type, string $message = null): void
    {
        try {
            $datadogEvent = $this->sdk->getDatadogEventInstance();

            if (!\is_null($message)) {
                $datadogEvent->message = $message;
            }

            $datadogEvent->value = $event_type;
            $datadogEvent->plugin_version = MP_VERSION;
            $datadogEvent->platform->name = MP_PLATFORM_NAME;
            $datadogEvent->platform->version = $this->getWoocommerceVersion();
            $datadogEvent->platform->url = site_url();

            $datadogEvent->register(array("team" => "smb", "event_type" => "funnel"));
        } catch (\Exception $e) {
            return;
        }
    }
}
