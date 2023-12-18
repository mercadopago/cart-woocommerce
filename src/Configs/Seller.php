<?php

namespace MercadoPago\Woocommerce\Configs;

use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class Seller
{
    /**
     * @const
     */
    private const SITE_ID = '_site_id_v1';

    /**
     * @const
     */
    private const CLIENT_ID = '_mp_client_id';

    /**
     * @const
     */
    private const COLLECTOR_ID = '_collector_id_v1';

    /**
     * @const
     */
    private const CREDENTIALS_PUBLIC_KEY_PROD = '_mp_public_key_prod';

    /**
     * @const
     */
    private const CREDENTIALS_PUBLIC_KEY_TEST = '_mp_public_key_test';

    /**
     * @const
     */
    private const CREDENTIALS_ACCESS_TOKEN_PROD = '_mp_access_token_prod';

    /**
     * @const
     */
    private const CREDENTIALS_ACCESS_TOKEN_TEST = '_mp_access_token_test';

    /**
     * @const
     */
    private const HOMOLOG_VALIDATE = 'homolog_validate';

    /**
     * @const
     */
    private const CHECKOUT_BASIC_PAYMENT_METHODS = '_checkout_payments_methods';

    /**
     * @const
     */
    private const CHECKOUT_TICKET_PAYMENT_METHODS = '_all_payment_methods_ticket';

    /**
     * @const
     */
    private const SITE_ID_PAYMENT_METHODS = '_site_id_payment_methods';

    /**
     * @const
     */
    private const CHECKOUT_PAYMENT_METHOD_PIX = '_mp_payment_methods_pix';

    /**
     * @const
     */
    private const ALL_PAYMENT_METHODS = '_all_payment_methods_v0';

    /**
     * @const
     */
    private const TEST_USER = '_test_user_v1';

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var Requester
     */
    private $requester;

    /**
     * @var Store
     */
    private $store;

    /**
     * @var Logs
     */
    private $logs;

    /**
     * Credentials constructor
     *
     * @param Cache $cache
     * @param Options $options
     * @param Requester $requester
     * @param Store $store
     * @param Logs $logs
     */
    public function __construct(Cache $cache, Options $options, Requester $requester, Store $store, Logs $logs)
    {
        $this->cache     = $cache;
        $this->options   = $options;
        $this->requester = $requester;
        $this->store     = $store;
        $this->logs      = $logs;
    }

    /**
     * @return string
     */
    public function getSiteId(): string
    {
        return strtoupper($this->options->get(self::SITE_ID, ''));
    }

    /**
     * @param string $siteId
     */
    public function setSiteId(string $siteId): void
    {
        $this->options->set(self::SITE_ID, $siteId);
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->options->get(self::CLIENT_ID, '');
    }

    /**
     * @param string $clientId
     */
    public function setClientId(string $clientId): void
    {
        $this->options->set(self::CLIENT_ID, $clientId);
    }

    /**
     * @return string
     */
    public function getCollectorId(): string
    {
        return $this->options->get(self::COLLECTOR_ID, '');
    }

    /**
     * @param string $collectorId
     */
    public function setCollectorId(string $collectorId): void
    {
        $this->options->set(self::COLLECTOR_ID, $collectorId);
    }

    /**
     * @return string
     */
    public function getCredentialsPublicKeyProd(): string
    {
        return $this->options->get(self::CREDENTIALS_PUBLIC_KEY_PROD, '');
    }

    /**
     * @param string $credentialsPublicKeyProd
     */
    public function setCredentialsPublicKeyProd(string $credentialsPublicKeyProd): void
    {
        $this->options->set(self::CREDENTIALS_PUBLIC_KEY_PROD, $credentialsPublicKeyProd);
    }

    /**
     * @return string
     */
    public function getCredentialsPublicKeyTest(): string
    {
        return $this->options->get(self::CREDENTIALS_PUBLIC_KEY_TEST, '');
    }

    /**
     * @param string $credentialsPublicKeyTest
     */
    public function setCredentialsPublicKeyTest(string $credentialsPublicKeyTest): void
    {
        $this->options->set(self::CREDENTIALS_PUBLIC_KEY_TEST, $credentialsPublicKeyTest);
    }

    /**
     * @return string
     */
    public function getCredentialsAccessTokenProd(): string
    {
        return $this->options->get(self::CREDENTIALS_ACCESS_TOKEN_PROD, '');
    }

    /**
     * @param string $credentialsAccessTokenProd
     */
    public function setCredentialsAccessTokenProd(string $credentialsAccessTokenProd): void
    {
        $this->options->set(self::CREDENTIALS_ACCESS_TOKEN_PROD, $credentialsAccessTokenProd);
    }

    /**
     * @return string
     */
    public function getCredentialsAccessTokenTest(): string
    {
        return $this->options->get(self::CREDENTIALS_ACCESS_TOKEN_TEST, '');
    }

    /**
     * @param string $credentialsAccessTokenTest
     */
    public function setCredentialsAccessTokenTest(string $credentialsAccessTokenTest): void
    {
        $this->options->set(self::CREDENTIALS_ACCESS_TOKEN_TEST, $credentialsAccessTokenTest);
    }

    /**
     * @return bool
     */
    public function getHomologValidate(): bool
    {
        return $this->options->get(self::HOMOLOG_VALIDATE);
    }

    /**
     * @param bool $homologValidate
     */
    public function setHomologValidate(bool $homologValidate): void
    {
        $this->options->set(self::HOMOLOG_VALIDATE, $homologValidate);
    }

    /**
     * @return bool
     */
    public function getTestUser(): bool
    {
        return $this->options->get(self::TEST_USER);
    }

    /**
     * @param bool $testUser
     */
    public function setTestUser(bool $testUser): void
    {
        $this->options->set(self::TEST_USER, $testUser);
    }

    /**
     * @return bool
     */
    public function isTestUser(): bool
    {
        return $this->getTestUser();
    }

    /**
     * @return string
     */
    public function getCredentialsPublicKey(): string
    {
        if ($this->store->isTestMode()) {
            return $this->getCredentialsPublicKeyTest();
        }

        return $this->getCredentialsPublicKeyProd();
    }

    /**
     * @return string
     */
    public function getCredentialsAccessToken(): string
    {
        if ($this->store->isTestMode()) {
            return $this->getCredentialsAccessTokenTest();
        }

        return $this->getCredentialsAccessTokenProd();
    }

    /**
     * @param string $gatewayOption
     *
     * @return array
     */
    public function getPaymentMethodsByGatewayOption(string $gatewayOption): array
    {
        $paymentMethods = $this->options->get($gatewayOption, []);

        if (!$paymentMethods) {
            $this->updatePaymentMethods();
            $paymentMethods = $this->options->get($gatewayOption, []);
        }

        if (!is_array($paymentMethods)) {
            $paymentMethods = json_decode($paymentMethods);
        }

        return $paymentMethods;
    }


    /**
     * @return array
     */
    public function getCheckoutBasicPaymentMethods(): array
    {
        return $this->getPaymentMethodsByGatewayOption(self::CHECKOUT_BASIC_PAYMENT_METHODS);
    }

    /**
     * @param array $checkoutBasicPaymentMethods
     */
    public function setCheckoutBasicPaymentMethods(array $checkoutBasicPaymentMethods): void
    {
        $this->options->set(self::CHECKOUT_BASIC_PAYMENT_METHODS, $checkoutBasicPaymentMethods);
    }

    /**
     * @return array
     */
    public function getCheckoutTicketPaymentMethods(): array
    {
        return $this->getPaymentMethodsByGatewayOption(self::CHECKOUT_TICKET_PAYMENT_METHODS);
    }

    /**
     * @param array $checkoutTicketPaymentMethods
     */
    public function setCheckoutTicketPaymentMethods(array $checkoutTicketPaymentMethods): void
    {
        $this->options->set(self::CHECKOUT_TICKET_PAYMENT_METHODS, $checkoutTicketPaymentMethods);
    }

    /**
     * @return array
     */
    public function getSiteIdPaymentMethods(): array
    {
        $siteIdPaymentMethods = $this->options->get(self::SITE_ID_PAYMENT_METHODS, []);

        if (empty($siteIdPaymentMethods)) {
            $this->updatePaymentMethodsBySiteId();
            $siteIdPaymentMethods = $this->options->get(self::SITE_ID_PAYMENT_METHODS, []);
        }

        return $siteIdPaymentMethods;
    }

    /**
     * @param array $checkoutTicketPaymentMethods
     */
    public function setSiteIdPaymentMethods(array $checkoutTicketPaymentMethods): void
    {
        $this->options->set(self::SITE_ID_PAYMENT_METHODS, $checkoutTicketPaymentMethods);
    }

    /**
     * @return array
     */
    public function getCheckoutPixPaymentMethods(): array
    {
        return $this->getPaymentMethodsByGatewayOption(self::CHECKOUT_PAYMENT_METHOD_PIX);
    }

    /**
     * @param array $checkoutPaymentMethodsPix
     */
    public function setCheckoutPixPaymentMethods(array $checkoutPaymentMethodsPix): void
    {
        $this->options->set(self::CHECKOUT_PAYMENT_METHOD_PIX, $checkoutPaymentMethodsPix);
    }

    /**
     * @return mixed
     */
    public function getAllPaymentMethods()
    {
        return $this->options->get(self::ALL_PAYMENT_METHODS, '');
    }

    /**
     * Get excluded payments
     *
     * @param $gateway
     *
     * @return array
     */
    public function getExPayments($gateway): array
    {
        $exPayments       = [];
        $exPaymentOptions = $this->getAllPaymentMethods();

        if (!empty($exPaymentOptions)) {
            $options = explode(',', $exPaymentOptions);

            foreach ($options as $option) {
                if ($this->options->getGatewayOption($gateway, "ex_payments_$option", 'yes') === 'no') {
                    $exPayments[] = $option;
                }
            }
        }

        return $exPayments;
    }

    /**
     * Update Payment Methods
     *
     * @param string|null $publicKey
     * @param string|null $accessToken
     *
     */
    public function updatePaymentMethods(string $publicKey = null, string $accessToken = null): void
    {
        if ($publicKey === null) {
            $publicKey = $this->getCredentialsPublicKey();
        }

        if ($accessToken === null) {
            $accessToken = $this->getCredentialsAccessToken();
        }

        $paymentMethodsResponse = $this->getPaymentMethods($publicKey, $accessToken);

        if ($paymentMethodsResponse['status'] !== 200) {
            $this->setCheckoutBasicPaymentMethods([]);
            $this->setCheckoutPixPaymentMethods([]);
            $this->setCheckoutTicketPaymentMethods([]);

            return;
        }

        $this->setupBasicPaymentMethods($paymentMethodsResponse);
        $this->setupPixPaymentMethods($paymentMethodsResponse);
        $this->setupTicketPaymentMethods($paymentMethodsResponse);
    }

    /**
     * Update Payment Methods
     *
     * @param string|null $siteId
     *
     */
    public function updatePaymentMethodsBySiteId(string $siteId = null): void
    {
        if ($siteId === null) {
            $siteId = $this->getSiteId();
        }

        $paymentMethodsResponseBySiteId = $this->getPaymentMethodsBySiteId($siteId);

        if ($paymentMethodsResponseBySiteId['status'] !== 200) {
            $this->setSiteIdPaymentMethods([]);
            return;
        }

        $this->setSiteIdPaymentMethods($paymentMethodsResponseBySiteId['data']);
    }

    /**
     * Setup Basic Payment Methods
     *
     * @param array $paymentMethodsResponse
     *
     */
    private function setupBasicPaymentMethods(array $paymentMethodsResponse): void
    {
        $excludedPaymentMethods   = ['consumer_credits', 'paypal', 'account_money'];
        $serializedPaymentMethods = [];

        foreach ($paymentMethodsResponse['data'] as $paymentMethod) {
            if (in_array($paymentMethod['id'], $excludedPaymentMethods, true)) {
                continue;
            }

            $serializedPaymentMethods[] = [
                'id'     => $paymentMethod['id'],
                'name'   => $paymentMethod['name'],
                'type'   => $paymentMethod['payment_type_id'],
                'image'  => $paymentMethod['secure_thumbnail'],
                'config' => 'ex_payments_' . $paymentMethod['id'],
            ];
        }

        $this->setCheckoutBasicPaymentMethods($serializedPaymentMethods);
    }

    /**
     * Setup Pix Payment Methods
     *
     * @param array $paymentMethodsResponse
     */
    private function setupPixPaymentMethods(array $paymentMethodsResponse): void
    {
        $serializedPaymentMethods = [];

        foreach ($paymentMethodsResponse['data'] as $paymentMethod) {
            if ('pix' === $paymentMethod['id']) {
                $serializedPaymentMethods[] = [
                    'id'     => $paymentMethod['id'],
                    'name'   => $paymentMethod['name'],
                    'type'   => $paymentMethod['payment_type_id'],
                    'image'  => $paymentMethod['secure_thumbnail'],
                    'config' => 'ex_payments_' . $paymentMethod['id'],
                ];

                break;
            }
        }

        $this->setCheckoutPixPaymentMethods($serializedPaymentMethods);
    }

    /**
     * Setup Ticket Payment Methods
     *
     * @param array $paymentMethodsResponse
     */
    private function setupTicketPaymentMethods(array $paymentMethodsResponse): void
    {
        $excludedPaymentMethods   = ['paypal', 'pse', 'pix',];
        $serializedPaymentMethods = [];

        foreach ($paymentMethodsResponse['data'] as $paymentMethod) {
            if (
                in_array($paymentMethod['id'], $excludedPaymentMethods, true) ||
                'account_money' === $paymentMethod['payment_type_id'] ||
                'credit_card'   === $paymentMethod['payment_type_id'] ||
                'debit_card'    === $paymentMethod['payment_type_id'] ||
                'prepaid_card'  === $paymentMethod['payment_type_id']
            ) {
                continue;
            }

            $serializedPaymentMethods[] = $paymentMethod;
        }

        $serializedPaymentMethods = $this->buildPaymentPlaces($serializedPaymentMethods);

        $this->setCheckoutTicketPaymentMethods($serializedPaymentMethods);
    }

    /**
     * Build payment places for paycash
     *
     * @param array $serializedPaymentMethods
     *
     * @return array
     */
    public function buildPaymentPlaces(array $serializedPaymentMethods): array
    {
        $paymentPlaces = [
            'paycash' => [
                [
                    'payment_option_id' => '7eleven',
                    'name'              => '7 Eleven',
                    'status'            => 'active',
                    'thumbnail'         => 'https://http2.mlstatic.com/storage/logos-api-admin/417ddb90-34ab-11e9-b8b8-15cad73057aa-s.png'
                ],
                [
                    'payment_option_id' => 'circlek',
                    'name'              => 'Circle K',
                    'status'            => 'active',
                    'thumbnail'         => 'https://http2.mlstatic.com/storage/logos-api-admin/6f952c90-34ab-11e9-8357-f13e9b392369-s.png'
                ],
                [
                    'payment_option_id' => 'soriana',
                    'name'              => 'Soriana',
                    'status'            => 'active',
                    'thumbnail'         => 'https://http2.mlstatic.com/storage/logos-api-admin/dac0bf10-01eb-11ec-ad92-052532916206-s.png'
                ],
                [
                    'payment_option_id' => 'extra',
                    'name'              => 'Extra',
                    'status'            => 'active',
                    'thumbnail'         => 'https://http2.mlstatic.com/storage/logos-api-admin/9c8f26b0-34ab-11e9-b8b8-15cad73057aa-s.png'
                ],
                [
                    'payment_option_id' => 'calimax',
                    'name'              => 'Calimax',
                    'status'            => 'active',
                    'thumbnail'         => 'https://http2.mlstatic.com/storage/logos-api-admin/52efa730-01ec-11ec-ba6b-c5f27048193b-s.png'
                ],
            ],
        ];

        foreach ($serializedPaymentMethods as $key => $method) {
            if (isset($paymentPlaces[$method['id']])) {
                $serializedPaymentMethods[$key]['payment_places'] = $paymentPlaces[$method['id']];
            }
        }

        return $serializedPaymentMethods;
    }

    /**
     * Get seller info with users credentials
     *
     * @param string $accessToken
     *
     * @return array
     */
    public function getSellerInfo(string $accessToken): array
    {
        try {
            $key   = sprintf('%sat%s', __FUNCTION__, $accessToken);
            $cache = $this->cache->getCache($key);

            if ($cache) {
                return $cache;
            }

            $uri     = '/users/me';
            $headers = ['Authorization: Bearer ' . $accessToken];

            $response           = $this->requester->get($uri, $headers);
            $serializedResponse = [
                'data'   => $response->getData(),
                'status' => $response->getStatus(),
            ];

            $this->cache->setCache($key, $serializedResponse);

            return $serializedResponse;
        } catch (\Exception $e) {
            $this->logs->file->error(
                "Mercado pago gave error to get seller info: {$e->getMessage()}",
                __CLASS__
            );

            return [
                'data'   => null,
                'status' => 500,
            ];
        }
    }

    /**
     * @param string $publicKey
     *
     * @return array
     */
    public function validatePublicKey(string $publicKey): array
    {
        return $this->validateCredentials(null, $publicKey);
    }

    /**
     * @param string $accessToken
     *
     * @return array
     */
    public function validateAccessToken(string $accessToken): array
    {
        return $this->validateCredentials($accessToken);
    }

    /**
     * Validate credentials with plugins wrapper credentials API
     *
     * @param string|null $accessToken
     * @param string|null $publicKey
     *
     * @return array
     */
    private function validateCredentials(string $accessToken = null, string $publicKey = null): array
    {
        try {
            $key   = sprintf('%sat%spk%s', __FUNCTION__, $accessToken, $publicKey);
            $cache = $this->cache->getCache($key);

            if ($cache) {
                return $cache;
            }

            $headers = [];
            $uri     = '/plugins-credentials-wrapper/credentials';

            if ($accessToken && !$publicKey) {
                $headers[] = 'Authorization: Bearer ' . $accessToken;
            }

            if ($publicKey && !$accessToken) {
                $uri = $uri . '?public_key=' . $publicKey;
            }

            $response           = $this->requester->get($uri, $headers);
            $serializedResponse = [
                'data'   => $response->getData(),
                'status' => $response->getStatus(),
            ];

            $this->cache->setCache($key, $serializedResponse);

            return $serializedResponse;
        } catch (\Exception $e) {
            $this->logs->file->error(
                "Mercado pago gave error to validate seller credentials: {$e->getMessage()}",
                __CLASS__
            );
            return [
                'data'   => null,
                'status' => 500,
            ];
        }
    }

    /**
     * Get Payment Methods
     *
     * @param string|null $publicKey
     * @param string|null $accessToken
     *
     * @return array
     */
    private function getPaymentMethods(string $publicKey = null, string $accessToken = null): array
    {
        try {
            $key   = sprintf('%sat%spk%s', __FUNCTION__, $accessToken, $publicKey);
            $cache = $this->cache->getCache($key);

            if ($cache) {
                return $cache;
            }

            $headers = [];
            $uri     = '/v1/payment_methods';

            if ($accessToken) {
                $headers[] = 'Authorization: Bearer ' . $accessToken;
            }

            if ($publicKey) {
                $uri = $uri . '?public_key=' . $publicKey;
            }

            $response           = $this->requester->get($uri, $headers);
            $serializedResponse = [
                'data'   => $response->getData(),
                'status' => $response->getStatus(),
            ];

            $this->cache->setCache($key, $serializedResponse);

            return $serializedResponse;
        } catch (\Exception $e) {
            $this->logs->file->error(
                "Mercado pago gave error to get seller payment methods: {$e->getMessage()}",
                __CLASS__
            );
            return [
                'data'   => null,
                'status' => 500,
            ];
        }
    }

    /**
     * Get Payment Methods by SiteId
     *
     * @param string $siteId
     *
     * @return array
     */
    private function getPaymentMethodsBySiteId(string $siteId): array
    {
        try {
            $key   = sprintf('%ssi%s', __FUNCTION__, $siteId);
            $cache = $this->cache->getCache($key);

            if ($cache) {
                return $cache;
            }

            $uri = '/sites/' . $siteId . '/payment_methods';

            $response           = $this->requester->get($uri);
            $serializedResponse = [
                'data'   => $response->getData(),
                'status' => $response->getStatus(),
            ];

            $this->cache->setCache($key, $serializedResponse);

            return $serializedResponse;
        } catch (\Exception $e) {
            $this->logs->file->error(
                "Mercado pago gave error to get seller payment methods by ID: {$e->getMessage()}",
                __CLASS__
            );
            return [
                'data'   => null,
                'status' => 500,
            ];
        }
    }
}
