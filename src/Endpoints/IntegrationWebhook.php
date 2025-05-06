<?php

namespace MercadoPago\Woocommerce\Endpoints;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class IntegrationWebhook
{
    private Seller $sellerConfig;
    private Store $storeConfig;
    private Requester $requester;
    private Endpoints $endpoints;
    private Logs $logs;
    private Plugin $pluginHook;

    public const WEBHOOK_ENDPOINT = 'WC_WooMercadoPago_Integration_Webhook';

    public function __construct(
        Seller $sellerConfig,
        Store $storeConfig,
        Requester $requester,
        Endpoints $endpoints,
        Logs $logs,
        Plugin $pluginHook
    ) {
        $this->sellerConfig = $sellerConfig;
        $this->storeConfig = $storeConfig;
        $this->requester = $requester;
        $this->endpoints = $endpoints;
        $this->logs = $logs;
        $this->pluginHook = $pluginHook;

        $this->registerIntegrationWebhookEndpoint();
    }

    private function registerIntegrationWebhookEndpoint(): void
    {
        $this->endpoints->registerApiEndpoint(self::WEBHOOK_ENDPOINT, [$this, 'webhookHandler']);
    }

    public function webhookHandler(): bool
    {
        if (!isset($_GET['integration_id'])) {
            $this->logs->file->error("Missing integration_id in Integration Webhook request", __CLASS__);
            $this->sendUserTo(admin_url('admin.php?page=mercadopago-settings&onboarding_error=true'));
            return false;
        }

        $integration_id = Form::sanitizedGetData('integration_id');
        $code_verifier = $this->storeConfig->getCodeVerifier();

        $this->logs->file->info("Received webhook with integration_id: {$integration_id}", __CLASS__);

        try {
            $deviceFingerPrint = $this->sellerConfig->getDeviceFingerprint();
            $url = "/ppcore/prod/configurations-api/onboarding/v1/integration/{$integration_id}?code_verifier={$code_verifier}";
            $response = $this->requester->get($url, [
                'X-Device-Fingerprint' => $deviceFingerPrint
            ]);

            $data = $response->getData();

            if ($response->getStatus() === 200) {
                $urlRedirectToSuccessPage = $this->getRedirectUrlBasedOnCredentialsState();
                $this->logs->file->info("API request successful for integration_id: {$integration_id}, got credentials.", __CLASS__);

                [$productionCredentials, $testCredentials, $validation] = $this->validateCredentialsReponse($response->getData());

                $this->setStoreAndSellerInfo($productionCredentials['access_token'], $validation);
                $this->setCredentialsOnOptions($productionCredentials, $testCredentials);
                $this->pluginHook->executeUpdateCredentialAction();

                $this->logs->file->info("Stored validated credentials successfully for integration_id: {$integration_id}", __CLASS__);
                $this->sendUserTo(admin_url($urlRedirectToSuccessPage));
                return true;
            } else {
                $originalMessage = $data['original_message'];
                $this->logs->file->error("API request failed for integration_id: {$integration_id}. Device: {$deviceFingerPrint} Status code: " . $response->getStatus() . " Original Message: " . $originalMessage, __CLASS__);
                $this->sendUserTo(admin_url('admin.php?page=mercadopago-settings&onboarding_error=true'));
                return false;
            }
        } catch (\Exception $e) {
            $this->logs->file->error("Error in credentials update workflow: " . $e->getMessage(), __CLASS__);
            $this->sendUserTo(admin_url('admin.php?page=mercadopago-settings&onboarding_error=true'));
            return false;
        }
    }

    private function validateCredentialsReponse($data): array
    {
        if (
            !isset($data['credentials']) ||
            !isset($data['credentials']['production']) ||
            !isset($data['credentials']['test'])
        ) {
            throw new \Exception("Missing credentials in API response");
        }

        $productionCredentials = $data['credentials']['production'];
        if (
            !isset($productionCredentials['access_token']) ||
            !isset($productionCredentials['public_key'])
        ) {
            throw new \Exception("Missing production credentials in API response");
        }
        $testCredentials = $data['credentials']['test'];
        if (
            !isset($testCredentials['access_token']) ||
            !isset($testCredentials['public_key'])
        ) {
            throw new \Exception("Missing test credentials in API response");
        }

        $testValidation = $this->validateCredentialsInWrapper($testCredentials);
        if (
            $testValidation['homologated'] !== true ||
            !isset($testValidation['client_id']) ||
            $testValidation['is_test'] !== true
        ) {
            throw new \Exception("Test credentials are not homologated");
        }
        $prodValidation = $this->validateCredentialsInWrapper($productionCredentials);
        if (
            $prodValidation['homologated'] !== true ||
            !isset($prodValidation['client_id'])
        ) {
            throw new \Exception("Production credentials are not homologated");
        }

        return [$productionCredentials, $testCredentials, $prodValidation];
    }

    private function validateCredentialsInWrapper($credentials): array
    {
        $response = $this->sellerConfig->validateCredentials($credentials['access_token'], $credentials['public_key']);
        if ($response['status'] !== 200 || count($response['data']) == 0) {
            throw new \Exception("Unable to make request to validate credentials");
        }
        return $response['data'];
    }

    private function setCredentialsOnOptions($productionCredentials, $testCredentials): void
    {
        $this->sellerConfig->setCredentialsAccessTokenProd($productionCredentials['access_token']);
        $this->sellerConfig->setCredentialsAccessTokenTest($testCredentials['access_token']);
        $this->sellerConfig->setCredentialsPublicKeyProd($productionCredentials['public_key']);
        $this->sellerConfig->setCredentialsPublicKeyTest($testCredentials['public_key']);
    }

    private function setStoreAndSellerInfo($accessToken, $validation)
    {
        $sellerInfo = $this->sellerConfig->getSellerInfo($accessToken);
        if ($sellerInfo['status'] === 200) {
            $siteId = $sellerInfo['data']['site_id'];
            $this->storeConfig->setCheckoutCountry($siteId);
            $this->sellerConfig->setSiteId($siteId);
            $this->sellerConfig->setTestUser(in_array('test_user', $sellerInfo['data']['tags'], true));
        }
        $this->sellerConfig->setHomologValidate($validation['homologated']);
        $this->sellerConfig->setClientId($validation['client_id']);
    }

    protected function sendUserTo($url): void
    {
        header('Location: ' . $url);
    }

    public function getRedirectUrlBasedOnCredentialsState(): string
    {
        $publicKeyProd   = $this->sellerConfig->getCredentialsPublicKeyProd();
        $isExpiredPublicKey = false;

        if (!empty($publicKeyProd)) {
            $isExpiredPublicKey = $this->sellerConfig->isExpiredPublicKey($publicKeyProd);
        }

        $urlAdminMp = 'admin.php?page=mercadopago-settings';
        $urlLinkUpdated = 'admin.php?page=mercadopago-settings&link_updated=true';

        return $isExpiredPublicKey ? $urlLinkUpdated : $urlAdminMp;
    }
}
